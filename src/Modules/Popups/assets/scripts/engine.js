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
		} catch (error) {
			return {};
		}
	}

	function writeStore(key, data) {
		try {
			window.localStorage.setItem(key, JSON.stringify(data));
		} catch (error) {
			/* storage unavailable — ignore */
		}
	}

	function markSession(key) {
		try {
			window.sessionStorage.setItem(key, '1');
		} catch (error) {
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

	// Whether a trigger-initiated open is allowed: every registered rule handler
	// must pass. Free ships frequency_cap; pro registers the rest. An unknown
	// rule type (no handler) is skipped, so the open is allowed.
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
	 * Width / z-index are NOT applied here — they are compiled into the
	 * popup's generated Elementor post CSS from the document's editor controls.
	 */
	function hydrate(state) {
		if (state.hydrated) {
			return;
		}

		var root = state.root;
		var template = root.querySelector('template.elemacy-popup-tpl');
		var display = state.config.display || {};

		// Position class.
		root.classList.add(positionClass(display));

		// Sticky top bar (stays in view while scrolling).
		if (display.sticky) {
			root.classList.add('is-sticky');
		}

		// Z-index is applied on every open() instead (opening order matters
		// for modals sharing the stack; hydrate() only ever runs once).

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
				overlay.style.setProperty('--elemacy-popup-overlay-color', display.overlay.color);
			}
			var overlayOpacity = parseFloat(display.overlay.opacity);
			if (isFinite(overlayOpacity)) {
				overlayOpacity = Math.max(0, Math.min(overlayOpacity, 1));
				overlay.style.setProperty('--elemacy-popup-overlay-opacity', String(overlayOpacity));
			}
			var overlayBlur = parseFloat(display.overlay.blur);
			if (isFinite(overlayBlur)) {
				overlayBlur = Math.max(0, Math.min(overlayBlur, 40));
				overlay.style.setProperty('--elemacy-popup-overlay-blur', overlayBlur + 'px');
			}
			overlay.setAttribute('data-elemacy-overlay', '');
			root.appendChild(overlay);
			state.overlay = overlay;
		}

		// The popup box IS the cloned Elementor content wrapper (.elementor-{id})
		// itself — the same element the document's size/style controls target, so
		// frontend and editor stay in sync. We just tag it with a stable class.
		var fragment;
		if (template && template.content) {
			fragment = document.importNode(template.content, true);
		} else {
			fragment = document.createDocumentFragment();
			var wrapper = document.createElement('div');
			wrapper.innerHTML = template ? template.innerHTML : '';
			while (wrapper.firstChild) {
				fragment.appendChild(wrapper.firstChild);
			}
		}

		// Captured before appending — a DocumentFragment empties into its new
		// parent on append, but an already-obtained element reference stays
		// valid across that move.
		var content = fragment.querySelector ? fragment.querySelector('.elementor') : null;

		// The COMPLETE fragment is appended, not just the located .elementor
		// element: a prepared document can carry sibling <style>/<link>
		// markup alongside its root (see Core/Rendering/TemplateRenderer::
		// prepare()), and appending only .elementor would silently discard it.
		root.appendChild(fragment);

		if (!content) {
			// No .elementor root found (e.g. a fallback with no builder
			// content) — the fragment's own content is already under root;
			// wrap it in one element so there is still a single box to style.
			content = document.createElement('div');
			while (root.firstChild) {
				content.appendChild(root.firstChild);
			}
			root.appendChild(content);
		}
		content.classList.add('elemacy-popup__box');

		// The author's content scrolls in its own element so the box never clips
		// the close button when it is positioned outside the popup.
		var scroller = document.createElement('div');
		scroller.className = 'elemacy-popup__scroll';
		while (content.firstChild) {
			scroller.appendChild(content.firstChild);
		}
		content.appendChild(scroller);

		// Auto-injected close button when enabled. It carries the
		// `elemacy-close-popup` class so the delegated click handler closes it.
		if (display.close && display.close.button) {
			var closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'elemacy-popup__close elemacy-close-popup';
			closeBtn.setAttribute('aria-label', (window.elemacyPopups && window.elemacyPopups.i18n && window.elemacyPopups.i18n.close) || 'Close');
			closeBtn.innerHTML = '&times;';
			content.appendChild(closeBtn);
		}

		state.content = content;
		state.hydrated = true;

		// Elementor never learns about content inserted outside its own
		// render pipeline on its own — without this, a popup's widgets exist
		// in the DOM but their scripts never actually attach to them.
		if (window.ElemacyElementorLifecycle) {
			window.ElemacyElementorLifecycle.hydrate(content);
		}
	}

	function getFocusable(container) {
		return Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return el.offsetParent !== null || el === document.activeElement;
		});
	}

	// After a modal popup closes/is destroyed, focus returns to whatever
	// opened it — but that opener may no longer be connected to the
	// document (e.g. it was itself inside dynamically-replaced content).
	// Falls back to the modal now on top of the stack, if any, so focus is
	// never silently dropped while another modal is still open.
	function restoreFocusAfterModalCloses(state) {
		if (state.lastFocused && typeof state.lastFocused.focus === 'function'
			&& document.contains(state.lastFocused)) {
			state.lastFocused.focus();
			return;
		}

		var topId = ModalStack.top();
		var topState = topId !== null ? registry[topId] : null;
		if (!topState) {
			return;
		}

		var focusables = getFocusable(topState.content || topState.root);
		if (focusables.length) {
			focusables[0].focus();
		} else {
			topState.root.setAttribute('tabindex', '-1');
			topState.root.focus();
		}
	}

	/* ---------------------------------------------------------------- */
	/* Modal stack — the single owner of opening order                  */
	/* (z-index, Escape, focus trap) and the shared body-scroll lock     */
	/* across every currently-open MODAL popup. Topbars/banners/floating */
	/* elements never join it unless authored to behave modally          */
	/* (state.config.modal) — each of those keeps closing independently  */
	/* on its own on_esc setting, exactly as before.                     */
	/* ---------------------------------------------------------------- */

	var ModalStack = (function () {
		var stack = []; // ordered ids, oldest-opened first; last = topmost.
		var order = 0; // strictly increasing per push — see push's note.
		var lockers = []; // ids currently holding the shared scroll lock.
		var lockedBodyOverflow = '';

		function push(id) {
			var index = stack.indexOf(id);
			if (index !== -1) {
				stack.splice(index, 1);
			}
			stack.push(id);
			// A strictly increasing counter, not stack length/position: a
			// popup that closes and reopens must always outrank every
			// still-open modal, including ones that themselves opened
			// after it originally did but before it reopened — stack
			// position alone can repeat a value after removals.
			order += 1;
			return order;
		}

		// Out-of-order safe: removing an id from anywhere in the stack —
		// not only the top — leaves the remaining relative order intact.
		function remove(id) {
			var index = stack.indexOf(id);
			if (index !== -1) {
				stack.splice(index, 1);
			}
		}

		function top() {
			return stack.length ? stack[stack.length - 1] : null;
		}

		// Idempotent per id and counted by "how many currently-open modals
		// asked for the lock", not by call count — an out-of-order
		// release() from a modal that never actually acquired it is always
		// a safe no-op, and the original inline style is restored exactly
		// once, when the LAST locking modal releases it.
		function acquireScrollLock(id) {
			if (lockers.indexOf(id) !== -1) {
				return;
			}
			if (!lockers.length) {
				lockedBodyOverflow = document.body.style.overflow;
				document.body.style.overflow = 'hidden';
			}
			lockers.push(id);
		}

		function releaseScrollLock(id) {
			var index = lockers.indexOf(id);
			if (index === -1) {
				return;
			}
			lockers.splice(index, 1);
			if (!lockers.length) {
				document.body.style.overflow = lockedBodyOverflow;
			}
		}

		return {
			push: push,
			remove: remove,
			top: top,
			acquireScrollLock: acquireScrollLock,
			releaseScrollLock: releaseScrollLock
		};
	})();

	/* ---------------------------------------------------------------- */
	/* Open / close / destroy core — explicit per-instance state         */
	/* machine: closed -> opening -> open -> closing ->                  */
	/* closed, with destroyed reachable from any state except itself.    */
	/* `state.token` increments on every transition start; a transition  */
	/* superseded mid-flight (open-during-close, close-during-open, or   */
	/* destroy at any point) has its pending animationend listener and   */
	/* fallback timer torn down immediately, and any callback that still */
	/* somehow ran captured a now-stale token and is a guaranteed no-op. */
	/* ---------------------------------------------------------------- */

	function isActive(phase) {
		return phase === 'open' || phase === 'opening';
	}

	// Cancels whatever the CURRENT transition (if any) is still waiting on —
	// called at the start of every new transition so a stale animationend
	// or fallback timer from a superseded one can never fire later.
	function cancelPendingTransition(state) {
		if (state.transitionTimer) {
			window.clearTimeout(state.transitionTimer);
			state.transitionTimer = null;
		}
		if (state.transitionListener && state.content) {
			state.content.removeEventListener('animationend', state.transitionListener);
		}
		state.transitionListener = null;
	}

	// Runs `settle` once (removing the very listener it fires from —
	// animationend does not auto-remove itself), on whichever of the box's
	// own animationend or a fixed fallback timeout happens first — and only
	// if no newer transition has started since `token` was issued.
	function awaitTransitionEnd(state, token, settle) {
		var done = false;
		var onSettle = function () {
			if (done || state.token !== token) {
				return;
			}
			done = true;
			cancelPendingTransition(state);
			settle();
		};
		state.transitionListener = onSettle;
		if (state.content) {
			state.content.addEventListener('animationend', onSettle);
		}
		state.transitionTimer = window.setTimeout(onSettle, 600);
	}

	function open(id) {
		var state = registry[id];
		if (!state || state.phase === 'destroyed' || isActive(state.phase)) {
			return;
		}

		hydrate(state);
		cancelPendingTransition(state);
		if (state.autoCloseTimer) {
			window.clearTimeout(state.autoCloseTimer);
			state.autoCloseTimer = null;
		}

		var token = ++state.token;
		state.phase = 'opening';

		var root = state.root;
		var display = state.config.display || {};

		root.removeAttribute('hidden');
		root.setAttribute('aria-hidden', 'false');
		root.classList.remove('is-closing');
		root.classList.add('is-open');

		// Body scroll lock — shared across every currently-open modal via
		// the stack, not saved/restored per popup (see ModalStack above).
		if (display.prevent_body_scroll) {
			ModalStack.acquireScrollLock(id);
		}

		// Focus management, opening order (z-index/Escape/Tab) and the
		// scroll lock above all apply to modal popups only; non-modal bars
		// (topbar/banner/floating) must not steal or trap keyboard focus
		// and never join the stack.
		if (state.config.modal) {
			var stackOrder = ModalStack.push(id);
			if (display.z_index) {
				root.style.zIndex = String(parseInt(display.z_index, 10) + stackOrder);
			}

			state.lastFocused = document.activeElement;

			var focusables = getFocusable(state.content || root);
			if (focusables.length) {
				focusables[0].focus();
			} else {
				root.setAttribute('tabindex', '-1');
				root.focus();
			}
		} else if (display.z_index) {
			root.style.zIndex = String(display.z_index);
		}

		var finishOpen = function () {
			state.phase = 'open';
			root.classList.remove('is-animating');

			// Auto-close timer, guarded against a stale/cleared transition.
			var autoClose = display.close && display.close.auto_close_s ? parseFloat(display.close.auto_close_s) : 0;
			if (autoClose > 0) {
				state.autoCloseTimer = window.setTimeout(function () {
					if (state.token === token && state.phase === 'open') {
						close(id);
					}
				}, autoClose * 1000);
			}

			// Lifecycle seam: lets add-ons react to a popup being displayed
			// (e.g. pro analytics records an impression). Symmetric to
			// 'elemacy-popup:closed'.
			try {
				document.dispatchEvent(
					new CustomEvent('elemacy-popup:shown', { detail: { id: id } })
				);
			} catch (error) {
				/* CustomEvent unsupported — ignore */
			}
		};

		var animIn = display.animation && display.animation.in ? display.animation.in : 'none';
		if (animIn && animIn !== 'none' && !prefersReducedMotion) {
			root.classList.add('is-animating');
			awaitTransitionEnd(state, token, finishOpen);
		} else {
			finishOpen();
		}
	}

	function close(id) {
		var state = registry[id];
		if (!state || state.phase === 'destroyed' || state.phase === 'closed' || state.phase === 'closing') {
			return;
		}

		cancelPendingTransition(state);
		if (state.autoCloseTimer) {
			window.clearTimeout(state.autoCloseTimer);
			state.autoCloseTimer = null;
		}

		var token = ++state.token;
		state.phase = 'closing';

		var root = state.root;
		var display = state.config.display || {};

		// Leaves the modal stack immediately (not only once the close
		// transition settles) so the next-topmost modal, if any, becomes
		// eligible for Escape/Tab right away rather than waiting out this
		// one's close animation.
		if (state.config.modal) {
			ModalStack.remove(id);
		}

		var finishClose = function () {
			state.phase = 'closed';
			root.classList.remove('is-open', 'is-closing', 'is-animating');
			root.setAttribute('aria-hidden', 'true');
			root.setAttribute('hidden', '');

			if (display.prevent_body_scroll) {
				ModalStack.releaseScrollLock(id);
			}

			if (state.config.modal) {
				restoreFocusAfterModalCloses(state);
			}

			// Extension seam: lets pro triggers (e.g. "after another popup
			// closes") react to a close without patching the engine.
			try {
				document.dispatchEvent(
					new CustomEvent('elemacy-popup:closed', { detail: { id: id } })
				);
			} catch (error) {
				/* CustomEvent unsupported — ignore */
			}
		};

		var animOut = display.animation && display.animation.out ? display.animation.out : 'none';
		if (animOut && animOut !== 'none' && !prefersReducedMotion) {
			// .elemacy-popup.is-animating supplies animation-duration/timing
			// for both directions' keyframes (popups.css); finishOpen()
			// removes it once the open transition settles, so it must be
			// added again here rather than assumed still present.
			root.classList.add('is-closing', 'is-animating');
			awaitTransitionEnd(state, token, finishClose);
		} else {
			finishClose();
		}
	}

	// Permanently retires a popup instance: idempotent (a second call on an
	// already-destroyed instance is a safe no-op), releases the lifecycle
	// adapter's own record of its hydrated subtree (see elementor-
	// lifecycle.js's release()), and drops its click-trigger selectors so
	// the document-level delegated listener no longer matches anything for
	// it — the functional equivalent of tearing down trigger listeners,
	// since this engine never attaches one addEventListener per popup.
	function destroy(id) {
		var state = registry[id];
		if (!state || state.phase === 'destroyed') {
			return;
		}

		cancelPendingTransition(state);
		if (state.autoCloseTimer) {
			window.clearTimeout(state.autoCloseTimer);
			state.autoCloseTimer = null;
		}

		var wasActive = isActive(state.phase);
		state.token++;
		state.phase = 'destroyed';
		state.clickSelectors = [];

		// close() already leaves the stack the moment a close transition
		// starts, so this only has further work to do when destroy() is
		// called directly from 'open'/'opening' — but remove() is
		// idempotent, so calling it unconditionally is always safe.
		if (state.config.modal) {
			ModalStack.remove(id);
		}

		if (wasActive) {
			var root = state.root;
			var display = state.config.display || {};

			root.classList.remove('is-open', 'is-closing', 'is-animating');
			root.setAttribute('aria-hidden', 'true');
			root.setAttribute('hidden', '');

			if (display.prevent_body_scroll) {
				ModalStack.releaseScrollLock(id);
			}
			if (state.config.modal) {
				restoreFocusAfterModalCloses(state);
			}
		}

		if (window.ElemacyElementorLifecycle && state.content) {
			window.ElemacyElementorLifecycle.release(state.content);
		}

		try {
			document.dispatchEvent(
				new CustomEvent('elemacy-popup:destroyed', { detail: { id: id } })
			);
		} catch (error) {
			/* CustomEvent unsupported — ignore */
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
		if (!state || state.phase === 'destroyed' || isActive(state.phase)) {
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
		if (selector) {
			state.clickSelectors.push(selector);
		}
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
		} catch (error) {
			return null;
		}
	}

	function setupDelegation() {
		document.addEventListener('click', function (event) {
			var target = event.target;

			// Explicit open buttons (button-click opens directly, no frequency).
			var openEl =
				closest(target, '.elemacy-open-popup') ||
				closest(target, '[data-elemacy-popup-open]');
			if (openEl) {
				var openId =
					openEl.getAttribute('data-popup-id') ||
					openEl.getAttribute('data-elemacy-popup-open');
				if (openId) {
					event.preventDefault();
					open(parseInt(openId, 10));
					return;
				}
			}

			// Per-popup click-trigger selectors (a popup may have several click
			// triggers; any matching selector opens it).
			Object.keys(registry).forEach(function (id) {
				var state = registry[id];
				for (var s = 0; s < state.clickSelectors.length; s++) {
					if (matchesSelector(target, state.clickSelectors[s])) {
						triggerOpen(parseInt(id, 10));
						break;
					}
				}
			});

			// Generic close controls.
			var closeEl =
				closest(target, '.elemacy-close-popup') ||
				closest(target, '[data-elemacy-popup-close]');
			if (closeEl) {
				var rootForClose = closest(closeEl, '[data-elemacy-popup-id]');
				if (rootForClose) {
					event.preventDefault();
					close(parseInt(rootForClose.getAttribute('data-elemacy-popup-id'), 10));
				}
				return;
			}

			// Overlay click close.
			if (target.hasAttribute && target.hasAttribute('data-elemacy-overlay')) {
				var overlayRoot = closest(target, '[data-elemacy-popup-id]');
				if (overlayRoot) {
					var overlayId = parseInt(overlayRoot.getAttribute('data-elemacy-popup-id'), 10);
					var overlayState = registry[overlayId];
					if (overlayState && overlayState.config.display && overlayState.config.display.close &&
						overlayState.config.display.close.on_overlay_click) {
						close(overlayId);
					}
				}
			}
		});

		// ESC + focus-trap tab cycling. Both act on the modal stack's own
		// topmost entry only: with
		// several modals open, Escape/Tab release and trap one at a time,
		// innermost first, not every open modal simultaneously.
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' || event.keyCode === 27) {
				// A modal on top of the stack always closes on Escape,
				// regardless of its own on_esc setting — required to avoid a
				// keyboard trap (WCAG 2.1.2) for whichever modal is
				// currently trapping Tab, i.e. the topmost one.
				var topModal = ModalStack.top();
				if (topModal !== null) {
					close(topModal);
				}

				// Non-modal bars (topbar/banner/floating) never join the
				// stack; each still closes independently on its own
				// authored on_esc setting.
				Object.keys(registry).forEach(function (id) {
					var state = registry[id];
					if (state.config.modal || !isActive(state.phase)) {
						return;
					}
					var closeConfig = state.config.display && state.config.display.close;
					if (closeConfig && closeConfig.on_esc) {
						close(parseInt(id, 10));
					}
				});
				return;
			}

			if (event.key === 'Tab' || event.keyCode === 9) {
				// Only the topmost modal traps Tab; non-modal bars and any
				// modal buried under it leave page focus alone.
				var topModalId = ModalStack.top();
				var openState = topModalId !== null ? registry[topModalId] : null;
				if (!openState || !openState.content) {
					return;
				}
				var focusables = getFocusable(openState.content);
				if (!focusables.length) {
					return;
				}
				var first = focusables[0];
				var last = focusables[focusables.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
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
				phase: 'closed',
				token: 0,
				transitionTimer: null,
				transitionListener: null,
				content: null,
				overlay: null,
				clickSelectors: [],
				autoCloseTimer: null,
				lastFocused: null
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
		destroy: destroy,
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
