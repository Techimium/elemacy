/**
 * Accordion behavior for the Elemacy ACF Accordion widget.
 *
 * Because the widget is registered under its own name, Elementor's default
 * accordion handler does not bind to it. This script provides equivalent
 * click + keyboard interaction targeting the rendered DOM structure.
 */
(function ($) {
    'use strict';

    var ACTIVE_CLASS = 'elementor-active';

    function ElemacyAcfAccordion($scope) {
        var $accordion = $scope.find('.elementor-accordion').first();
        if (!$accordion.length) {
            return;
        }

        function closeItem($item) {
            $item.removeClass(ACTIVE_CLASS);
            $item.find('.elementor-tab-title')
                .attr('aria-expanded', 'false');
            $item.find('.elementor-accordion-icon-closed').show();
            $item.find('.elementor-accordion-icon-opened').hide();
            $item.find('.elementor-tab-content').stop(true, true).slideUp();
        }

        function openItem($item) {
            $item.addClass(ACTIVE_CLASS);
            $item.find('.elementor-tab-title')
                .attr('aria-expanded', 'true');
            $item.find('.elementor-accordion-icon-closed').hide();
            $item.find('.elementor-accordion-icon-opened').show();
            $item.find('.elementor-tab-content').stop(true, true).slideDown();
        }

        function toggle($title) {
            var $item = $title.closest('.elementor-accordion-item');

            if ($item.hasClass(ACTIVE_CLASS)) {
                closeItem($item);
                return;
            }

            $accordion.find('.elementor-accordion-item.' + ACTIVE_CLASS)
                .each(function () {
                    closeItem($(this));
                });

            openItem($item);
        }

        $accordion.on('click', '.elementor-tab-title', function (e) {
            e.preventDefault();
            toggle($(e.currentTarget));
        });

        $accordion.on('keydown', '.elementor-tab-title', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle($(e.currentTarget));
            }
        });
    }

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/elemacy-acf-accordion.default',
            ElemacyAcfAccordion
        );
    });
}(jQuery));
