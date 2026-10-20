<?php

namespace Elemacy\Modules\Widgets\Forms;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Definition\FieldIdResolver;
use Elemacy\Forms\Definition\FormDefinition;

defined('ABSPATH') || exit;

/**
 * Turns the Form widget's saved settings into the engine's FormDefinition.
 * Rendering and submission both go through here, so field IDs, field order
 * and selected actions can never differ between the page and the server.
 */
final class ElementorFormDefinitions
{
    public const SOURCE = 'elementor';

    public static function from_settings(int $owner, string $form_id, array $settings): FormDefinition
    {
        $rows = is_array($settings['form_fields'] ?? null) ? array_values($settings['form_fields']) : [];
        $ids = FieldIdResolver::resolve($rows)['ids'];
        $fields = [];

        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : [];
            $type = is_string($row['type'] ?? null) && '' !== $row['type'] ? $row['type'] : 'text';
            $label = is_scalar($row['label'] ?? null) ? (string) $row['label'] : '';
            $fields[] = new Field($ids[$index], $type, $label, $row);
        }

        $actions = $settings['submit_actions'] ?? [];
        $actions = is_string($actions) ? [$actions] : (is_array($actions) ? $actions : []);

        return new FormDefinition(self::SOURCE, $owner, $form_id, $fields, array_map('strval', array_filter($actions, 'is_scalar')), $settings);
    }
}
