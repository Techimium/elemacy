<?php

namespace Elemacy\Modules\Widgets\Forms;

use Elemacy\Forms\Contracts\FormSourceInterface;
use Elemacy\Forms\Definition\FormDefinition;
use Elemacy\Forms\FormUnavailableException;
use Elementor\Plugin;

defined('ABSPATH') || exit;

/**
 * The Form widget's saved forms: the one elemacy-form element with the
 * requested ID in the owner's saved Elementor data, with Elementor's control
 * defaults applied (the editor does not store values equal to a default).
 */
final class ElementorFormSource implements FormSourceInterface
{
    public const WIDGET = 'elemacy-form';

    public function key(): string
    {
        return ElementorFormDefinitions::SOURCE;
    }

    public function resolve(int $owner, string $form_id): FormDefinition
    {
        if ('builder' !== get_post_meta($owner, '_elementor_edit_mode', true) || '' === $form_id || strlen($form_id) > 100) {
            throw FormUnavailableException::create();
        }

        $tree = json_decode((string) get_post_meta($owner, '_elementor_data', true), true);

        if (!is_array($tree)) {
            throw FormUnavailableException::create();
        }

        $found = null;
        $stack = [$tree];

        while ($stack) {
            foreach (array_pop($stack) as $element) {
                if (!is_array($element)) {
                    continue;
                }

                if (($element['id'] ?? null) === $form_id) {
                    // Two elements claiming one ID make the request ambiguous.
                    if (null !== $found) {
                        throw FormUnavailableException::create();
                    }

                    $found = $element;
                }

                if (!empty($element['elements']) && is_array($element['elements'])) {
                    $stack[] = $element['elements'];
                }
            }
        }

        if (($found['elType'] ?? '') !== 'widget' || ($found['widgetType'] ?? '') !== self::WIDGET || !is_array($found['settings'] ?? null)) {
            throw FormUnavailableException::create();
        }

        $instance = Plugin::$instance->elements_manager->create_element_instance($found);

        if (!$instance) {
            throw FormUnavailableException::create();
        }

        return ElementorFormDefinitions::from_settings($owner, $form_id, $instance->get_settings());
    }
}
