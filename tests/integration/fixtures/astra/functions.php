<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Replicates Astra's own default header markup callback and registration. */
function astra_header_markup()
{
    echo '<header id="fixture-astra-header">Astra-like native header</header>';
}
add_action('astra_header', 'astra_header_markup');

/** Replicates Astra's own default footer markup callback and registration. */
function astra_footer_markup()
{
    echo '<footer id="fixture-astra-footer">Astra-like native footer</footer>';
}
add_action('astra_footer', 'astra_footer_markup');
