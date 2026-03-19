(function ($) {
    "use strict";

    const ElemacyNavHandler = function ($scope, $) {
        // Scope the elements to this specific widget instance only
        const $nav = $scope.find('.elemacy-nav');
        const $toggle = $scope.find('.elemacy-nav__toggle, .elemacy-nav__toggle-close');
        const $menu = $scope.find('.elemacy-nav__menu');

        // Toggle Click Event
        $toggle.on('click', function (e) {
            e.preventDefault();

            const isOpen = $nav.hasClass('is-open');

            // Toggle the class on the main wrapper
            $nav.toggleClass('is-open');

            // Accessibility: Update ARIA states
            $toggle.attr('aria-expanded', !isOpen);
        });

        // Close menu if clicking outside (Optional but recommended for UX)
        $(document).on('click', function (e) {
            if ($nav.hasClass('is-open') && !$nav.is(e.target) && $nav.has(e.target).length === 0) {
                $nav.removeClass('is-open');
                $toggle.attr('aria-expanded', 'false');
            }
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy_nav_menu.default', ElemacyNavHandler);
    });

})(jQuery);