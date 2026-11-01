<?php

namespace Elemacy\Modules\LoopBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

/**
 * Registers the Loop Item Elementor document type so loop templates edit on a
 * dedicated document with real-data Preview Settings.
 *
 * @internal
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
        foreach (require Utils::get_plugin_path('src/Modules/LoopBuilder/Config/documents.php') as $document) {
            $documents_manager->register_document_type($document::get_type(), $document::get_class_full_name());
        }
    }
}
