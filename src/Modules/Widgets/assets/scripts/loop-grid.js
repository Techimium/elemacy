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
        var requestRaw = $targetContainer.attr('data-elemacy-loop-request');
        var paginateBase = $targetContainer.attr('data-elemacy-loop-base') || '';

        if (!requestRaw || typeof window.elemacy === 'undefined') {
            return;
        }

        var baseRequest;
        try {
            baseRequest = JSON.parse(requestRaw);
        } catch (e) {
            return;
        }

        // A refresh-required/invalid response is stable and bounded: show a
        // notice once and stop intercepting clicks — never an automatic
        // reload or a repeated/fallback query with the stale request.
        function showStaleNotice() {
            $targetContainer.off('click.elemacyLoopGrid');
            $targetContainer.find('.elemacy-loop-stale-notice').remove();
            $targetContainer.append(
                $('<p class="elemacy-loop-stale-notice"></p>').text('This content has changed. Refresh the page to continue.')
            );
        }

        // Delegate pagination clicks
        $targetContainer.on('click.elemacyLoopGrid', '.elemacy-pagination a.page-numbers', function (e) {
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

            var requestPayload = $.extend({}, baseRequest, { page: paged });

            $.ajax({
                url: window.elemacy.ajax_url,
                type: 'POST',
                data: {
                    action: 'elemacy_widgets_loop_grid_pagination',
                    _wpnonce: window.elemacy.nonce,
                    request: JSON.stringify(requestPayload),
                    paginate_base: paginateBase
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

                        // Re-initialize any Elementor elements inside the grid that just
                        // loaded, through the shared lifecycle adapter rather than a second
                        // copy of this widget-handler-initialization loop. $loopGrid[0] is the SAME container node across every
                        // pagination click (.html() replaces its inner content, not the
                        // node itself), so release() first is required: without it, the
                        // adapter's own "never initialize an already-seen subtree twice"
                        // guard (needed for a reopened popup) would silently skip
                        // hydrating every pagination response after the first.
                        if (window.ElemacyElementorLifecycle) {
                            window.ElemacyElementorLifecycle.release($loopGrid[0]);
                            window.ElemacyElementorLifecycle.hydrate($loopGrid[0]);
                        }

                        // Smooth scroll to top of the grid
                        $('html, body').animate({
                            scrollTop: $scope.offset().top - 80 // slight offset for header
                        }, 500);

                    } else {
                        showStaleNotice();
                        if (elementorFrontend.isEditMode()) {
                            console.error('Elemacy Loop AJAX Error:', response.data);
                        } else {
                            console.error('Elemacy: loop pagination failed.');
                        }
                    }
                },
                error: function (xhr, status, error) {
                    if (elementorFrontend.isEditMode()) {
                        console.error('Elemacy Loop AJAX Request Failed:', error);
                    } else {
                        console.error('Elemacy: loop pagination request failed.');
                    }
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
