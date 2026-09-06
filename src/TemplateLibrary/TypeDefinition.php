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

    public function __construct(string $name, string $label, string $group, string $module)
    {
        $this->name   = $name;
        $this->label  = $label;
        $this->group  = $group;
        $this->module = $module;
    }
}
