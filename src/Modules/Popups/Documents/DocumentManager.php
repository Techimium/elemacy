<?php

namespace Elemacy\Modules\Popups\Documents;

defined('ABSPATH') || exit;

/**
 * Registers the popup Elementor document type.
 */
class DocumentManager
{
    /**
     * Hook the document registration into Elementor.
     */
    public function register_hooks(): void
    {
        add_action('elementor/documents/register', [$this, 'register_document_type']);
    }

    /**
     * @param \Elementor\Core\Documents_Manager $documents_manager
     */
    public function register_document_type($documents_manager): void
    {
        $documents_manager->register_document_type(
            PopupDocument::get_type(),
            PopupDocument::get_class_full_name()
        );
    }
}
