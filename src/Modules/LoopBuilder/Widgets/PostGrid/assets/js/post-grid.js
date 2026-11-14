/**
 * Post Grid: reuses the loop list's pagination, and lays out Masonry.
 *
 * Masonry keeps a CSS grid, so items stay in reading order, left to right. Each
 * card spans as many one-pixel rows as its height needs. Without this script
 * the list simply stays a regular grid.
 */
(function ($) {
    'use strict';

    var READY_CLASS = 'elemacy-post-grid--masonry-ready';

    function rowSpan(card, gap) {
        return Math.ceil((card.getBoundingClientRect().height + gap) / (1 + gap));
    }

    function layout(list) {
        var gap = parseFloat(window.getComputedStyle(list).rowGap) || 0;

        Array.prototype.forEach.call(list.querySelectorAll('.elemacy-post-card'), function (card) {
            card.style.gridRowEnd = 'span ' + rowSpan(card, gap);
        });
    }

    function initMasonry($scope) {
        var list = $scope.find('.elemacy-post-grid--masonry')[0];

        if (!list || !window.ResizeObserver || !window.MutationObserver) {
            return;
        }

        var frame = 0;
        var schedule = function () {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(function () {
                layout(list);
            });
        };
        var resize = new window.ResizeObserver(schedule);
        var observeCards = function () {
            Array.prototype.forEach.call(list.querySelectorAll('.elemacy-post-card'), function (card) {
                resize.observe(card);
            });
        };

        list.classList.add(READY_CLASS);
        observeCards();
        schedule();

        // Load More appends and AJAX pagination replaces cards here.
        new window.MutationObserver(function () {
            observeCards();
            schedule();
        }).observe(list, { childList: true });
    }

    function init($scope) {
        window.elemacyLoop.pagination($scope, $);
        window.elemacyLoop.incremental($scope);
        initMasonry($scope);
    }

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-post-grid.default', init);
    });
})(jQuery);
