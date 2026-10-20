<?php

/**
 * Popup root markup checks. Required from run.php, so it shares that file's
 * stubs and check()/conclude().
 */

use Elemacy\Modules\Popups\Services\PopupRenderer;

$popup_box = static fn(string $content, bool $close = true): ?string => (new PopupRenderer())->box_markup($content, $close);

check('box_markup() tags the Elementor root as the box, moves its children into the scroll element and appends the close button', static function () use ($popup_box) {
    $out = $popup_box('<div class="elementor elementor-5" data-elementor-id="5"><div class="e-con">hi</div></div>');

    return '<div class="elemacy-popup__box elementor elementor-5" data-elementor-id="5">'
        . '<div class="elemacy-popup__scroll"><div class="e-con">hi</div></div>'
        . '<button type="button" class="elemacy-popup__close elemacy-close-popup" aria-label="Close">&times;</button></div>' === $out;
});

check('box_markup() leaves sibling markup around the root, and omits the close button when disabled', static function () use ($popup_box) {
    $out = $popup_box('<style id="s">.a{}</style><div class="elementor elementor-5"><p>x</p></div><link rel="stylesheet" href="a.css">', false);

    return '<style id="s">.a{}</style><div class="elemacy-popup__box elementor elementor-5">'
        . '<div class="elemacy-popup__scroll"><p>x</p></div></div><link rel="stylesheet" href="a.css">' === $out;
});

check('box_markup() matches the root\'s own closing tag through nested elements, quoted ">" and raw-text elements', static function () use ($popup_box) {
    $out = $popup_box(
        '<div class="elementor"><div data-x="a>b</div>"><span></span></div><script>var s = "</div>";</script><!-- </div> --><div></div></div><p>after</p>',
        false
    );

    return '<div class="elemacy-popup__box elementor"><div class="elemacy-popup__scroll">'
        . '<div data-x="a>b</div>"><span></span></div><script>var s = "</div>";</script><!-- </div> --><div></div>'
        . '</div></div><p>after</p>' === $out;
});

check('box_markup() takes the first element carrying the class token "elementor", not a class that merely contains it', static function () use ($popup_box) {
    $out = $popup_box('<div class="not-elementor-x">a</div><div class=\'foo elementor\'>b</div>', false);

    return '<div class="not-elementor-x">a</div><div class=\'elemacy-popup__box foo elementor\'><div class="elemacy-popup__scroll">b</div></div>' === $out;
});

check('box_markup() declines a fragment with no Elementor root or an unbalanced one, so the engine opens that popup instead', static function () use ($popup_box) {
    return null === $popup_box('<p>plain</p>')
        && null === $popup_box('')
        && null === $popup_box('<div class="elementor"><div></div>');
});
