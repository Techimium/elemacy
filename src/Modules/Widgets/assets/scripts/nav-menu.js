/**
 * Elemacy Nav Menu (elemacy_nav_menu) frontend.
 *
 * Behaves like the Elementor Pro Nav Menu, whose submenus come from the
 * SmartMenus jQuery plugin, without loading it: the same hover delays, fades,
 * slides, tap rules, keyboard keys and viewport repositioning, implemented on
 * the DOM. One Elementor handler instance per widget owns every listener it
 * adds, so editor re-renders never leave a listener behind.
 */
(function ($) {
    'use strict';

    /** SmartMenus timings used by the reference widget. */
    const SHOW_DELAY = 250;
    const HIDE_DELAY = 500;
    const TOUCH_HIDE_DELAY = 350;
    const FADE_DURATION = 200;
    const SLIDE_DURATION = 200;

    /** jQuery's default "swing" easing, which SmartMenus' fade and slide use. */
    const SWING = 'cubic-bezier(.37, 0, .63, 1)';

    /** Open dropdown height when the widget is not sticky; large enough to never clip, small enough to animate. */
    const OPEN_HEIGHT = '1000vmax';

    const CLASSES = {
        main: 'elemacy-nav-menu__main',
        vertical: 'elemacy-nav-menu__main--vertical',
        list: 'elemacy-nav-menu__list',
        container: 'elemacy-nav-menu__container',
        dropdown: 'elemacy-nav-menu__dropdown',
        anchor: 'elemacy-nav-menu__link--anchor',
        active: 'elemacy-nav-menu__link--active',
        open: 'elemacy-nav-menu__link--open',
        indicator: 'elemacy-nav-menu__indicator',
        toggle: 'elemacy-nav-menu__toggle',
        toggleActive: 'elemacy-nav-menu__toggle--active',
    };

    const HEIGHT_PROPERTY = '--elemacy-nav-menu-height';

    /**
     * Menus treat input as touch until a mouse moves, and again after any touch
     * or pen contact, as SmartMenus does. Shared by every menu on the page.
     */
    const pointer = { mouse: false };

    // `pointerover` fires before `mouseenter`, so a cursor that lands straight on
    // a menu item (a page loading under it) already counts as a mouse there.
    ['pointerover', 'pointermove'].forEach((type) => document.addEventListener(type, (event) => {
        if (event.pointerType === 'mouse') {
            pointer.mouse = true;
        }
    }, { passive: true, capture: true }));

    document.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'mouse') {
            pointer.mouse = false;
        }
    }, { passive: true, capture: true });

    function viewportSize() {
        const root = document.documentElement;

        return {
            width: Math.min(root.clientWidth, window.innerWidth),
            height: Math.min(root.clientHeight, window.innerHeight),
        };
    }

    function isShown(element) {
        return window.getComputedStyle(element).display !== 'none';
    }

    /**
     * One menu tree: the desktop menu (hover/focus) or the mobile dropdown's
     * menu (collapsible in place).
     */
    class SubmenuController {
        constructor(root, { collapsible, listen }) {
            this.root = root;
            this.collapsible = collapsible;
            this.vertical = !collapsible && root.closest('.' + CLASSES.main).classList.contains(CLASSES.vertical);

            /** On right-to-left sites submenus open toward the left, as the reference sets SmartMenus up. */
            this.rightToLeft = Boolean(elementorFrontend.config && elementorFrontend.config.is_rtl);

            /** Parent link => its submenu, and back. */
            this.submenus = new Map();
            this.parents = new Map();
            this.levels = new Map();

            /** Activated link per level (index 0 = top level), and submenus in the order they opened. */
            this.activated = [];
            this.visible = [];

            /** Submenu => its running fade or slide, so a new show/hide can finish it first. */
            this.animations = new Map();
            this.pressed = new WeakSet();

            this.zIndex = 0;
            this.showTimer = 0;
            this.hideTimer = 0;
            this.focusActivated = false;
            this.touch = null;

            this.index();
            this.bind(listen);
        }

        index() {
            const id = this.root.id || 'elemacy-menu';
            let count = 0;

            this.root.querySelectorAll('ul').forEach((submenu) => {
                const link = submenu.parentElement.querySelector(':scope > a');

                if (!link) {
                    return;
                }

                let level = 2;
                for (let parent = submenu.parentElement.closest('ul'); parent !== this.root; parent = parent.parentElement.closest('ul')) {
                    level++;
                }

                count++;
                link.id = link.id || `${id}-link-${count}`;
                submenu.id = submenu.id || `${id}-sub-${count}`;

                link.setAttribute('aria-haspopup', 'true');
                link.setAttribute('aria-controls', submenu.id);
                link.setAttribute('aria-expanded', 'false');
                submenu.setAttribute('role', 'group');
                submenu.setAttribute('aria-hidden', 'true');
                submenu.setAttribute('aria-labelledby', link.id);
                submenu.setAttribute('aria-expanded', 'false');

                this.submenus.set(link, submenu);
                this.parents.set(submenu, link);
                this.levels.set(submenu, level);
            });
        }

        bind(listen) {
            const root = this.root;

            listen(root, 'mouseover', (event) => this.onRootOver(event));
            listen(root, 'focusin', (event) => this.onRootOver(event));
            listen(root, 'mouseout', (event) => this.onRootOut(event));
            listen(root, 'focusout', (event) => this.onRootOut(event));
            listen(root, 'keydown', (event) => this.onKeyDown(event));

            root.querySelectorAll('a').forEach((link) => {
                listen(link, 'mouseenter', () => this.onItemEnter(link));
                listen(link, 'mouseleave', () => this.onItemLeave(link));
                listen(link, 'mousedown', () => this.pressed.add(link));
                listen(link, 'focus', () => this.onItemFocus(link));
                listen(link, 'click', (event) => this.onItemClick(event, link));
            });

            listen(document, 'click', (event) => this.onDocumentClick(event));
            listen(document, 'touchstart', (event) => this.onTouchStart(event), { passive: true });
            listen(document, 'touchmove', (event) => this.onTouchMove(event), { passive: true });
            listen(document, 'touchend', () => this.onTouchEnd(), { passive: true });
            listen(window, 'resize', () => this.onResize());
            listen(window, 'orientationchange', () => this.onResize());
        }

        /** No hover in collapsible menus, and none until a mouse is detected. */
        isTouchMode() {
            return !pointer.mouse || this.collapsible;
        }

        levelOf(link) {
            const list = link.closest('ul');

            return list === this.root ? 1 : this.levels.get(list);
        }

        onRootOver(event) {
            if (this.isTouchMode() || event.target === this.root) {
                return;
            }

            clearTimeout(this.hideTimer);
        }

        onRootOut(event) {
            if (this.isTouchMode() || event.target === this.root) {
                return;
            }

            clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(() => this.hideAll(), HIDE_DELAY);
        }

        onItemEnter(link) {
            if (this.isTouchMode()) {
                return;
            }

            clearTimeout(this.showTimer);
            this.showTimer = setTimeout(() => this.activate(link), SHOW_DELAY);
        }

        onItemLeave(link) {
            if (!this.isTouchMode()) {
                link.blur();
                clearTimeout(this.showTimer);
            }

            this.pressed.delete(link);
        }

        /** After a click or Space has opened a submenu, moving focus opens the focused item's submenu too. */
        onItemFocus(link) {
            const last = this.activated[this.activated.length - 1];

            if (this.focusActivated && (!this.isTouchMode() || !this.pressed.has(link)) && last !== link) {
                this.activate(link, true);
            }
        }

        /**
         * A parent whose submenu is closed opens it instead of navigating: the
         * first tap opens, the second follows the link. In the collapsible menu
         * the indicator also closes an open submenu.
         */
        onItemClick(event, link) {
            const submenu = this.submenus.get(link);

            if (!submenu) {
                return;
            }

            if (!isShown(submenu)) {
                this.activate(link);
                this.focusActivated = true;
                event.preventDefault();
                event.stopPropagation();

                return;
            }

            if (this.collapsible && event.target instanceof Element && event.target.closest('.' + CLASSES.indicator)) {
                this.activate(link);
                this.hide(submenu);
                event.preventDefault();
                event.stopPropagation();
            }
        }

        onKeyDown(event) {
            if (event.key === 'Escape') {
                const top = this.activated[0];

                if (top) {
                    this.hideAll();
                    top.focus();
                }

                return;
            }

            const link = event.target instanceof Element ? event.target.closest('a') : null;
            const submenu = link && this.submenus.get(link);

            if (event.key === ' ' && submenu && !isShown(submenu)) {
                this.activate(link);
                this.focusActivated = true;
                event.preventDefault();
            }
        }

        /** Any click outside the menu, or on any link, closes every submenu. */
        onDocumentClick(event) {
            const target = event.target instanceof Element ? event.target : null;

            if ((this.visible.length && !this.root.contains(target)) || (target && target.closest('a'))) {
                this.hideAll();
            }
        }

        onTouchStart(event) {
            const touch = event.touches[0];

            this.touch = { x: touch.pageX, y: touch.pageY, moved: false, target: touch.target };
        }

        onTouchMove(event) {
            if (!this.touch) {
                return;
            }

            const touch = event.touches[0];
            this.touch.moved = this.touch.moved || touch.pageX !== this.touch.x || touch.pageY !== this.touch.y;
        }

        /** A tap (not a scroll) outside the menu closes it, after a pause so the tap cannot land on what was underneath. */
        onTouchEnd() {
            const touch = this.touch;
            this.touch = null;

            if (!touch || touch.moved || !this.visible.length || this.root.contains(touch.target)) {
                return;
            }

            clearTimeout(this.hideTimer);
            this.hideTimer = setTimeout(() => this.hideAll(), TOUCH_HIDE_DELAY);
        }

        onResize() {
            if (!this.collapsible) {
                this.hideAll();
            }
        }

        /** Makes `link` the open item of its level: closes other branches and shows its submenu. */
        activate(link, hideDeeper = false) {
            const level = this.levelOf(link);
            const list = link.closest('ul');

            // A deep item activated directly (for example by focus) opens its ancestors first.
            if (level > 1 && this.activated[level - 2] !== this.parents.get(list)) {
                this.activate(this.parents.get(list));
            }

            if (!this.collapsible || hideDeeper) {
                this.hideSubmenus(this.activated[level - 1] !== link ? level - 1 : level);
            }

            this.activated[level - 1] = link;

            const submenu = this.submenus.get(link);

            if (submenu) {
                this.show(submenu);
            }
        }

        hideSubmenus(level) {
            for (let index = this.activated.length - 1; index >= level; index--) {
                const submenu = this.submenus.get(this.activated[index]);

                if (submenu) {
                    this.hide(submenu);
                }
            }
        }

        hideAll() {
            clearTimeout(this.showTimer);

            for (let index = this.visible.length - 1; index >= 0; index--) {
                this.hide(this.visible[index]);
            }

            this.activated = [];
            this.visible = [];
            this.focusActivated = false;
            this.zIndex = 0;
        }

        show(submenu) {
            this.finishAnimation(submenu);

            if (isShown(submenu)) {
                return;
            }

            const link = this.parents.get(submenu);
            link.classList.add(CLASSES.open);

            if (this.collapsible) {
                this.slide(submenu, true);
            } else {
                this.zIndex = (this.zIndex || parseInt(window.getComputedStyle(this.root).zIndex, 10) || 1) + 1;
                submenu.style.zIndex = String(this.zIndex);
                submenu.style.display = 'block';
                this.position(submenu);
            }

            link.setAttribute('aria-expanded', 'true');
            submenu.setAttribute('aria-expanded', 'true');
            submenu.setAttribute('aria-hidden', 'false');
            this.visible.push(submenu);
        }

        hide(submenu) {
            this.finishAnimation(submenu);

            if (!isShown(submenu)) {
                return;
            }

            const done = () => {
                submenu.style.zIndex = '';
            };

            if (this.collapsible) {
                this.slide(submenu, false, done);
            } else {
                this.fadeOut(submenu, done);
            }

            const link = this.parents.get(submenu);
            link.classList.remove(CLASSES.open);
            link.setAttribute('aria-expanded', 'false');
            submenu.setAttribute('aria-expanded', 'false');
            submenu.setAttribute('aria-hidden', 'true');

            this.activated.splice(this.levels.get(submenu) - 1, 1);
            this.visible.splice(this.visible.indexOf(submenu), 1);
        }

        /**
         * Places a desktop submenu below a top-level item (horizontal layout) or
         * beside its parent item, keeping it inside the viewport: a dropdown
         * shifts sideways, a side menu flips to the other side or moves up, and
         * one taller than the viewport scrolls inside itself. The same arithmetic
         * as SmartMenus, right-to-left mode included, so both directions match
         * the reference.
         */
        position(submenu) {
            const link = this.parents.get(submenu);
            const horizontalParent = this.levels.get(submenu) === 2 && !this.vertical;

            Object.assign(submenu.style, { top: 'auto', left: '0', marginLeft: '', marginTop: '', maxHeight: '', overflowY: '' });

            const sub = submenu.getBoundingClientRect();
            const item = link.getBoundingClientRect();
            const viewport = viewportSize();
            const winX = window.scrollX;
            const winY = window.scrollY;
            const itemX = item.left + winX;
            const itemY = item.top + winY;

            let x;

            if (horizontalParent) {
                x = this.rightToLeft ? item.width - sub.width : 0;
            } else {
                x = this.rightToLeft ? -sub.width : item.width;
            }

            let y = horizontalParent ? item.height : 0;

            const absX = itemX + x;
            const absY = itemY + y;

            if (this.rightToLeft && absX < winX) {
                x = horizontalParent ? winX - absX + x : item.width;
            } else if (!this.rightToLeft && absX + sub.width > winX + viewport.width) {
                x = horizontalParent ? winX + viewport.width - sub.width - absX + x : -sub.width;
            }

            if (!horizontalParent) {
                if (sub.height < viewport.height && absY + sub.height > winY + viewport.height) {
                    y += winY + viewport.height - sub.height - absY;
                } else if (sub.height >= viewport.height || absY < winY) {
                    y += winY - absY;
                }
            }

            const tooTall = horizontalParent
                ? absY + sub.height > winY + viewport.height + 0.49 || absY < winY
                : sub.height > viewport.height + 0.49;

            if (tooTall) {
                submenu.style.maxHeight = `${horizontalParent ? winY + viewport.height - absY : viewport.height}px`;
                submenu.style.overflowY = 'auto';
            }

            submenu.style.marginLeft = `${x}px`;
            submenu.style.marginTop = `${y - item.height}px`;
        }

        fadeOut(submenu, done) {
            this.animate(submenu, [{ opacity: 1 }, { opacity: 0 }], FADE_DURATION, () => {
                submenu.style.display = 'none';
                done();
            });
        }

        slide(submenu, open, done = () => {}) {
            submenu.style.display = 'block';
            submenu.style.overflow = 'hidden';

            const height = `${submenu.scrollHeight}px`;
            const frames = open ? [{ height: '0px' }, { height }] : [{ height }, { height: '0px' }];

            this.animate(submenu, frames, SLIDE_DURATION, () => {
                submenu.style.overflow = '';

                if (!open) {
                    submenu.style.display = 'none';
                }

                done();
            });
        }

        animate(submenu, frames, duration, finish) {
            const animation = submenu.animate(frames, { duration, easing: SWING });
            const entry = { animation, finish };

            this.animations.set(submenu, entry);
            animation.onfinish = () => this.finishAnimation(submenu, entry);
        }

        /** Applies a running animation's end state now, as jQuery's stop(true, true) does. */
        finishAnimation(submenu, expected = null) {
            const entry = this.animations.get(submenu);

            if (!entry || (expected && entry !== expected)) {
                return;
            }

            this.animations.delete(submenu);
            entry.animation.onfinish = null;
            entry.animation.cancel();
            entry.finish();
        }
    }

    /** The hamburger: opens and closes the mobile dropdown, and sizes it inside sticky headers. */
    class DropdownToggle {
        constructor({ widget, toggle, container, stretch, isFullWidth, listen }) {
            this.widget = widget;
            this.toggle = toggle;
            this.container = container;
            this.stretch = stretch;
            this.isFullWidth = isFullWidth;

            listen(toggle, 'click', () => this.setOpen(!this.isOpen()));
            listen(toggle, 'keydown', (event) => this.onKeyDown(event));

            // A stretched dropdown covers the page, so following a final link closes it.
            container.querySelectorAll('.menu-item:not(.menu-item-has-children) > a').forEach((link) => {
                listen(link, 'click', () => {
                    if (this.isFullWidth()) {
                        this.setOpen(false);
                    }
                });
            });

            listen(window, 'scroll', elementorFrontend.debounce(() => this.fitHeight(), 250), { passive: true });
        }

        isOpen() {
            return this.toggle.classList.contains(CLASSES.toggleActive);
        }

        /** Enter and Space activate the toggle like a button, without scrolling the page. */
        onKeyDown(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                this.toggle.click();
            }
        }

        setOpen(open) {
            this.toggle.classList.toggle(CLASSES.toggleActive, open);
            this.toggle.setAttribute('aria-expanded', String(open));
            this.container.setAttribute('aria-hidden', String(!open));
            this.container.querySelectorAll('a').forEach((link) => link.setAttribute('tabindex', open ? '0' : '-1'));
            this.fitHeight();

            if (open && this.isFullWidth()) {
                this.stretch.stretch();
            }
        }

        /** In a sticky header the open dropdown may only use the viewport space below it. */
        fitHeight() {
            if (!this.isOpen()) {
                this.container.style.setProperty(HEIGHT_PROPERTY, '0');

                return;
            }

            if (!this.widget.closest('.elementor-sticky')) {
                this.container.style.setProperty(HEIGHT_PROPERTY, OPEN_HEIGHT);

                return;
            }

            this.container.style.removeProperty(HEIGHT_PROPERTY);
            const top = this.container.getBoundingClientRect().top;
            this.container.style.setProperty(HEIGHT_PROPERTY, `${window.innerHeight - top}px`);
        }
    }

    /**
     * Marks a one-page menu's anchor links active while their section fills the
     * upper half of the viewport (or the top of the viewport, for a section
     * taller than half of it).
     */
    class AnchorTracker {
        constructor(links, listen) {
            this.links = Array.from(links).filter((link) => link.pathname === window.location.pathname && link.hash !== '');
            this.observers = [];

            if (!this.links.length) {
                return;
            }

            this.observe();
            listen(window, 'resize', () => this.observe());
        }

        observe() {
            this.disconnect();
            const viewportHeight = window.innerHeight;

            this.links.forEach((link) => {
                const target = this.target(link);

                if (!target) {
                    return;
                }

                const bottom = -viewportHeight / 2;
                const top = target.offsetHeight > viewportHeight / 2 ? bottom : 0;
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        link.classList.toggle(CLASSES.active, entry.isIntersecting);

                        if (entry.isIntersecting) {
                            link.setAttribute('aria-current', 'location');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                }, { root: null, rootMargin: `${top}px 0px ${bottom}px 0px` });

                observer.observe(target);
                this.observers.push(observer);
            });
        }

        target(link) {
            try {
                return document.querySelector(decodeURIComponent(link.hash));
            } catch (error) {
                return null;
            }
        }

        disconnect() {
            this.observers.forEach((observer) => observer.disconnect());
            this.observers = [];
        }
    }

    $(window).on('elementor/frontend/init', () => {
        const Base = elementorModules.frontend.handlers.Base;

        const NavMenuHandler = Base.extend({
            onInit(...args) {
                Base.prototype.onInit.apply(this, args);

                const widget = this.$element[0];
                const lists = widget.querySelectorAll('.' + CLASSES.list);

                if (!lists.length) {
                    return;
                }

                this.abort = new AbortController();
                const listen = this.listener(widget);
                const container = widget.querySelector(`.${CLASSES.container}.${CLASSES.dropdown}`);
                const toggle = widget.querySelector('.' + CLASSES.toggle);

                lists.forEach((list) => new SubmenuController(list, {
                    collapsible: list.closest(`.${CLASSES.container}.${CLASSES.dropdown}`) !== null,
                    listen,
                }));

                this.stretch = container ? new elementorModules.frontend.tools.StretchElement({ element: $(container) }) : null;
                this.toggle = toggle;
                this.container = container;

                if (toggle && container) {
                    new DropdownToggle({
                        widget,
                        toggle,
                        container,
                        stretch: this.stretch,
                        isFullWidth: () => this.isFullWidth(),
                        listen,
                    });
                }

                this.fitStretch();
                listen(window, 'resize', () => this.fitStretch());

                if (!elementorFrontend.isEditMode()) {
                    this.anchors = new AnchorTracker(widget.querySelectorAll(`.${CLASSES.main} .${CLASSES.anchor}`), listen);
                }
            },

            onElementChange(setting) {
                if (setting === 'full_width') {
                    this.fitStretch();
                }
            },

            onDestroy(...args) {
                this.teardown();
                Base.prototype.onDestroy.apply(this, args);
            },

            /**
             * Adds listeners this instance owns. A listener that fires after the
             * editor has replaced the widget's element tears the instance down
             * instead, so re-renders never accumulate listeners.
             */
            listener(widget) {
                return (target, type, callback, options = {}) => {
                    target.addEventListener(type, (event) => {
                        if (!widget.isConnected) {
                            this.teardown();

                            return;
                        }

                        callback(event);
                    }, { ...options, signal: this.abort.signal });
                };
            },

            teardown() {
                if (this.abort) {
                    this.abort.abort();
                }

                if (this.anchors) {
                    this.anchors.disconnect();
                }
            },

            isFullWidth() {
                return Boolean(this.getElementSettings('full_width'));
            },

            /** A full-width dropdown spans the viewport and opens right below the toggle. */
            fitStretch() {
                if (!this.stretch) {
                    return;
                }

                if (!this.isFullWidth()) {
                    this.stretch.reset();

                    return;
                }

                this.stretch.stretch();

                if (this.toggle) {
                    this.container.style.top = `${this.toggle.offsetHeight}px`;
                }
            },
        });

        elementorFrontend.elementsHandler.attachHandler('elemacy_nav_menu', NavMenuHandler);
    });
})(jQuery);
