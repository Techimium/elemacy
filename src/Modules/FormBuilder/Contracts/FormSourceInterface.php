<?php

namespace Elemacy\Modules\FormBuilder\Contracts;

use Elemacy\Modules\FormBuilder\Definition\FormDefinition;
use Elemacy\Modules\FormBuilder\Exceptions\FormUnavailableException;

defined('ABSPATH') || exit;

/**
 * Where a builder stores its forms. The Widgets module registers the
 * 'elementor' source for the Form widget; another builder registers its own on
 * Hooks::FORM_SOURCES_REGISTER_ACTION and reuses the rest of the engine.
 * The engine checks the owner's accessibility before asking the source.
 *
 * @api
 */
interface FormSourceInterface
{
    /** The identifier forms of this builder post as form_source. */
    public function key(): string;

    /**
     * The saved form $form_id stored in post $owner.
     *
     * @throws FormUnavailableException When the owner holds no such form.
     */
    public function resolve(int $owner, string $form_id): FormDefinition;
}
