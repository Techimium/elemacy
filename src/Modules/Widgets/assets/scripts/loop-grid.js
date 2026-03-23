(function ($) {
    'use strict';

    /**
     * Loop Grid Frontend Script
     * Handles the AJAX pagination logic for the loop grid widget.
     */
    var WidgetLoopGridHandler = function ($scope, $) {
        var $targetContainer = $scope.find('.elemacy-ajax-pagination');

        // If AJAX pagination is not enabled for this widget, do nothing
        if (!$targetContainer.length) {
            return;
        }

        var $loopGrid = $targetContainer.find('.elemacy-loop-grid');
        var settingsRaw = $targetContainer.attr('data-elemacy-loop-settings');

        if (!settingsRaw || typeof window.elemacy === 'undefined') {
            return;
        }

        // Delegate pagination clicks
        $targetContainer.on('click', '.elemacy-pagination a.page-numbers', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var href = $btn.attr('href');
            var paged = 1;

            if (!href) return;

            // Extract page number from URL if possible
            var pageMatch = href.match(/\/page\/(\d+)/);
            if (pageMatch && pageMatch[1]) {
                paged = parseInt(pageMatch[1], 10);
            } else {
                // Try from query string ?paged=
                var pagedMatch = href.match(/(?:[?&])paged=(\d+)/);
                if (pagedMatch && pagedMatch[1]) {
                    paged = parseInt(pagedMatch[1], 10);
                } else {
                    // Try to guess from button text if it's a number (fallback for edge cases)
                    var textNum = parseInt($btn.text(), 10);
                    if (!isNaN(textNum)) {
                        paged = textNum;
                    }
                }
            }

            // Do nothing if we can't figure out the target page
            if (paged <= 0) return;

            // Visual loading state
            $targetContainer.addClass('elemacy-is-loading');
            $loopGrid.css('opacity', '0.5');
            $loopGrid.css('transition', 'opacity 0.3s ease');

            $.ajax({
                url: window.elemacy.ajax_url,
                type: 'POST',
                data: {
                    action: 'elemacy_loop_grid_pagination',
                    _wpnonce: window.elemacy.nonce,
                    paged: paged,
                    settings: settingsRaw
                },
                success: function (response) {
                    if (response.success && response.data) {

                        // Update grid items
                        if (response.data.items) {
                            $loopGrid.html(response.data.items);
                        }

                        // Update or replace pagination HTML
                        var $oldPagination = $targetContainer.find('.elemacy-pagination');
                        if (response.data.pagination) {
                            if ($oldPagination.length) {
                                $oldPagination.replaceWith(response.data.pagination);
                            } else {
                                $targetContainer.append(response.data.pagination);
                            }
                        } else {
                            $oldPagination.remove();
                        }

                        // Re-initialize any Elementor elements inside the grid that just loaded
                        $loopGrid.find('.elementor-element').each(function () {
                            elementorFrontend.elementsHandler.runReadyTrigger($(this));
                        });

                        // Smooth scroll to top of the grid
                        $('html, body').animate({
                            scrollTop: $scope.offset().top - 80 // slight offset for header
                        }, 500);

                    } else {
                        console.error('Elemacy Loop AJAX Error:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Elemacy Loop AJAX Request Failed:', error);
                },
                complete: function () {
                    $targetContainer.removeClass('elemacy-is-loading');
                    $loopGrid.css('opacity', '1');
                }
            });
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-loop-grid.default', WidgetLoopGridHandler);
    });
})(jQuery);
