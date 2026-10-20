<?php

/**
 * A field type and a submit action registered the way an add-on would:
 * entirely through the forms engine's public registration hooks, with no
 * edit to Free. The field ('fixture_color') accepts red or blue; the action
 * ('fixture_log') records each submission and fails with its own message
 * when the submitted 'outcome' field is "fail".
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

// Literal hook names: mu-plugins load before the plugin autoloader is registered.
add_action('elemacy/action/forms/field_types/register', static function ($registry): void {
    if (!class_exists('Elemacy_Fixture_Color_Field')) {
        class Elemacy_Fixture_Color_Field extends \Elemacy\Forms\Fields\AbstractFieldType
        {
            public function key(): string
            {
                return 'fixture_color';
            }

            public function label(): string
            {
                return 'Fixture Color';
            }

            public function settings(): array
            {
                return [\Elemacy\Forms\Settings\Setting::standard('required'), \Elemacy\Forms\Settings\Setting::text('fixture_shade', 'Shade')];
            }

            protected function check(\Elemacy\Forms\Definition\Field $field, string $value): \Elemacy\Forms\Submission\FieldResult
            {
                return in_array($value, ['red', 'blue'], true)
                    ? \Elemacy\Forms\Submission\FieldResult::valid($value)
                    : \Elemacy\Forms\Submission\FieldResult::invalid('Pick red or blue.');
            }

            protected function input_attributes(\Elemacy\Forms\Definition\Field $field): array
            {
                return ['data-fixture-color' => 'yes'];
            }
        }
    }

    $registry->register(new Elemacy_Fixture_Color_Field());
});

add_action('elemacy/action/forms/actions/register', static function ($registry): void {
    if (!class_exists('Elemacy_Fixture_Log_Action')) {
        class Elemacy_Fixture_Log_Action implements \Elemacy\Forms\Contracts\FormActionInterface
        {
            public function key(): string
            {
                return 'fixture_log';
            }

            public function label(): string
            {
                return 'Fixture Log';
            }

            public function settings(): array
            {
                return [\Elemacy\Forms\Settings\Setting::text('fixture_log_note', 'Note')];
            }

            public function run(\Elemacy\Forms\Submission\Submission $submission): \Elemacy\Forms\Submission\ActionResult
            {
                $GLOBALS['elemacy_fixture_form_log'][] = [
                    'values' => $submission->values(),
                    'user' => $submission->meta()->user_id(),
                    'note' => $submission->form()->setting('fixture_log_note'),
                ];

                return 'fail' === $submission->value('outcome')
                    ? \Elemacy\Forms\Submission\ActionResult::failure('Could not reach the CRM')
                    : \Elemacy\Forms\Submission\ActionResult::success(['logged' => true]);
            }
        }
    }

    $registry->register(new Elemacy_Fixture_Log_Action());
});
