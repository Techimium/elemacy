<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Documents\ArchiveDocument;
use Elemacy\Modules\ThemeBuilder\Documents\FooterDocument;
use Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SearchDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SingleDocument;

/**
 * Theme Builder template type => the Elementor document that backs it. Header
 * and Footer get a chrome document, narrowed to the settings that actually
 * affect site chrome; Single, Archive and Search get a previewable document
 * with real-data Preview Settings. Types not listed fall back to Elementor's
 * built-in 'wp-page' document. Both the document registration
 * (DocumentManager) and the type mapping (TemplateService) read this map.
 */
return [
    'header'  => HeaderDocument::class,
    'footer'  => FooterDocument::class,
    'single'  => SingleDocument::class,
    'archive' => ArchiveDocument::class,
    'search'  => SearchDocument::class,
];
