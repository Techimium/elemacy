(function ($) {
    'use strict';

    /**
     * Page-URL pagination (Current Query).
     *
     * A Current Query loop's pages are the host page's own paginated URLs, so
     * the browser fetches the clicked link's URL, finds each page-mode loop in
     * the returned document by its stable identity, and swaps it in place.
     * WordPress builds every page's query itself: nothing is rebuilt or sent
     * to a loop endpoint. Any failure falls back to a normal page load.
     */
    var PageMode = (function () {
        var SELECTOR = '.elemacy-ajax-pagination[data-elemacy-loop-mode="page"]';
        var controller = null;
        var currentUrl = null;
        var bound = false;

        function stripHash(url) {
            return String(url).split('#')[0];
        }

        function sameOrigin(url) {
            try {
                return new URL(url, window.location.href).origin === window.location.origin;
            } catch (e) {
                return false;
            }
        }

        function supported() {
            return !!(window.fetch && window.DOMParser && window.URL && window.history && window.history.pushState);
        }

        function containers(root) {
            return Array.prototype.slice.call(root.querySelectorAll(SELECTOR));
        }

        function findById(root, id) {
            var nodes = containers(root);
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].getAttribute('data-elemacy-loop-id') === id) {
                    return nodes[i];
                }
            }
            return null;
        }

        function setBusy(busy) {
            containers(document).forEach(function (node) {
                if (busy) {
                    node.setAttribute('aria-busy', 'true');
                    node.classList.add('elemacy-is-loading');
                } else {
                    node.removeAttribute('aria-busy');
                    node.classList.remove('elemacy-is-loading');
                }
            });
        }

        // Replaces one live loop's items and pagination with the fetched
        // document's copy of the same loop. False when either side lacks the
        // loop's grid, so the caller falls back to a normal page load.
        function swap(live, fetched) {
            var liveGrid = live.querySelector('.elemacy-loop-grid');
            var fetchedGrid = fetched.querySelector('.elemacy-loop-grid');
            if (!liveGrid || !fetchedGrid) {
                return false;
            }

            $(liveGrid).html(fetchedGrid.innerHTML);

            var livePagination = live.querySelector('.elemacy-pagination');
            var fetchedPagination = fetched.querySelector('.elemacy-pagination');
            if (fetchedPagination) {
                var copy = document.importNode(fetchedPagination, true);
                if (livePagination) {
                    livePagination.parentNode.replaceChild(copy, livePagination);
                } else {
                    live.appendChild(copy);
                }
            } else if (livePagination) {
                livePagination.parentNode.removeChild(livePagination);
            }

            // The grid node is the same across swaps, so release() first: the
            // lifecycle adapter otherwise skips an already-seen subtree.
            if (window.ElemacyElementorLifecycle) {
                window.ElemacyElementorLifecycle.release(liveGrid);
                window.ElemacyElementorLifecycle.hydrate(liveGrid);
            }

            return true;
        }

        function load(url, options) {
            options = options || {};

            if (controller) {
                controller.abort();
            }
            controller = window.AbortController ? new AbortController() : null;
            var mine = controller;

            setBusy(true);

            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
                signal: mine ? mine.signal : undefined
            }).then(function (response) {
                // A failing status (404 past the last page) or a redirect
                // off-site is not something to splice in: let the browser load it.
                if (!response.ok || !sameOrigin(response.url || url)) {
                    throw new Error('elemacy-loop-fallback');
                }
                return response.text().then(function (html) {
                    return { html: html, url: response.url || url };
                });
            }).then(function (result) {
                // A newer request took over while this one was in flight.
                if (mine !== controller) {
                    return;
                }
                var doc = new DOMParser().parseFromString(result.html, 'text/html');
                var lives = containers(document);
                var swapped = [];

                for (var i = 0; i < lives.length; i++) {
                    var fetched = findById(doc, lives[i].getAttribute('data-elemacy-loop-id'));
                    if (!fetched || !swap(lives[i], fetched)) {
                        throw new Error('elemacy-loop-fallback');
                    }
                    swapped.push(lives[i]);
                }

                if (options.push !== false && stripHash(result.url) !== stripHash(window.location.href)) {
                    window.history.pushState({ elemacyLoop: true }, '', result.url);
                }
                currentUrl = stripHash(window.location.href);

                if (doc.title) {
                    document.title = doc.title;
                }

                if (options.focus && swapped.length) {
                    var grid = swapped[0].querySelector('.elemacy-loop-grid');
                    if (grid) {
                        grid.setAttribute('tabindex', '-1');
                        grid.focus({ preventScroll: true });
                    }
                    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    var top = swapped[0].getBoundingClientRect().top + window.pageYOffset - 80;
                    window.scrollTo({ top: Math.max(0, top), behavior: reduce ? 'auto' : 'smooth' });
                }

                setBusy(false);
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    // Superseded by a newer request, which owns the busy state.
                    return;
                }
                setBusy(false);
                window.location.assign(url);
            });
        }

        function onClick(event) {
            // Leave new-tab / new-window gestures to the browser.
            if (event.which > 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            var href = this.href;
            if (!href || !sameOrigin(href)) {
                return;
            }

            event.preventDefault();
            load(href, { push: true, focus: true });
        }

        function bindHistory() {
            if (bound) {
                return;
            }
            bound = true;
            currentUrl = stripHash(window.location.href);

            window.addEventListener('popstate', function () {
                var url = stripHash(window.location.href);
                if (url === currentUrl || !containers(document).length) {
                    return;
                }
                load(window.location.href, { push: false, focus: false });
            });
        }

        return {
            init: function ($container) {
                // Without fetch/History the links simply navigate; in the
                // editor preview they must not be hijacked.
                if (!supported() || elementorFrontend.isEditMode()) {
                    return;
                }
                bindHistory();
                $container.off('click.elemacyLoopPage').on('click.elemacyLoopPage', '.elemacy-pagination a.page-numbers', onClick);
            }
        };
    })();

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

        // Current Query paginates by the page's own URLs, not the loop endpoint.
        if ($targetContainer.attr('data-elemacy-loop-mode') === 'page') {
            PageMode.init($targetContainer);
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
