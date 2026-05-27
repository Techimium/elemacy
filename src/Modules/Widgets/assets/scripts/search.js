(function ($) {
    "use strict";

    /**
     * Full-screen skin handler.
     * The skin class (.elemacy-search-form--skin-full_screen) lives on the
     * widget wrapper $scope itself (via Elementor's `prefix_class`), so this
     * is also where the `is-open` toggle class belongs.
     *
     * The other skins (classic, minimal) submit natively — no JS needed.
     */
    const ElemacySearchHandler = function ($scope) {
        if (!$scope.hasClass('elemacy-search-form--skin-full_screen')) {
            return;
        }

        const $toggle    = $scope.find('.elemacy-search-form__toggle');
        const $container = $scope.find('.elemacy-search-form__container');
        const $input     = $scope.find('.elemacy-search-form__input');
        const $close     = $scope.find('.elemacy-search-form__close');

        if (!$toggle.length || !$container.length) {
            return;
        }

        const BODY_LOCK_CLASS = 'elemacy-search-form-is-open';
        const NAMESPACE       = '.elemacySearch-' + ($scope.data('id') || $scope.attr('data-id') || '');

        let lastFocused = null;

        function open(e) {
            if (e) {
                e.preventDefault();
            }

            lastFocused = document.activeElement;

            $scope.addClass('is-open');
            $toggle.attr('aria-expanded', 'true');
            document.body.classList.add(BODY_LOCK_CLASS);

            window.requestAnimationFrame(function () {
                $input.trigger('focus');
            });
        }

        function close(e) {
            if (e) {
                e.preventDefault();
            }

            if (!$scope.hasClass('is-open')) {
                return;
            }

            $scope.removeClass('is-open');
            $toggle.attr('aria-expanded', 'false');
            document.body.classList.remove(BODY_LOCK_CLASS);

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        }

        $toggle.on('click keydown', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                return;
            }

            open(e);
        });

        $close.on('click', close);

        // Click on the dimmed backdrop (container itself, not its children).
        $container.on('mousedown', function (e) {
            if (e.target === this) {
                close(e);
            }
        });

        $(document).on('keydown' + NAMESPACE, function (e) {
            if (e.key === 'Escape') {
                close(e);
            }
        });

        // Editor live-preview: tear listener down when the widget is destroyed.
        $scope.on('remove', function () {
            $(document).off('keydown' + NAMESPACE);
            document.body.classList.remove(BODY_LOCK_CLASS);
        });
    };

    $(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/elemacy-search.default',
            ElemacySearchHandler
        );
    });
})(jQuery);
