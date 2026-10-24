<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Documents\ArchiveDocument;
use Elemacy\Modules\ThemeBuilder\Documents\FooterDocument;
use Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument;
use Elemacy\Modules\ThemeBuilder\Documents\NotFoundDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SearchDocument;
use Elemacy\Modules\ThemeBuilder\Documents\SingleDocument;

/**
 * Theme Builder template type => the Elementor document that backs it. Header
 * and Footer get a chrome document, narrowed to the settings that actually
 * affect site chrome; Single, Archive and Search get a previewable document
 * with real-data Preview Settings; 404 gets a plain page document, having
 * nothing to preview against. Every type has its own document, so each can
 * change without touching another. Both the document registration
 * (DocumentManager) and the type mapping (TemplateService) read this map.
 */
return [
    'header'  => HeaderDocument::class,
    'footer'  => FooterDocument::class,
    'single'  => SingleDocument::class,
    'archive' => ArchiveDocument::class,
    'search'  => SearchDocument::class,
    '404'     => NotFoundDocument::class,
];
