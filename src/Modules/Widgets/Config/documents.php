<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Documents\LoopDocument;

/**
 * Block-library template type => the Elementor document that backs it. Loop items
 * get a previewable document; other block types fall back to Elementor's built-in
 * 'wp-page' document. Both the document registration (DocumentManager) and the
 * type mapping (BlockTemplateService) read this map.
 */
return [
    'loop' => LoopDocument::class,
];
