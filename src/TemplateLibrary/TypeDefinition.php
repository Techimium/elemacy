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
    /** Displayed only where one of its inclusion conditions matches. */
    public const TARGETS_CONDITIONS = 'conditions';

    /** Offers no conditions: applies to every request of its location. */
    public const TARGETS_LOCATION = 'location';

    public string $name;
    public string $label;

    /**
     * Coarse family used for resolution and admin grouping, e.g. 'theme' or
     * 'popup'.
     *
     * @var string
     */
    public string $group;

    /**
     * Slug of the module that owns this type, so the admin can mark items whose
     * module is currently inactive.
     *
     * @var string
     */
    public string $module;

    /**
     * Fully-qualified Elementor document class backing this type, if it has
     * one of its own (e.g. for real-data Preview Settings).
     *
     * @var class-string|null
     */
    public ?string $document_class;

    /**
     * How a single-winner resolution targets items of this type: one of the
     * TARGETS_* constants. Collect-all groups (popups) ignore it.
     *
     * @var string
     */
    public string $targeting;

    /**
     * Rules a new item of this type starts with, in stored array form, so an
     * author begins from the type's broadest explicit condition.
     *
     * @var array<int, array{type: string, operator: string, value: string}>
     */
    public array $default_conditions;

    /**
     * @param string                                                           $name               Stored type identifier.
     * @param string                                                           $label              Translated label.
     * @param string                                                           $group              Resolution and admin family.
     * @param string                                                           $module             Owning module slug.
     * @param class-string|null                                                $document_class     Elementor document class, if the type has one.
     * @param string                                                           $targeting          One of the TARGETS_* constants.
     * @param array<int, array{type: string, operator: string, value: string}> $default_conditions Rules a new item starts with.
     */
    public function __construct(
        string $name,
        string $label,
        string $group,
        string $module,
        ?string $document_class = null,
        string $targeting = self::TARGETS_CONDITIONS,
        array $default_conditions = []
    ) {
        $this->name               = $name;
        $this->label              = $label;
        $this->group              = $group;
        $this->module             = $module;
        $this->document_class     = $document_class;
        $this->targeting          = $targeting;
        $this->default_conditions = $default_conditions;
    }

    public function targets_location(): bool
    {
        return self::TARGETS_LOCATION === $this->targeting;
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
