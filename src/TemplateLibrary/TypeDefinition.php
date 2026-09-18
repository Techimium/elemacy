<?php

namespace Elemacy\TemplateLibrary;

defined('ABSPATH') || exit;

/**
 * Describes one kind of library item (e.g. header, footer, popup, topbar).
 * Modules register the types they own; the registry is pure code with no DB
 * footprint, so types come and go with the module without touching stored data.
 */
class TypeDefinition
{
    public string $name;
    public string $label;

    /**
     * Coarse family used for resolution and admin grouping, e.g. 'theme' or
     * 'popup'.
     */
    public string $group;

    /**
     * Slug of the module that owns this type, so the admin can mark items whose
     * module is currently inactive.
     */
    public string $module;

    /**
     * Fully-qualified Elementor document class backing this type, if it has
     * one of its own (e.g. for real-data Preview Settings). Null means the
     * caller's own fallback applies — typically Elementor's built-in
     * 'wp-page' document, but this is a decision each builder-specific
     * service already owns, not a rule this registry imposes.
     *
     * @var class-string|null
     */
    public ?string $document_class;

    public function __construct(string $name, string $label, string $group, string $module, ?string $document_class = null)
    {
        $this->name           = $name;
        $this->label          = $label;
        $this->group          = $group;
        $this->module         = $module;
        $this->document_class = $document_class;
    }

    /**
     * The Elementor document type string this type resolves to, or null when
     * it has no document of its own — the caller decides its own fallback.
     */
    public function get_document_type(): ?string
    {
        return $this->document_class !== null ? ($this->document_class)::get_type() : null;
    }
}
