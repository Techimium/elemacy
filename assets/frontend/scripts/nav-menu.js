/**
 * Elemacy Nav Menu - Frontend Script
 *
 * Lightweight, conflict-free behavior for the Elemacy nav widget:
 * - Handles mobile toggle open/close
 * - Syncs aria-expanded / aria-hidden states
 * - Closes on outside click and Escape
 *
 * All behavior is scoped to `.elemacy-nav` instances.
 */

(function () {
    'use strict';

    if (typeof window === 'undefined' || !document) {
        return;
    }

    /**
     * Initialize a single nav instance.
     *
     * @param {HTMLElement} navRoot
     */
    function initNav(navRoot) {
        var toggle = navRoot.querySelector('.elemacy-nav__toggle');
        var menu = navRoot.querySelector('.elemacy-nav__menu');

        if (!menu) {
            return;
        }

        if (toggle) {
            var menuId = menu.getAttribute('id') || '';
            if (menuId) {
                toggle.setAttribute('aria-controls', menuId);
            }

            toggle.addEventListener('click', function () {
                var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                setOpenState(!isExpanded);
            });

            toggle.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    var isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                    setOpenState(!isExpanded);
                }
            });
        }

        function setOpenState(isOpen) {
            if (!toggle) {
                return;
            }

            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (isOpen) {
                menu.classList.add('is-open');
                document.addEventListener('click', handleDocumentClick, true);
                document.addEventListener('keydown', handleKeydown, true);
            } else {
                menu.classList.remove('is-open');
                document.removeEventListener('click', handleDocumentClick, true);
                document.removeEventListener('keydown', handleKeydown, true);
            }
        }

        function handleDocumentClick(event) {
            if (!navRoot.contains(event.target)) {
                setOpenState(false);
            }
        }

        function handleKeydown(event) {
            if (event.key === 'Escape' || event.key === 'Esc') {
                setOpenState(false);
                if (toggle) {
                    toggle.focus();
                }
            }
        }

        // Enhance nested submenus (multi-level) with toggle buttons.
        initSubmenus(navRoot);
    }

    /**
     * Initialize submenu toggle buttons for items that have children.
     *
     * @param {HTMLElement} navRoot
     */
    function initSubmenus(navRoot) {
        var items = navRoot.querySelectorAll('.elemacy-nav__item.menu-item-has-children, .elemacy-nav__item.elemacy-nav__item--has-children');
        if (!items.length) {
            return;
        }

        for (var i = 0; i < items.length; i++) {
            (function () {
                var li = items[i];
                var link = li.querySelector('.elemacy-nav__link');
                var submenu = li.querySelector('ul.sub-menu, .elemacy-nav__submenu');

                if (!link || !submenu) {
                    return;
                }

                // Normalize submenu class.
                submenu.classList.add('elemacy-nav__submenu');

                // Avoid adding duplicate toggles when Elementor re-renders in editor.
                var existingToggle = li.querySelector('.elemacy-nav__submenu-toggle');
                if (existingToggle) {
                    return;
                }

                var toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'elemacy-nav__submenu-toggle';
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.innerHTML = '<span class="elemacy-nav__submenu-toggle-icon" aria-hidden="true"></span>' +
                    '<span class="screen-reader-text">Toggle submenu</span>';

                // Insert toggle right after the link.
                if (link.nextSibling) {
                    li.insertBefore(toggleBtn, link.nextSibling);
                } else {
                    li.appendChild(toggleBtn);
                }

                toggleBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    var isOpen = li.classList.contains('elemacy-nav__item--submenu-open');
                    if (isOpen) {
                        li.classList.remove('elemacy-nav__item--submenu-open');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    } else {
                        li.classList.add('elemacy-nav__item--submenu-open');
                        toggleBtn.setAttribute('aria-expanded', 'true');
                    }
                });
            })();
        }
    }

    function onReady() {
        var navs = document.querySelectorAll('.elemacy-nav');
        if (navs.length) {
            for (var i = 0; i < navs.length; i++) {
                initNav(navs[i]);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onReady);
    } else {
        onReady();
    }

    // Elementor editor integration.
    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', function () {
            if (window.elementorFrontend && window.elementorFrontend.hooks) {
                window.elementorFrontend.hooks.addAction('frontend/element_ready/elemacy_nav_menu.default', function (scope) {
                    var root = scope[0] || scope;
                    if (!root) {
                        return;
                    }
                    var nav = root.querySelector('.elemacy-nav');
                    if (nav) {
                        initNav(nav);
                    }
                });
            }
        });
    }
})();

