/**
 * Loop Grid: Load More and Infinite Scroll.
 *
 * Both append the next page's items to the loop instead of replacing it. They
 * share one loader; only the trigger differs (a click, or the loop's end
 * nearing the viewport). The loader reaches the next page through a
 * "transport", chosen by what the server rendered:
 *
 *  - endpoint:  the saved-definition AJAX request (Posts, Terms, Users, ...).
 *  - page URL:  the next page's own URL, fetched and read (Current Query).
 *
 * A transport hands back the same three things, so nothing else depends on
 * which one is in use:  html (the page's items), pagination (the new
 * "next page" link, or '') and next (what to ask for after this page, or null
 * on the last page). The browser never works the next page out itself: the
 * server says what comes next.
 *
 * Without scripting the "next page" link is a plain link to the next page.
 */
(function ($) {
    'use strict';

    var NEXT_LINK = 'a.elemacy-load-more';

    function sameOrigin(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    // ---- Transports --------------------------------------------------------

    /** Pages of a source the saved-definition endpoint serves. */
    function endpointTransport($container) {
        var request;
        try {
            request = JSON.parse($container.attr('data-elemacy-loop-request'));
        } catch (e) {
            return null;
        }
        if (!request || !window.elemacy) {
            return null;
        }

        var paginateBase = $container.attr('data-elemacy-loop-base') || '';
        var renderedPage = parseInt($container.attr('data-elemacy-loop-page'), 10) || 1;

        return {
            // The page after the one rendered, when the server printed a next link.
            first: function () {
                return $container.find(NEXT_LINK).length ? renderedPage + 1 : null;
            },
            load: function (page) {
                return new Promise(function (resolve, reject) {
                    $.ajax({
                        url: window.elemacy.ajax_url,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'elemacy_widgets_loop_grid_pagination',
                            _wpnonce: window.elemacy.nonce,
                            request: JSON.stringify($.extend({}, request, { page: page })),
                            paginate_base: paginateBase
                        }
                    }).done(function (response) {
                        if (!response || !response.success || !response.data) {
                            reject(new Error('endpoint refused the page'));
                            return;
                        }
                        resolve({
                            html: response.data.items || '',
                            pagination: response.data.pagination || '',
                            next: response.data.next_page || null
                        });
                    }).fail(reject);
                });
            }
        };
    }

    /** Pages that are the host page's own paginated URLs (Current Query). */
    function pageUrlTransport($container) {
        var loopId = $container.attr('data-elemacy-loop-id');
        if (!loopId || !window.fetch || !window.DOMParser || !window.URL) {
            return null;
        }

        function nextUrl(root, baseUrl) {
            var link = root.querySelector(NEXT_LINK);
            return link ? new URL(link.getAttribute('href'), baseUrl).href : null;
        }

        function findLoop(doc) {
            var loops = doc.querySelectorAll('.elemacy-ajax-pagination[data-elemacy-loop-mode="page"]');
            for (var i = 0; i < loops.length; i++) {
                if (loops[i].getAttribute('data-elemacy-loop-id') === loopId) {
                    return loops[i];
                }
            }
            return null;
        }

        return {
            first: function () {
                return nextUrl($container[0], window.location.href);
            },
            load: function (url) {
                if (!sameOrigin(url)) {
                    return Promise.reject(new Error('cross-origin page'));
                }
                return fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
                    .then(function (response) {
                        // A failing status or an off-site redirect is not spliced in.
                        if (!response.ok || !sameOrigin(response.url || url)) {
                            throw new Error('unusable page');
                        }
                        return response.text().then(function (text) {
                            return { text: text, url: response.url || url };
                        });
                    })
                    .then(function (page) {
                        var loop = findLoop(new DOMParser().parseFromString(page.text, 'text/html'));
                        var grid = loop && loop.querySelector('.elemacy-loop-grid');
                        if (!grid) {
                            throw new Error('loop not found in the page');
                        }
                        var pagination = loop.querySelector('.elemacy-pagination');
                        return {
                            html: grid.innerHTML,
                            pagination: pagination ? pagination.outerHTML : '',
                            next: nextUrl(loop, page.url)
                        };
                    });
            }
        };
    }

    // ---- Appending ---------------------------------------------------------

    /**
     * Splits a page's HTML into one group per item. A group is the item plus
     * the markup printed just before it (its per-item style), so skipping a
     * repeated item skips its style too. Anything after the last item is a
     * final group with no item.
     */
    function groupByItem(nodes) {
        var groups = [];
        var pending = [];
        nodes.forEach(function (node) {
            pending.push(node);
            if (node.nodeType === 1 && node.hasAttribute('data-elemacy-item')) {
                groups.push({ item: node, nodes: pending });
                pending = [];
            }
        });
        if (pending.length) {
            groups.push({ item: null, nodes: pending });
        }
        return groups;
    }

    // ---- The loader --------------------------------------------------------

    function IncrementalLoop($container, transport, infinite) {
        var grid = $container.find('.elemacy-loop-grid')[0];
        var status = $container.find('.elemacy-load-more-status')[0];
        var end = $container.find('.elemacy-load-more-end')[0];
        var next = transport.first();
        var busy = false;
        var observer = null;

        function text(name) {
            return (status && status.getAttribute('data-' + name + '-text')) || '';
        }

        function setBusy(on) {
            var link = $container.find(NEXT_LINK)[0];
            $container.attr('aria-busy', on ? 'true' : null);
            if (link) {
                if (on) {
                    link.setAttribute('data-label', link.textContent);
                    link.setAttribute('aria-disabled', 'true');
                    link.textContent = text('loading') || link.textContent;
                } else {
                    link.textContent = link.getAttribute('data-label') || link.textContent;
                    link.removeAttribute('aria-disabled');
                }
            }
            if (status) {
                status.textContent = on ? text('loading') : '';
            }
        }

        function watchEnd() {
            if (!observer) {
                return;
            }
            observer.disconnect();
            var nav = $container.children('.elemacy-pagination')[0];
            if (nav) {
                observer.observe(nav);
            }
        }

        function stopWatching() {
            if (observer) {
                observer.disconnect();
                observer = null;
            }
            $container.removeClass('elemacy-infinite-active');
        }

        // Appends the page's items (skipping any already shown) and swaps in
        // the new next-page link. Returns the items actually added.
        function append(result) {
            var shown = {};
            $(grid).children('[data-elemacy-item]').each(function () {
                shown[this.getAttribute('data-elemacy-item')] = true;
            });

            var added = [];
            groupByItem($.parseHTML(result.html, document, true) || []).forEach(function (group) {
                var id = group.item && group.item.getAttribute('data-elemacy-item');
                if (id && shown[id]) {
                    return;
                }
                $(grid).append(group.nodes);
                if (group.item) {
                    shown[id] = true;
                    added.push(group.item);
                }
            });

            $container.children('.elemacy-pagination').remove();
            if (result.pagination) {
                $(grid).after(result.pagination);
            }

            if (window.elemacyElementorLifecycle) {
                added.forEach(function (item) {
                    window.elemacyElementorLifecycle.hydrate(item);
                });
            }

            return added;
        }

        function load(userInitiated) {
            if (busy || next === null) {
                return;
            }

            var link = $container.find(NEXT_LINK)[0];
            var fallbackUrl = link ? link.href : null;
            busy = true;
            setBusy(true);

            transport.load(next).then(function (result) {
                var added = append(result);
                next = result.next;
                busy = false;
                setBusy(false);

                if (status) {
                    status.textContent = text('loaded');
                }
                if (userInitiated && added.length) {
                    added[0].setAttribute('tabindex', '-1');
                    added[0].focus();
                }
                if (next === null) {
                    stopWatching();
                    if (end) {
                        end.hidden = false;
                    }
                } else {
                    watchEnd();
                }
            }).catch(function () {
                busy = false;
                setBusy(false);
                if (userInitiated && fallbackUrl) {
                    // Anything the page can't do in place, the browser can: load that page.
                    window.location.assign(fallbackUrl);
                } else {
                    // An automatic load failed: stop automating and leave the button to the visitor.
                    stopWatching();
                }
            });
        }

        $container.on('click.elemacyLoadMore', NEXT_LINK, function (event) {
            // Leave new-tab / new-window gestures to the browser.
            if (event.which > 1 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();
            load(true);
        });

        if (infinite && 'IntersectionObserver' in window) {
            observer = new IntersectionObserver(function (entries) {
                if (entries.some(function (entry) { return entry.isIntersecting; })) {
                    load(false);
                }
            }, { rootMargin: '0px 0px 400px 0px' });
            $container.addClass('elemacy-infinite-active');
            watchEnd();
        }
    }

    // ---- Entry -------------------------------------------------------------

    function init($scope) {
        // The loop's own container, not one of a nested loop's.
        var $container = $scope.find('.elemacy-ajax-pagination[data-elemacy-loop-style]').filter(function () {
            return $(this).closest('[data-widget_type="elemacy-loop-grid.default"]')[0] === $scope[0];
        }).first();
        var style = $container.attr('data-elemacy-loop-style');

        if ((style !== 'load_more' && style !== 'infinite_scroll') || $container.data('elemacyIncremental')) {
            return;
        }

        var transport = $container.attr('data-elemacy-loop-mode') === 'page'
            ? pageUrlTransport($container)
            : endpointTransport($container);

        // No transport (no fetch, bad payload): the link stays a normal link.
        if (transport) {
            $container.data('elemacyIncremental', true);
            IncrementalLoop($container, transport, style === 'infinite_scroll');
        }
    }

    $(window).on('elementor/frontend/init', function () {
        // The editor shows the button but never loads pages.
        if (elementorFrontend.isEditMode()) {
            return;
        }
        elementorFrontend.hooks.addAction('frontend/element_ready/elemacy-loop-grid.default', init);
    });
})(jQuery);
