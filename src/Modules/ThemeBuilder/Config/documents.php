<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Documents\ArchiveDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SearchDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SingleDocument;

/**
 * Theme Builder template type => the Elementor document that backs it, giving
 * those types real-data Preview Settings. Types not listed fall back to
 * Elementor's built-in 'wp-page' document. Both the document registration
 * (DocumentManager) and the type mapping (TemplateService) read this map.
 */
return [
    'single'  => SingleDocument::class,
    'archive' => ArchiveDocument::class,
    'search'  => SearchDocument::class,
];
