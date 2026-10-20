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

	// Trigger types the engine runs itself; every other type needs a handler
	// an extension registers.
	var BUILTIN_TRIGGERS = { page_load: true, click: true };

	// Embedded players that only stop when their iframe is reloaded.
	var VIDEO_EMBED = /(?:youtube\.com|youtube-nocookie\.com)\/embed|player\.vimeo\.com\/video/i;

	// Open links and attributes (the page-markup contract; see docs/CONTRACTS.md).
	var OPEN_HREF = /^#elemacy-popup-(\d+)$/;
	var CLOSE_HREF = '#elemacy-popup-close';
	var POPUP_HREF_PREFIX = '#elemacy-popup-';

	// Both directions of every transition finish within this long (popups.css);
	// the fallback timer allows a little more.
	var TRANSITION_FALLBACK_MS = 500;

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

	// Debug flag from the server (WP_DEBUG); set in init().
	var debug = false;

	// Error keys already reported, so a handler that throws on every event
	// logs once.
	var reported = {};

	/**
	 * Popup ids arrive as numbers from the server and as strings from markup
	 * and third-party code. Everything public takes either and uses this form.
	 */
	function toId(value) {
		var id = parseInt(value, 10);
		return isNaN(id) ? null : id;
	}

	function reportOnce(key, error) {
		if (reported[key]) {
			return;
		}
		reported[key] = true;
		if (window.console && window.console.error) {
			window.console.error('Elemacy Popups: ' + key, error);
		}
	}

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

	// Whether a trigger-initiated open is allowed: every rule handler must pass.
	// Free ships frequency_cap; pro registers the rest. The server never sends a
	// popup whose rule it cannot evaluate, so a rule with no handler here means
	// the extension that provides it did not load: fail closed, as the server
	// does. A handler that throws counts as blocking, too.
	function canShow(config) {
		if (config.rules && config.rules.length) {
			for (var i = 0; i < config.rules.length; i++) {
				var rule = config.rules[i];
				var handler = ruleHandlers[rule.type];
				if (!handler) {
					return false;
				}
				try {
					if (handler(rule, config, readStore(config.storage_key)) === false) {
						return false;
					}
				} catch (error) {
					reportOnce('rule "' + rule.type + '" threw', error);
					return false;
				}
			}
		}
		return true;
	}

	/* ---------------------------------------------------------------- */
	/* Readiness gate. A popup's automatic and click triggers are armed  */
	/* only once a handler exists for every trigger type and client rule */
	/* it uses, however late the script providing it loads.              */
	/* ---------------------------------------------------------------- */

	function missingHandlers(config) {
		var needs = { triggers: {}, rules: {} };
		(config.triggers || []).forEach(function (trigger) {
			if (!BUILTIN_TRIGGERS[trigger.type] && !triggerHandlers[trigger.type]) {
				needs.triggers[trigger.type] = true;
			}
		});
		(config.rules || []).forEach(function (rule) {
			if (!ruleHandlers[rule.type]) {
				needs.rules[rule.type] = true;
			}
		});
		return needs;
	}

	function isReady(state) {
		return !Object.keys(state.needs.triggers).length && !Object.keys(state.needs.rules).length;
	}

	// Arms every popup that has been waiting for the handler just registered.
	function armWaiting(kind, type) {
		Object.keys(registry).forEach(function (id) {
			var state = registry[id];
			if (state.armed || state.phase === 'destroyed') {
				return;
			}
			delete state.needs[kind][type];
			if (isReady(state)) {
				arm(state);
			}
		});
	}

	function arm(state) {
		if (state.armed || state.phase === 'destroyed') {
			return;
		}
		state.armed = true;
		try {
			setupTriggers(state.config);
		} catch (error) {
			reportOnce('could not arm popup ' + state.config.id, error);
		}
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

	// Classes the root carries from its own display config. Idempotent, so a
	// root the server already printed open can take them too.
	function applyRootClasses(state) {
		var root = state.root;
		var display = state.config.display || {};

		// Position class.
		root.classList.add(positionClass(display));

		// Sticky top bar (stays in view while scrolling).
		if (display.sticky) {
			root.classList.add('is-sticky');
		}

		// Legacy styling hook: the entrance choice. The transition itself runs
		// from the directional classes added on open and close.
		var animIn = display.animation && display.animation.in ? display.animation.in : 'none';
		if (animIn && animIn !== 'none' && !prefersReducedMotion) {
			root.classList.add('elemacy-popup--anim-' + animIn);
		}
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

		applyRootClasses(state);

		// Z-index is applied on every open() instead (opening order matters
		// for modals sharing the stack; hydrate() only ever runs once).

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
		// PopupRenderer::box_markup() prints the same structure for a top bar
		// the server shows at first paint: keep the two in step.
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
	}

	// Elementor never learns about content inserted outside its own render
	// pipeline on its own — without this, a popup's widgets exist in the DOM but
	// their scripts never actually attach to them. Runs once, from open(), after
	// the root is visible: widgets that measure themselves (carousels) must see
	// their real size.
	function hydrateWidgets(state) {
		if (state.widgetsHydrated || !state.content) {
			return;
		}
		state.widgetsHydrated = true;
		if (window.ElemacyElementorLifecycle) {
			window.ElemacyElementorLifecycle.hydrate(state.content);
		}
	}

	function getFocusable(container) {
		return Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE)).filter(function (el) {
			return el.offsetParent !== null || el === document.activeElement;
		});
	}

	// Moves focus into a modal: its first focusable element, else the root.
	function focusInside(modalState) {
		var focusables = getFocusable(modalState.content || modalState.root);
		if (focusables.length) {
			focusables[0].focus();
		} else {
			modalState.root.setAttribute('tabindex', '-1');
			modalState.root.focus();
		}
	}

	function modalContains(modalState, el) {
		return !!el && (modalState.root === el || modalState.root.contains(el));
	}

	// After a modal popup closes/is destroyed. While another modal is still
	// open, focus belongs to it — it stays there when it is already inside,
	// and moves there otherwise — and only when none remains does it go back
	// to whatever opened this one (if that is still connected).
	function restoreFocusAfterModalCloses(state) {
		var topId = ModalStack.top();
		var topState = topId !== null ? registry[topId] : null;
		if (topState) {
			if (!modalContains(topState, document.activeElement)) {
				focusInside(topState);
			}
			return;
		}

		if (state.lastFocused && typeof state.lastFocused.focus === 'function'
			&& document.contains(state.lastFocused)) {
			state.lastFocused.focus();
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
		var saved = null; // page styles to put back when the last lock releases.

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
		// a safe no-op, and the original inline styles are restored exactly
		// once, when the LAST locking modal releases it.
		//
		// Hiding overflow removes a classic scrollbar and widens the page by
		// its width, which would shift centered content sideways. The width is
		// measured before hiding and added back as body padding. <html> is
		// locked too: a page whose scroller is <html> ignores body overflow.
		function acquireScrollLock(id) {
			if (lockers.indexOf(id) !== -1) {
				return;
			}
			if (!lockers.length) {
				var html = document.documentElement;
				var body = document.body;
				saved = {
					bodyOverflow: body.style.overflow,
					bodyPaddingRight: body.style.paddingRight,
					htmlOverflow: html.style.overflow
				};
				var gap = window.innerWidth - html.clientWidth;
				if (gap > 0) {
					var padding = parseFloat(window.getComputedStyle(body).paddingRight) || 0;
					body.style.paddingRight = (padding + gap) + 'px';
				}
				body.style.overflow = 'hidden';
				html.style.overflow = 'hidden';
			}
			lockers.push(id);
		}

		function releaseScrollLock(id) {
			var index = lockers.indexOf(id);
			if (index === -1) {
				return;
			}
			lockers.splice(index, 1);
			if (!lockers.length && saved) {
				document.body.style.overflow = saved.bodyOverflow;
				document.body.style.paddingRight = saved.bodyPaddingRight;
				document.documentElement.style.overflow = saved.htmlOverflow;
				saved = null;
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

	function animationName(display, direction) {
		var animation = display.animation && display.animation[direction] ? display.animation[direction] : 'none';
		return animation && animation !== 'none' && !prefersReducedMotion ? animation : null;
	}

	// Drops the directional transition classes: `--in-{name}` while opening,
	// `--out-{name}` while closing (popups.css animates each from its own class).
	function clearTransitionClasses(root) {
		var drop = [];
		for (var i = 0; i < root.classList.length; i++) {
			var name = root.classList[i];
			if (name.indexOf('elemacy-popup--in-') === 0 || name.indexOf('elemacy-popup--out-') === 0) {
				drop.push(name);
			}
		}
		drop.forEach(function (name) {
			root.classList.remove(name);
		});
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
	// if no newer transition has started since `token` was issued. An
	// animationend from anything inside the box (a widget's own animation
	// bubbles up to here) is not the box finishing, so it is ignored.
	function awaitTransitionEnd(state, token, settle) {
		var done = false;
		var onSettle = function (event) {
			if (event && event.type === 'animationend' && event.target !== state.content) {
				return;
			}
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
		state.transitionTimer = window.setTimeout(onSettle, TRANSITION_FALLBACK_MS);
	}

	// Pauses audio and video, and reloads the embedded players that only stop
	// that way (their iframe has no pause we can reach). Called once a popup has
	// finished closing.
	function stopMedia(container) {
		if (!container || !container.querySelectorAll) {
			return;
		}
		Array.prototype.forEach.call(container.querySelectorAll('video, audio'), function (media) {
			try {
				media.pause();
			} catch (error) {
				/* ignore */
			}
		});
		Array.prototype.forEach.call(container.querySelectorAll('iframe'), function (frame) {
			var src = frame.getAttribute('src');
			if (src && VIDEO_EMBED.test(src)) {
				frame.setAttribute('src', src);
			}
		});
	}

	// The popup is on screen: bookkeeping shared by a popup that finished its
	// entrance animation and one the server printed already open.
	function finishOpen(state, token) {
		var root = state.root;
		var display = state.config.display || {};
		var id = state.config.id;

		state.phase = 'open';
		state.reachedOpen = true;
		root.classList.remove('is-animating');
		clearTransitionClasses(root);

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
	}

	function open(id) {
		id = toId(id);
		var state = id !== null ? registry[id] : null;
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

		clearTransitionClasses(root);
		root.removeAttribute('hidden');
		root.setAttribute('aria-hidden', 'false');
		root.classList.remove('is-closing');
		root.classList.add('is-open');

		// Visible now, so widgets that size themselves see real dimensions.
		hydrateWidgets(state);

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
			focusInside(state);
		} else if (display.z_index) {
			root.style.zIndex = String(display.z_index);
		}

		var animIn = animationName(display, 'in');
		if (animIn) {
			root.classList.add('is-animating', 'elemacy-popup--in-' + animIn);
			awaitTransitionEnd(state, token, function () {
				finishOpen(state, token);
			});
		} else {
			finishOpen(state, token);
		}
	}

	function close(id) {
		id = toId(id);
		var state = id !== null ? registry[id] : null;
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

		// Whether this popup was actually displayed before this close: a popup
		// closed while still animating in never reached 'open'.
		var wasShown = state.reachedOpen;
		state.reachedOpen = false;

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
			clearTransitionClasses(root);
			root.setAttribute('aria-hidden', 'true');
			root.setAttribute('hidden', '');

			stopMedia(state.content);

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
					new CustomEvent('elemacy-popup:closed', { detail: { id: id, wasShown: wasShown } })
				);
			} catch (error) {
				/* CustomEvent unsupported — ignore */
			}
		};

		var animOut = animationName(display, 'out');
		if (animOut) {
			// .elemacy-popup.is-animating supplies animation-duration/timing
			// for both directions' keyframes (popups.css); finishOpen()
			// removes it once the open transition settles, so it must be
			// added again here rather than assumed still present.
			clearTransitionClasses(root);
			root.classList.add('is-closing', 'is-animating', 'elemacy-popup--out-' + animOut);
			awaitTransitionEnd(state, token, finishClose);
		} else {
			finishClose();
		}
	}

	// Permanently retires a popup instance: idempotent (a second call on an
	// already-destroyed instance is a safe no-op), releases the lifecycle
	// adapter's own record of its hydrated subtree (see elementor-
	// lifecycle.js's release()), drops its click-trigger selectors so the
	// document-level delegated listener no longer matches anything for it, and
	// releases whatever its triggers set up (see api.onCleanup).
	function destroy(id) {
		id = toId(id);
		var state = id !== null ? registry[id] : null;
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
		runCleanups(state);

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
			clearTransitionClasses(root);
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

	// Releases the listeners and timers a popup's triggers set up. Run once,
	// when the popup opens automatically (no automatic trigger can open it
	// again this page view) or is destroyed.
	function runCleanups(state) {
		var cleanups = state.cleanups;
		state.cleanups = [];
		cleanups.forEach(function (fn) {
			try {
				fn();
			} catch (error) {
				reportOnce('cleanup for popup ' + state.config.id + ' threw', error);
			}
		});
	}

	/**
	 * Open initiated by a trigger (respects the rules); records the show.
	 *
	 * `source` is 'auto' for page load and every extension trigger — a popup
	 * opens that way at most once per page view — or 'click' for its click
	 * selectors, which may open it again.
	 */
	function triggerOpen(id, source) {
		id = toId(id);
		var state = id !== null ? registry[id] : null;
		if (!state || state.phase === 'destroyed' || isActive(state.phase)) {
			return;
		}
		if (source === 'auto' && state.autoShown) {
			return;
		}
		if (!canShow(state.config)) {
			return;
		}
		recordShown(state.config);
		if (source === 'auto') {
			state.autoShown = true;
			runCleanups(state);
		}
		open(id);
	}

	function setupPageLoad(state, params) {
		var delay = (params && params.delay ? parseFloat(params.delay) : 0) * 1000;
		var timer = window.setTimeout(function () {
			triggerOpen(state.config.id, 'auto');
		}, delay);
		addCleanup(state, function () {
			window.clearTimeout(timer);
		});
	}

	function setupClickTrigger(state, params) {
		var selector = params && params.selector ? String(params.selector) : '';
		if (selector) {
			state.clickSelectors.push(selector);
		}
	}

	function addCleanup(state, fn) {
		if (state.autoShown || state.phase === 'destroyed') {
			// Nothing left to release later: the trigger can no longer fire.
			fn();
			return;
		}
		state.cleanups.push(fn);
	}

	function setupTriggers(config) {
		var state = registry[config.id];

		// No trigger configured: show on page load by default so the element
		// actually appears. Topbars, floating elements and banners are
		// persistent UI; popups default to showing on load. Authors add explicit
		// triggers (scroll, click, exit-intent…) to refine this.
		if (!config.triggers || !config.triggers.length) {
			setupPageLoad(state, {});
			return;
		}

		var triggerApi = {
			open: function () {
				triggerOpen(config.id, 'auto');
			},
			onCleanup: function (fn) {
				if (typeof fn === 'function') {
					addCleanup(state, fn);
				}
			}
		};

		config.triggers.forEach(function (trigger) {
			// Each trigger's PHP to_js_config() emits a FLAT object
			// ({type, ...params}), so the params live on the trigger itself —
			// not under a nested `params` key.
			var params = trigger;
			try {
				switch (trigger.type) {
					case 'page_load':
						setupPageLoad(state, params);
						break;
					case 'click':
						setupClickTrigger(state, params);
						break;
					default:
						// Pro triggers (scroll, exit-intent, inactivity, …) register
						// a handler via window.ElemacyPopups.registerTrigger.
						if (triggerHandlers[trigger.type]) {
							triggerHandlers[trigger.type](config.id, params, triggerApi);
						}
						break;
				}
			} catch (error) {
				// One broken trigger leaves this popup without that trigger only.
				reportOnce('trigger "' + trigger.type + '" of popup ' + config.id + ' threw', error);
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Delegated document-level listeners (open/close).                 */
	/* ---------------------------------------------------------------- */

	function closest(el, selector) {
		if (!el || el.nodeType !== 1) {
			return null;
		}
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

	// A click trigger on a link must not follow it when the link goes nowhere
	// (empty, '#', javascript:) or is a popup link; any real destination keeps
	// its normal behavior. The link followed is the one under the click: a
	// selector often names a widget's wrapper (an Elementor button's class sits
	// on the wrapper around its <a>), not the link itself.
	function suppressDeadLink(event, matched) {
		var link = closest(event.target, 'a[href]') || closest(matched, 'a[href]');
		if (!link) {
			return;
		}
		var href = (link.getAttribute('href') || '').replace(/^\s+|\s+$/g, '');
		if (href === '' || href === '#' || /^javascript:/i.test(href) || href.indexOf(POPUP_HREF_PREFIX) === 0) {
			event.preventDefault();
		}
	}

	function handleClick(event) {
		var target = event.target;

		// 1. Open: a link to #elemacy-popup-{id}, or [data-elemacy-popup-open].
		//    Explicit opens skip the rules and frequency bookkeeping.
		var link = closest(target, 'a[href]');
		var href = link ? (link.getAttribute('href') || '').replace(/^\s+|\s+$/g, '') : '';
		var linkOpen = href.match(OPEN_HREF);
		if (linkOpen) {
			event.preventDefault();
			open(toId(linkOpen[1]));
			return;
		}
		var openEl = closest(target, '[data-elemacy-popup-open]');
		if (openEl) {
			var openId = toId(openEl.getAttribute('data-elemacy-popup-open'));
			if (openId !== null) {
				event.preventDefault();
				open(openId);
				return;
			}
		}

		// 2. Close: a link to #elemacy-popup-close, .elemacy-close-popup or
		//    [data-elemacy-popup-close], inside a popup.
		var closeEl = href === CLOSE_HREF
			? link
			: (closest(target, '.elemacy-close-popup') || closest(target, '[data-elemacy-popup-close]'));
		if (closeEl) {
			var rootForClose = closest(closeEl, '[data-elemacy-popup-id]');
			if (rootForClose) {
				event.preventDefault();
				close(rootForClose.getAttribute('data-elemacy-popup-id'));
			} else if (href === CLOSE_HREF) {
				event.preventDefault();
			}
			return;
		}

		// Any other popup link (an id that is not a number) goes nowhere.
		if (href.indexOf(POPUP_HREF_PREFIX) === 0) {
			event.preventDefault();
			return;
		}

		// 3. Per-popup click-trigger selectors (a popup may have several click
		//    triggers; any matching selector opens it).
		Object.keys(registry).forEach(function (id) {
			var state = registry[id];
			for (var s = 0; s < state.clickSelectors.length; s++) {
				var matched = matchesSelector(target, state.clickSelectors[s]);
				if (matched) {
					suppressDeadLink(event, matched);
					triggerOpen(id, 'click');
					break;
				}
			}
		});

		// 4. Overlay click close.
		if (target.hasAttribute && target.hasAttribute('data-elemacy-overlay')) {
			var overlayRoot = closest(target, '[data-elemacy-popup-id]');
			if (overlayRoot) {
				var overlayId = toId(overlayRoot.getAttribute('data-elemacy-popup-id'));
				var overlayState = overlayId !== null ? registry[overlayId] : null;
				if (overlayState && overlayState.config.display && overlayState.config.display.close &&
					overlayState.config.display.close.on_overlay_click) {
					close(overlayId);
				}
			}
		}
	}

	function topModalState() {
		var topId = ModalStack.top();
		var topState = topId !== null ? registry[topId] : null;
		return topState && topState.content ? topState : null;
	}

	function setupDelegation() {
		document.addEventListener('click', function (event) {
			try {
				handleClick(event);
			} catch (error) {
				reportOnce('click handler threw', error);
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
						close(id);
					}
				});
				return;
			}

			if (event.key === 'Tab' || event.keyCode === 9) {
				// Only the topmost modal traps Tab; non-modal bars and any
				// modal buried under it leave page focus alone.
				var openState = topModalState();
				if (!openState) {
					return;
				}
				var focusables = getFocusable(openState.content);
				if (!focusables.length) {
					return;
				}
				var first = focusables[0];
				var last = focusables[focusables.length - 1];
				var active = document.activeElement;
				if (!openState.content.contains(active)) {
					// Focus has left the modal (a programmatic focus, a click
					// outside): Tab re-enters it instead of walking the page.
					event.preventDefault();
					(event.shiftKey ? last : first).focus();
				} else if (event.shiftKey && active === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && active === last) {
					event.preventDefault();
					first.focus();
				}
			}
		});

		// Focus that lands outside the topmost modal while it is open is brought
		// back into it. Not `inert` on the page: that would also lock out fixed
		// headers and cookie banners that are meant to stay usable.
		var pulling = false;
		document.addEventListener('focusin', function (event) {
			if (pulling) {
				return;
			}
			var openState = topModalState();
			if (!openState || modalContains(openState, event.target)) {
				return;
			}
			pulling = true;
			try {
				focusInside(openState);
			} finally {
				pulling = false;
			}
		});
	}

	/* ---------------------------------------------------------------- */
	/* Init.                                                             */
	/* ---------------------------------------------------------------- */

	// A top bar the server printed after the page (the theme never calls
	// wp_body_open) goes back to the top of the document.
	function relocate(root) {
		if (root.getAttribute('data-elemacy-relocate') === 'top' && document.body && document.body.firstChild !== root) {
			document.body.insertBefore(root, document.body.firstChild);
		}
	}

	// A top bar the server printed already open (see PopupRenderer): take it
	// over as if the engine had opened it, without touching its DOM.
	function adopt(state) {
		var root = state.root;
		var config = state.config;
		var display = config.display || {};

		state.content = root.querySelector('.elemacy-popup__box') || root;
		state.hydrated = true;
		state.widgetsHydrated = true; // Elementor's own frontend init handles page DOM.
		state.autoShown = true;
		applyRootClasses(state);

		if (display.z_index) {
			root.style.zIndex = String(display.z_index);
		}

		recordShown(config);
		finishOpen(state, ++state.token);
	}

	function register(config) {
		var root = getRoot(config.id);
		if (!root) {
			return;
		}

		relocate(root);

		var state = registry[config.id] = {
			config: config,
			root: root,
			hydrated: false,
			widgetsHydrated: false,
			phase: 'closed',
			token: 0,
			transitionTimer: null,
			transitionListener: null,
			content: null,
			overlay: null,
			clickSelectors: [],
			autoCloseTimer: null,
			lastFocused: null,
			needs: missingHandlers(config),
			armed: false,
			autoShown: false,
			reachedOpen: false,
			cleanups: []
		};

		if (root.hasAttribute('data-elemacy-prerendered')) {
			state.phase = 'open';
			state.armed = true;
			adopt(state);
			return;
		}

		if (isReady(state)) {
			arm(state);
		}
	}

	function warnAboutMissingHandlers() {
		Object.keys(registry).forEach(function (id) {
			var state = registry[id];
			if (state.armed || state.phase === 'destroyed') {
				return;
			}
			var types = Object.keys(state.needs.triggers).map(function (type) {
				return 'trigger "' + type + '"';
			}).concat(Object.keys(state.needs.rules).map(function (type) {
				return 'rule "' + type + '"';
			}));
			if (window.console && window.console.warn) {
				window.console.warn('Elemacy Popups: popup ' + id + ' is waiting for ' + types.join(', ') + ' and will not open until it is registered.');
			}
		});
	}

	function init() {
		if (initialized) {
			return;
		}

		var data = window.elemacyPopups;
		if (!data || !data.popups || !data.popups.length) {
			return;
		}

		initialized = true;
		debug = !!data.debug;

		// First, so the close controls work whatever happens below.
		setupDelegation();

		data.popups.forEach(function (config) {
			try {
				register(config);
			} catch (error) {
				reportOnce('could not set up popup ' + (config && config.id), error);
			}
		});

		if (debug) {
			// Late scripts get until the page has loaded to register.
			var warn = function () {
				window.setTimeout(warnAboutMissingHandlers, 0);
			};
			if (document.readyState === 'complete') {
				warn();
			} else {
				window.addEventListener('load', warn);
			}
		}
	}

	/* ---------------------------------------------------------------- */
	/* Public API + bootstrapping.                                      */
	/* ---------------------------------------------------------------- */

	var api = window.ElemacyPopups = {
		open: open,
		close: close,
		closeAll: closeAll,
		destroy: destroy,
		registerTrigger: function (type, fn) {
			triggerHandlers[type] = fn;
			armWaiting('triggers', type);
		},
		registerRule: function (type, fn) {
			ruleHandlers[type] = fn;
			armWaiting('rules', type);
		}
	};

	// Extension queue: scripts that cannot rely on load order push a function
	// that receives the API, before or after this file runs. Whatever was
	// queued before is called now, once; later pushes are called at once.
	(function () {
		var queue = window.elemacyPopupsExtensions;
		var pending = Array.isArray(queue) ? queue.slice() : [];
		var run = function (fn) {
			if (typeof fn !== 'function') {
				return;
			}
			try {
				fn(api);
			} catch (error) {
				reportOnce('an extension threw while registering', error);
			}
		};

		if (!Array.isArray(queue)) {
			queue = window.elemacyPopupsExtensions = [];
		}
		queue.length = 0;
		queue.push = function () {
			for (var i = 0; i < arguments.length; i++) {
				run(arguments[i]);
			}
			return queue.length;
		};
		pending.forEach(run);
	})();

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
