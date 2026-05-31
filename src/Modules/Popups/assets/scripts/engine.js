/**
 * Elemacy Popups — frontend engine.
 *
 * Self-contained IIFE. Reads window.elemacyPopups.popups (array of per-popup
 * configs emitted by PopupConfigResource) and wires up triggers, open/close,
 * a11y focus-trap, and frequency storage.
 *
 * Works with or without Elementor on the page. jQuery is a declared dependency
 * but only used for convenience; falls back to vanilla where possible.
 */
(function (window, document) {
	'use strict';

	var FOCUSABLE = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled]):not([type="hidden"])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])'
	].join(',');

	var prefersReducedMotion =
		window.matchMedia &&
		window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Registries (extension seams for pro).
	var triggerHandlers = {};

	// Rule handlers return false to block an open. Free ships the frequency-cap
	// rule ("show up to X times"); pro registers the rest via
	// window.ElemacyPopups.registerRule. `store` is this popup's saved counters.
	var ruleHandlers = {
		frequency_cap: function (rule, config, store) {
			var max = rule && rule.max ? parseInt(rule.max, 10) : 0;
			if (!max) {
				return true;
			}
			return (store.shown_count || 0) < max;
		}
	};

	// Per-popup runtime state, keyed by popup id.
	var registry = {};

	var initialized = false;

	/* ---------------------------------------------------------------- */
	/* Storage helpers (frequency plumbing).                            */
	/* ---------------------------------------------------------------- */

	function readStore(key) {
		try {
			var raw = window.localStorage.getItem(key);
			return raw ? JSON.parse(raw) : {};
		} catch (e) {
			return {};
		}
	}

	function writeStore(key, data) {
		try {
			window.localStorage.setItem(key, JSON.stringify(data));
		} catch (e) {
			/* storage unavailable — ignore */
		}
	}

	function markSession(key) {
		try {
			window.sessionStorage.setItem(key, '1');
		} catch (e) {
			/* ignore */
		}
	}

	function recordShown(config) {
		var data = readStore(config.storage_key);
		data.shown_count = (data.shown_count || 0) + 1;
		data.last_shown_at = Date.now();
		writeStore(config.storage_key, data);
		markSession(config.storage_key);
	}

	/**
	 * Whether a trigger-initiated open is currently allowed.
	 *
	 * NOTE: real rule semantics (max count, once-per-session, schedule) are
	 * refined in a later milestone. For now this only delegates to any
	 * registered rule handlers and otherwise allows the open.
	 */
	function canShow(config) {
		if (config.rules && config.rules.length) {
			for (var i = 0; i < config.rules.length; i++) {
				var rule = config.rules[i];
				var handler = ruleHandlers[rule.type];
				if (handler && handler(rule, config, readStore(config.storage_key)) === false) {
					return false;
				}
			}
		}
		return true;
	}

	/* ---------------------------------------------------------------- */
	/* DOM helpers.                                                      */
	/* ---------------------------------------------------------------- */

	function getRoot(id) {
		return document.querySelector('[data-elemacy-popup-id="' + id + '"]');
	}

	function positionClass(display) {
		var pos = display && display.position ? String(display.position) : 'center';
		return 'elemacy-popup--pos-' + pos;
	}

	/**
	 * Hydrate the popup once: clone the <template> content into the root and
	 * apply runtime display config (overlay, position, animation, close).
	 *
	 * Width / height / z-index are NOT applied here — they are compiled into the
	 * popup's generated Elementor post CSS from the document's editor controls.
	 */
	function hydrate(state) {
		if (state.hydrated) {
			return;
		}

		var root = state.root;
		var tpl = root.querySelector('template.elemacy-popup-tpl');
		var display = state.config.display || {};

		// Position class.
		root.classList.add(positionClass(display));

		// Sticky top bar (stays in view while scrolling).
		if (display.sticky) {
			root.classList.add('is-sticky');
		}

		// Z-index on the positioning root (above page content).
		if (display.z_index) {
			root.style.zIndex = String(display.z_index);
		}

		// Animation class.
		var animIn = display.animation && display.animation.in ? display.animation.in : 'none';
		if (animIn && animIn !== 'none' && !prefersReducedMotion) {
			root.classList.add('elemacy-popup--anim-' + animIn);
		}

		// Overlay.
		if (display.overlay && display.overlay.enabled) {
			var overlay = document.createElement('div');
			overlay.className = 'elemacy-popup__overlay';
			if (display.overlay.color) {
				overlay.style.background = display.overlay.color;
			}
			if (typeof display.overlay.opacity !== 'undefined') {
				overlay.style.opacity = String(display.overlay.opacity);
			}
			overlay.setAttribute('data-elemacy-overlay', '');
			root.appendChild(overlay);
			state.overlay = overlay;
		}

		// The popup box IS the cloned Elementor content wrapper (.elementor-{id})
		// itself — the same element the document's size/style controls target, so
		// frontend and editor stay in sync. We just tag it with a stable class.
		var fragment;
		if (tpl && tpl.content) {
			fragment = document.importNode(tpl.content, true);
		} else {
			fragment = document.createDocumentFragment();
			var tmp = document.createElement('div');
			tmp.innerHTML = tpl ? tpl.innerHTML : '';
			while (tmp.firstChild) {
				fragment.appendChild(tmp.firstChild);
			}
		}

		var content = fragment.querySelector ? fragment.querySelector('.elementor') : null;
		if (!content) {
			content = document.createElement('div');
			content.appendChild(fragment);
		}
		content.classList.add('elemacy-popup__box');

		// Auto-injected close button when enabled. It carries the
		// `elemacy-close-popup` class so the delegated click handler closes it.
		if (display.close && display.close.button) {
			var closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'elemacy-popup__close elemacy-close-popup';
			closeBtn.setAttribute('aria-label', 'Close');
			closeBtn.innerHTML = '&times;';
			content.appendChild(closeBtn);
		}

		root.appendChild(content);
		state.content = content;
		state.hydrated = true;
	}

	function getFocusable(container) {
		return Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return el.offsetParent !== null || el === document.activeElement;
		});
	}

	/* ---------------------------------------------------------------- */
	/* Open / close core.                                               */
	/* ---------------------------------------------------------------- */

	function open(id) {
		var state = registry[id];
		if (!state || state.isOpen) {
			return;
		}

		hydrate(state);

		var root = state.root;
		var display = state.config.display || {};

		state.lastFocused = document.activeElement;

		root.removeAttribute('hidden');
		root.setAttribute('aria-hidden', 'false');
		root.classList.remove('is-closing');
		root.classList.add('is-open');
		if (!prefersReducedMotion) {
			root.classList.add('is-animating');
		}
		state.isOpen = true;

		// Body scroll lock.
		if (display.prevent_body_scroll) {
			state.prevBodyOverflow = document.body.style.overflow;
			document.body.style.overflow = 'hidden';
		}

		// Focus trap: focus first focusable.
		var focusables = getFocusable(state.content || root);
		if (focusables.length) {
			focusables[0].focus();
		} else {
			root.setAttribute('tabindex', '-1');
			root.focus();
		}

		// Auto-close timer.
		var autoClose = display.close && display.close.auto_close_s ? parseFloat(display.close.auto_close_s) : 0;
		if (autoClose > 0) {
			state.autoCloseTimer = window.setTimeout(function () {
				close(id);
			}, autoClose * 1000);
		}
	}

	function close(id) {
		var state = registry[id];
		if (!state || !state.isOpen) {
			return;
		}

		var root = state.root;
		var display = state.config.display || {};

		if (state.autoCloseTimer) {
			window.clearTimeout(state.autoCloseTimer);
			state.autoCloseTimer = null;
		}

		var animOut = display.animation && display.animation.out ? display.animation.out : 'none';

		function finalize() {
			root.classList.remove('is-open', 'is-closing', 'is-animating');
			root.setAttribute('aria-hidden', 'true');
			root.setAttribute('hidden', '');
			state.isOpen = false;

			if (display.prevent_body_scroll) {
				document.body.style.overflow = state.prevBodyOverflow || '';
			}

			// Restore focus.
			if (state.lastFocused && typeof state.lastFocused.focus === 'function') {
				state.lastFocused.focus();
			}

			// Extension seam: lets pro triggers (e.g. "after another popup
			// closes") react to a close without patching the engine.
			try {
				document.dispatchEvent(
					new CustomEvent('elemacy-popup:closed', { detail: { id: id } })
				);
			} catch (e) {
				/* CustomEvent unsupported — ignore */
			}
		}

		if (animOut && animOut !== 'none' && !prefersReducedMotion) {
			root.classList.add('is-closing');
			var done = false;
			var onEnd = function () {
				if (done) {
					return;
				}
				done = true;
				if (state.content) {
					state.content.removeEventListener('animationend', onEnd);
				}
				finalize();
			};
			if (state.content) {
				state.content.addEventListener('animationend', onEnd);
			}
			// Safety fallback if animationend never fires.
			window.setTimeout(onEnd, 600);
		} else {
			finalize();
		}
	}

	function closeAll() {
		Object.keys(registry).forEach(function (id) {
			close(id);
		});
	}

	/* ---------------------------------------------------------------- */
	/* Triggers.                                                         */
	/* ---------------------------------------------------------------- */

	/**
	 * Open initiated by a trigger (respects frequency); records the show.
	 */
	function triggerOpen(id) {
		var state = registry[id];
		if (!state || state.isOpen) {
			return;
		}
		if (!canShow(state.config)) {
			return;
		}
		recordShown(state.config);
		open(id);
	}

	function setupPageLoad(id, params) {
		var delay = (params && params.delay ? parseFloat(params.delay) : 0) * 1000;
		window.setTimeout(function () {
			triggerOpen(id);
		}, delay);
	}

	function setupClickTrigger(id, params) {
		var selector = params && params.selector ? String(params.selector) : '';
		var state = registry[id];
		state.clickSelector = selector;
	}

	function setupTriggers(config) {
		// No trigger configured: show on page load by default so the element
		// actually appears. Topbars, floating elements and banners are
		// persistent UI; popups default to showing on load. Authors add explicit
		// triggers (scroll, click, exit-intent…) to refine this.
		if (!config.triggers || !config.triggers.length) {
			setupPageLoad(config.id, {});
			return;
		}

		config.triggers.forEach(function (trigger) {
			// Each trigger's PHP to_js_config() emits a FLAT object
			// ({type, ...params}), so the params live on the trigger itself —
			// not under a nested `params` key.
			var params = trigger;
			switch (trigger.type) {
				case 'page_load':
					setupPageLoad(config.id, params);
					break;
				case 'click':
					setupClickTrigger(config.id, params);
					break;
				default:
					// Pro triggers (scroll, exit-intent, inactivity, …) register
					// a handler via window.ElemacyPopups.registerTrigger.
					if (triggerHandlers[trigger.type]) {
						triggerHandlers[trigger.type](config.id, params, {
							open: function () {
								triggerOpen(config.id);
							}
						});
					}
					break;
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Delegated document-level listeners (open/close).                 */
	/* ---------------------------------------------------------------- */

	function closest(el, selector) {
		if (el.closest) {
			return el.closest(selector);
		}
		while (el && el.nodeType === 1) {
			if (el.matches && el.matches(selector)) {
				return el;
			}
			el = el.parentElement;
		}
		return null;
	}

	function matchesSelector(el, selector) {
		if (!selector) {
			return null;
		}
		try {
			return closest(el, selector);
		} catch (e) {
			return null;
		}
	}

	function setupDelegation() {
		document.addEventListener('click', function (e) {
			var target = e.target;

			// Explicit open buttons (button-click opens directly, no frequency).
			var openEl =
				closest(target, '.elemacy-open-popup') ||
				closest(target, '[data-elemacy-popup-open]');
			if (openEl) {
				var openId =
					openEl.getAttribute('data-popup-id') ||
					openEl.getAttribute('data-elemacy-popup-open');
				if (openId) {
					e.preventDefault();
					open(parseInt(openId, 10));
					return;
				}
			}

			// Per-popup click-trigger selectors.
			Object.keys(registry).forEach(function (id) {
				var state = registry[id];
				if (state.clickSelector && matchesSelector(target, state.clickSelector)) {
					triggerOpen(parseInt(id, 10));
				}
			});

			// Generic close controls.
			var closeEl =
				closest(target, '.elemacy-close-popup') ||
				closest(target, '[data-elemacy-popup-close]');
			if (closeEl) {
				var rootForClose = closest(closeEl, '[data-elemacy-popup-id]');
				if (rootForClose) {
					e.preventDefault();
					close(parseInt(rootForClose.getAttribute('data-elemacy-popup-id'), 10));
				}
				return;
			}

			// Overlay click close.
			if (target.hasAttribute && target.hasAttribute('data-elemacy-overlay')) {
				var overlayRoot = closest(target, '[data-elemacy-popup-id]');
				if (overlayRoot) {
					var oid = parseInt(overlayRoot.getAttribute('data-elemacy-popup-id'), 10);
					var oState = registry[oid];
					if (oState && oState.config.display && oState.config.display.close &&
						oState.config.display.close.on_overlay_click) {
						close(oid);
					}
				}
			}
		});

		// ESC + focus-trap tab cycling.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				Object.keys(registry).forEach(function (id) {
					var state = registry[id];
					if (state.isOpen && state.config.display && state.config.display.close &&
						state.config.display.close.on_esc) {
						close(parseInt(id, 10));
					}
				});
				return;
			}

			if (e.key === 'Tab' || e.keyCode === 9) {
				var openState = null;
				Object.keys(registry).forEach(function (id) {
					if (registry[id].isOpen) {
						openState = registry[id];
					}
				});
				if (!openState || !openState.content) {
					return;
				}
				var focusables = getFocusable(openState.content);
				if (!focusables.length) {
					return;
				}
				var first = focusables[0];
				var last = focusables[focusables.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Init.                                                             */
	/* ---------------------------------------------------------------- */

	function init() {
		if (initialized) {
			return;
		}

		var data = window.elemacyPopups;
		if (!data || !data.popups || !data.popups.length) {
			return;
		}

		initialized = true;

		data.popups.forEach(function (config) {
			var root = getRoot(config.id);
			if (!root) {
				return;
			}

			registry[config.id] = {
				config: config,
				root: root,
				hydrated: false,
				isOpen: false,
				content: null,
				overlay: null,
				clickSelector: '',
				autoCloseTimer: null,
				lastFocused: null,
				prevBodyOverflow: ''
			};

			setupTriggers(config);
		});

		setupDelegation();
	}

	/* ---------------------------------------------------------------- */
	/* Public API + bootstrapping.                                      */
	/* ---------------------------------------------------------------- */

	window.ElemacyPopups = {
		open: open,
		close: close,
		closeAll: closeAll,
		registerTrigger: function (type, fn) {
			triggerHandlers[type] = fn;
		},
		registerRule: function (type, fn) {
			ruleHandlers[type] = fn;
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Also init on Elementor frontend init (handles cached/late-rendered pages).
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', init);
	}
})(window, document);
