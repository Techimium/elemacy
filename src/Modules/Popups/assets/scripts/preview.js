/**
 * Elemacy Popups — Elementor editor preview helper.
 *
 * Ensures a real `.elemacy-popup__close` button exists inside the popup's
 * content wrapper in the editor preview, so the document's Close Button style
 * controls (and their :hover / :active states) preview exactly as on the site.
 *
 * The close button is runtime chrome — not part of the Elementor document's
 * content — so it has to be added to the editor preview by JS (Elementor Pro's
 * popup editor does the same). We re-add it via Elementor's own
 * `frontend/element_ready` render hook, which fires whenever the preview
 * (re)renders, instead of watching the whole DOM. Visibility and styling come
 * entirely from the controls' generated CSS.
 */
(function (window, document) {
	'use strict';

	function ensure(box) {
		if (!box || box.querySelector('.elemacy-popup__close')) {
			return;
		}

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'elemacy-popup__close elemacy-close-popup';
		btn.setAttribute('aria-label', 'Close');
		btn.setAttribute('tabindex', '-1');
		btn.innerHTML = '&times;';
		box.appendChild(btn);
	}

	function run() {
		var boxes = document.querySelectorAll('.elementor[data-elementor-type="elemacy_popup"]');
		Array.prototype.forEach.call(boxes, ensure);
	}

	function init() {
		run();

		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', run);
		}
	}

	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', init);
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(window, document);
