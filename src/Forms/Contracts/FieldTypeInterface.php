<?php

namespace Elemacy\Forms\Contracts;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;
use Elemacy\Forms\Settings\Setting;
use Elemacy\Forms\Submission\FieldResult;

defined('ABSPATH') || exit;

/**
 * A kind of form field: its settings, its control markup, and the server-side
 * rule for its value. Register one on Hooks::FORM_FIELD_TYPES_REGISTER_ACTION.
 * Extend Fields\AbstractFieldType rather than implementing this directly:
 * new methods arrive there with defaults, never here.
 *
 * @api
 */
interface FieldTypeInterface
{
    /** A labelled single control. */
    public const LAYOUT_FIELD = 'field';

    /** Several controls under one legend (radio, checkbox, acceptance). */
    public const LAYOUT_GROUP = 'group';

    /** A control with no visible chrome (hidden). */
    public const LAYOUT_HIDDEN = 'hidden';

    /** Content that submits nothing (HTML). */
    public const LAYOUT_CONTENT = 'content';

    /** The saved type identifier, e.g. 'number'. Never change it once released. */
    public function key(): string;

    public function label(): string;

    /** @return Setting[] Shown in the field's settings while this type is selected. */
    public function settings(): array;

    /** One of the LAYOUT_* constants. */
    public function layout(): string;

    /** The control markup only; the engine renders the wrapper, label and error slot. */
    public function render_control(Field $field, RenderContext $context): string;

    /**
     * Check and sanitize the submitted value, which is null when the browser
     * sent nothing. Never trust its shape.
     *
     * @param Field $field The saved field.
     * @param mixed $raw   The submitted value.
     */
    public function validate(Field $field, $raw): FieldResult;

    /**
     * The value as plain text for emails and other plain-text uses.
     *
     * @param Field           $field The saved field.
     * @param string|string[] $value A value validate() accepted.
     */
    public function format(Field $field, $value): string;
}
