<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Registers the Single, Archive and Search Elementor document types so those
 * theme templates edit on dedicated documents with real-data Preview Settings.
 */
class DocumentManager
{
    public function __construct()
    {
        add_action('elementor/documents/register', [$this, 'register_document_types']);
    }

    /**
     * @param \Elementor\Core\Documents_Manager $documents_manager
     */
    public function register_document_types($documents_manager): void
    {
        foreach (require Utils::get_plugin_path('src/Modules/ThemeBuilder/Config/documents.php') as $document) {
            $documents_manager->register_document_type($document::get_type(), $document::get_class_full_name());
        }
    }
}
