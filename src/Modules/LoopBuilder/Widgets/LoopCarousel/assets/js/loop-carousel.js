(function ($) {
    'use strict';

    /**
     * Loop Carousel Frontend Script
     * Initialize Swiper slider for the loop carousel.
     */
    const WidgetLoopCarouselHandler = function ($scope, $) {
        const $carouselContainer = $scope.find('.elemacy-loop-carousel');

        if (!$carouselContainer.length) {
            return;
        }

        const isEditMode = elementorFrontend.isEditMode();
        let elementSettings = {};

        if (isEditMode && !!$scope.data('model-cid')) {
            const model = elementorFrontend.config.elements.data[$scope.data('model-cid')];
            if (model) {
                elementSettings = model.attributes;
            }
        } else {
            const dataSettings = $scope.attr('data-settings');
            if (dataSettings) {
                try {
                    elementSettings = JSON.parse(dataSettings);
                } catch (e) { }
            }
        }

        const slidesPerView = parseInt(elementSettings.slides_per_view) || 3;
        const slidesToScroll = parseInt(elementSettings.slides_to_scroll) || 1;
        const autoplay = elementSettings.autoplay === 'yes';
        const autoplaySpeed = parseInt(elementSettings.autoplay_speed) || 5000;
        const pauseOnHover = elementSettings.pause_on_hover === 'yes';
        const loop = elementSettings.loop === 'yes';
        const spaceBetween = elementSettings.space_between?.size !== undefined ? parseInt(elementSettings.space_between.size) : 20;
        const transitionSpeed = parseInt(elementSettings.transition_speed) || 500;

        const swiperOptions = {
            slidesPerView: slidesPerView,
            slidesPerGroup: slidesToScroll,
            spaceBetween: spaceBetween,
            loop: loop,
            speed: transitionSpeed,
            breakpoints: {
                320: {
                    slidesPerView: parseInt(elementSettings.slides_per_view_mobile) || 1,
                    slidesPerGroup: parseInt(elementSettings.slides_to_scroll_mobile) || 1,
                    spaceBetween: elementSettings.space_between_mobile?.size !== undefined ? parseInt(elementSettings.space_between_mobile.size) : spaceBetween
                },
                768: {
                    slidesPerView: parseInt(elementSettings.slides_per_view_tablet) || 2,
                    slidesPerGroup: parseInt(elementSettings.slides_to_scroll_tablet) || 1,
                    spaceBetween: elementSettings.space_between_tablet?.size !== undefined ? parseInt(elementSettings.space_between_tablet.size) : spaceBetween
                },
                1024: {
                    slidesPerView: slidesPerView,
                    slidesPerGroup: slidesToScroll,
                    spaceBetween: spaceBetween
                }
            }
        };

        if (autoplay) {
            swiperOptions.autoplay = {
                delay: autoplaySpeed,
                disableOnInteraction: false,
                pauseOnMouseEnter: pauseOnHover
            };
        }

        // Navigation
        const $nextBtn = $scope.find('.elemacy-swiper-button-next');
        const $prevBtn = $scope.find('.elemacy-swiper-button-prev');
        if ($nextBtn.length && $prevBtn.length) {
            swiperOptions.navigation = {
                nextEl: $nextBtn[0],
                prevEl: $prevBtn[0],
            };
        }

        // Pagination
        const $pagination = $scope.find('.swiper-pagination');
        if ($pagination.length) {
            swiperOptions.pagination = {
                el: $pagination[0],
                clickable: true,
                type: 'bullets',
            };
        }

        // Initialize Swiper using Elementor's native utility
        const initSwiper = function () {
            if ('undefined' === typeof Swiper) {
                // Fetch Elementor Swiper async utility if Swiper is not globally defined.
                // Works in Elementor 3.3+
                const asyncSwiper = elementorFrontend.utils.swiper;
                new asyncSwiper($carouselContainer, swiperOptions).then(function (newSwiperInstance) {
                    $carouselContainer.data('swiper', newSwiperInstance);
                });
            } else {
                const swiperInstance = new Swiper($carouselContainer[0], swiperOptions);
                $carouselContainer.data('swiper', swiperInstance);
            }
        };

        initSwiper();
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-loop-carousel.default', WidgetLoopCarouselHandler);
    });
})(jQuery);
