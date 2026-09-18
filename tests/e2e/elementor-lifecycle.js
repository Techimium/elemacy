/**
 * Elemacy — Elementor frontend lifecycle adapter (design.md D9, task 9.5).
 *
 * Initializes a subtree of Elementor-built markup that was inserted into the
 * page outside Elementor's own render pipeline — a hydrated popup
 * (engine.js) or AJAX-loaded Loop Grid pagination content (loop-grid.js), the
 * two supported insertion endpoints this file centralizes. Elementor never
 * discovers such content on its own — its widget handlers only ever attach
 * when Elementor itself prints the markup during its normal page render.
 *
 * Public, longstanding Elementor API used here: elementorFrontend.
 * elementsHandler.runReadyTrigger($element), fired once per `.elementor-
 * element` descendant — the exact mechanism Elementor's own frontend.js
 * uses internally. It requires elementorFrontend.elementsHandler to exist,
 * which frontend.js only constructs immediately before firing
 * 'elementor/frontend/init' — this adapter never calls it before that event
 * has fired at least once.
 *
 * Self-contained IIFE, no bundler; a shared Core-owned script (registered by
 * Core/FrontendScripts.php as `elemacy-elementor-lifecycle`), not a
 * Popups-module file — both engine.js and loop-grid.js declare it as a
 * plain script dependency so neither module duplicates this logic inline
 * (loop-grid.js did exactly that before this task).
 */
(function (window, document) {
	'use strict';

	// Set once Elementor's own frontend has finished constructing
	// elementorFrontend.elementsHandler and fired its readiness event. The
	// listener below is attached the moment this script parses — always
	// before that event can fire, since it only ever fires from inside a
	// jQuery DOM-ready callback, and no synchronous script (this one
	// included) can still be parsing once DOM-ready has occurred. That
	// ordering guarantee is what lets `ready` be trusted later, however
	// late a popup actually opens.
	var ready = false;

	// Subtrees already initialized — never re-initialize the same one twice
	// (Elementor's own frontend keeps no record of this for non-editor
	// content, so double-initializing a widget's handler would be silent
	// and easy to miss, e.g. Swiper re-attaching and desyncing its own DOM).
	var initialized = (typeof window.WeakSet === 'function') ? new window.WeakSet() : null;
	var initializedFallback = [];

	function isInitialized(root) {
		if (initialized) {
			return initialized.has(root);
		}
		return initializedFallback.indexOf(root) !== -1;
	}

	function markInitialized(root) {
		if (initialized) {
			initialized.add(root);
			return;
		}
		initializedFallback.push(root);
	}

	function forget(root) {
		if (initialized) {
			initialized.delete(root);
			return;
		}
		var index = initializedFallback.indexOf(root);
		if (index !== -1) {
			initializedFallback.splice(index, 1);
		}
	}

	function runReadyTriggers(root) {
		if (!window.jQuery || !window.elementorFrontend || !window.elementorFrontend.elementsHandler) {
			return;
		}
		window.jQuery(root).find('.elementor-element').each(function () {
			window.elementorFrontend.elementsHandler.runReadyTrigger(window.jQuery(this));
		});
	}

	/**
	 * Initializes every Elementor widget/element inside $root exactly once,
	 * deferring until Elementor's own frontend has signaled readiness if it
	 * has not yet done so.
	 *
	 * @param {Element} root
	 */
	function hydrate(root) {
		if (!root || isInitialized(root)) {
			return;
		}
		markInitialized(root);

		if (ready) {
			runReadyTriggers(root);
			return;
		}

		if (window.jQuery) {
			window.jQuery(window).one('elementor/frontend/init', function () {
				runReadyTriggers(root);
			});
		}
	}

	/**
	 * Forgets a subtree so a later hydrate() call for it (e.g. after being
	 * removed and reinserted) treats it as new again. Does not — cannot —
	 * tear down any handler Elementor itself constructed: Elementor's own
	 * handler registry only tracks instances for editor-mode content, and
	 * offers no public API to detach one that was attached from outside its
	 * own render pipeline. Released for the same subtree.
	 *
	 * @param {Element} root
	 */
	function release(root) {
		if (root) {
			forget(root);
		}
	}

	function markReady() {
		ready = true;
	}

	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', markReady);
	}

	window.ElemacyElementorLifecycle = {
		hydrate: hydrate,
		release: release
	};
})(window, document);
