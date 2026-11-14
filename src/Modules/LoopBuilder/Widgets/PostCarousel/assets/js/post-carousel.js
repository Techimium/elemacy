/**
 * Post Carousel: slides its cards with the loop carousel's own handler.
 */
(function ($) {
    'use strict';

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-post-carousel.default', function ($scope) {
            window.elemacyLoop.carousel($scope, $);
        });
    });
})(jQuery);
