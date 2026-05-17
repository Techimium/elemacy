(function ($) {
    "use strict";

    const ElemacyNavHandler = function ($scope) {
        const $nav    = $scope.find('.elemacy-nav');
        const $toggle = $scope.find('.elemacy-nav__toggle, .elemacy-nav__toggle-close');

        // ── Close helper ─────────────────────────────────────────────────────
        // Keeps .is-open on the nav while the overlay fades/slides out so that
        // the :not(.is-open) desktop selectors never fire during the transition.
        let closeTimer;

        function closeMenu() {
            clearTimeout(closeTimer);
            $nav.addClass('elemacy-nav--closing');
            closeTimer = setTimeout(function () {
                $nav.removeClass('is-open elemacy-nav--closing');
                $toggle.attr('aria-expanded', 'false');
            }, 300); // must match CSS transition-duration
        }

        // ── Open ─────────────────────────────────────────────────────────────
        function openMenu() {
            clearTimeout(closeTimer);
            $nav.removeClass('elemacy-nav--closing').addClass('is-open');
            $toggle.attr('aria-expanded', 'true');
        }

        // ── Toggle button ────────────────────────────────────────────────────
        $toggle.on('click', function (e) {
            e.preventDefault();
            $nav.hasClass('is-open') ? closeMenu() : openMenu();
        });

        // ── Backdrop click closes slide panel overlays ────────────────────────
        $scope.find('.elemacy-nav__backdrop').on('click', function () {
            if ($nav.hasClass('is-open')) {
                closeMenu();
            }
        });

        // ── Fullscreen overlay: click directly on backdrop (the menu box) ────
        $scope.find('.elemacy-nav__menu').on('click', function (e) {
            if ($nav.hasClass('is-open') && e.target === this) {
                closeMenu();
            }
        });

        // ── Close on outside click (handles edge cases) ──────────────────────
        $(document).on('click.elemacy-nav-' + $scope.data('id'), function (e) {
            if (
                $nav.hasClass('is-open') &&
                !$nav.is(e.target) &&
                $nav.has(e.target).length === 0
            ) {
                closeMenu();
            }
        });

        // ── Close on Escape ──────────────────────────────────────────────────
        $(document).on('keydown.elemacy-nav-' + $scope.data('id'), function (e) {
            if (e.key === 'Escape' && $nav.hasClass('is-open')) {
                closeMenu();
                $scope.find('.elemacy-nav__toggle').first().trigger('focus');
            }
        });

        // ── Submenu collision detection ──────────────────────────────────────
        // The submenu is display:none when hidden, so getBoundingClientRect()
        // returns all zeros. Briefly render it invisible to measure its bounds,
        // apply overflow classes, then let CSS :hover reveal it.
        $scope.find('.elemacy-nav__item--has-children').on('mouseenter', function () {
            const $submenu = $(this).children('.elemacy-nav__submenu');
            if (!$submenu.length) return;

            $submenu.removeClass('elemacy-is-drop-left elemacy-is-drop-up');

            $submenu.css({ display: 'flex', visibility: 'hidden', animation: 'none', transition: 'none' });
            const rect = $submenu[0].getBoundingClientRect();
            $submenu.css({ display: '', visibility: '', animation: '', transition: '' });

            const winW = window.innerWidth  || document.documentElement.clientWidth;
            const winH = window.innerHeight || document.documentElement.clientHeight;

            if (rect.right  > winW) $submenu.addClass('elemacy-is-drop-left');
            if (rect.bottom > winH) $submenu.addClass('elemacy-is-drop-up');
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/elemacy_nav_menu.default',
            ElemacyNavHandler
        );
    });

})(jQuery);
