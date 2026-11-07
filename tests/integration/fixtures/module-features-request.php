<?php

/**
 * Boots WordPress with a given set of active modules and widget switches in
 * its own PHP process and prints what the widget platform, Loop Builder and
 * Form Builder expose as JSON. See ModuleFeatureAvailabilityTest.
 *
 * Elementor registers its widget types once per process and the module set
 * is read once per request, so each scenario needs a fresh process.
 *
 * Input on STDIN: {modules: array<string, bool>, widgets: array<string, bool>}.
 * `modules` replaces the stored active modules and `widgets` the stored
 * widget switches, both through pre_option filters seeded before WordPress
 * loads. A field-type and an action listener count how often the form
 * registration hooks fire.
 */

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$fired = ['field_types' => 0, 'actions' => 0];
$listener = static function (string $hook) use (&$fired): array {
    return [10 => [[
        'function' => static function () use (&$fired, $hook): void {
            $fired[$hook]++;
        },
        'accepted_args' => 1,
    ]]];
};

$GLOBALS['wp_filter'] = [
    'elemacy/action/forms/field_types/register' => $listener('field_types'),
    'elemacy/action/forms/actions/register' => $listener('actions'),
    'pre_option_elemacy_active_modules' => [10 => [[
        'function' => static fn() => $input['modules'],
        'accepted_args' => 1,
    ]]],
    'pre_option_elemacy_widgets' => [10 => [[
        'function' => static fn() => $input['widgets'],
        'accepted_args' => 1,
    ]]],
];

require dirname(__DIR__) . '/bootstrap.php';

use Elemacy\Conditions\Contracts\ConditionRegistrarInterface;
use Elemacy\Core\Container;
use Elemacy\Modules\FormBuilder\Contracts\FormActionRegistrarInterface;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Widgets\WidgetRegistry;

$resolves = static function (string $contract): string {
    try {
        Container::instance()->singleton($contract);

        return 'resolved';
    } catch (\Throwable $error) {
        return 'failed: ' . $error->getMessage();
    }
};

// A render, edit or submission looks a field type and an action up; that first
// lookup is what loads a registry and fires its hook. Only while the contract
// resolves, so a disabled Form Builder is never handed a registry to build.
$form_contract = $resolves(FormActionRegistrarInterface::class);
if ('resolved' === $form_contract) {
    Container::instance()->singleton(\Elemacy\Modules\FormBuilder\Services\FieldTypeRegistry::class)->get('email');
    Container::instance()->singleton(\Elemacy\Modules\FormBuilder\Services\FormActionRegistry::class)->get('email');
}

$registered = array_values(array_filter(
    array_keys(\Elementor\Plugin::$instance->widgets_manager->get_widget_types()),
    static fn(string $name): bool => 0 === strpos($name, 'elemacy-')
));

$listed = [];
foreach (Container::instance()->singleton(WidgetRegistry::class)->get_all_widgets() as $widget) {
    $listed[$widget->name] = $widget->is_enabled;
}

echo wp_json_encode([
    'registered' => $registered,
    'listed' => (object) $listed,
    'loop_type' => null !== TypeRegistry::instance()->get('loop'),
    'loop_pagination' => false !== has_action('wp_ajax_nopriv_elemacy_loop_builder_pagination'),
    'form_endpoint' => false !== has_action('wp_ajax_nopriv_elemacy_form_submit'),
    'form_contract' => $form_contract,
    'shared_contract' => $resolves(ConditionRegistrarInterface::class),
    'fired' => $fired,
]);
