<?php

namespace Elemacy\Core\Integrations\Elementor;

defined('ABSPATH') || exit;

/**
 * The one place AtomicStylesRenderer reaches into Elementor's Atomic Widgets
 * element-traversal API (Utils::traverse_post_elements(), Atomic_Elements_Utils,
 * Dynamic_Prop_Type) to collect a post's atomic element `styles` definitions
 * and detect a dynamic-tag-bound value inside one — design.md D9's "small
 * adapters for version-specific calls", isolating the exact call shape a
 * future Elementor version could restructure behind one adapter instead of
 * three call sites inline in the renderer itself.
 */
class AtomicElementsAdapter
{
    protected Capabilities $capabilities;

    public function __construct(?Capabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new Capabilities();
    }

    /**
     * Whether the Atomic element-traversal capability this adapter wraps is
     * actually present on the installed Elementor.
     */
    public function available(): bool
    {
        return $this->capabilities->atomic_widgets() && $this->capabilities->atomic_global_classes();
    }

    /**
     * Every atomic element's own `styles` definition on $post_id, one array
     * per element, kept separate and unfiltered. Empty (never a fatal) when
     * the Atomic Widgets capability is absent.
     *
     * @param int $post_id
     * @return array<int, array>
     */
    public function collect_element_styles(int $post_id): array
    {
        if (!$this->available()) {
            return [];
        }

        $per_element_styles = [];

        \Elementor\Modules\AtomicWidgets\Utils\Utils::traverse_post_elements(
            (string) $post_id,
            function (array $element_data) use (&$per_element_styles) {
                $element_type = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_type($element_data);
                $element_instance = \Elementor\Modules\GlobalClasses\Utils\Atomic_Elements_Utils::get_element_instance($element_type);

                if (!\Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic($element_instance)) {
                    return;
                }

                $per_element_styles[] = $element_data['styles'] ?? [];
            }
        );

        return $per_element_styles;
    }

    /**
     * Whether $value (recursively) contains an Atomic dynamic-tag-bound
     * value. Falls back to plain structural recursion (never a fatal) when
     * the Dynamic_Prop_Type capability is absent — it simply can't identify
     * a dynamic-bound leaf, so it only ever under-reports, never fatals.
     *
     * @param mixed $value
     * @return bool
     */
    public function contains_dynamic_value($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($this->capabilities->atomic_dynamic_prop_type()
            && \Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Prop_Type::is_dynamic_prop_value($value)) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->contains_dynamic_value($item)) {
                return true;
            }
        }

        return false;
    }
}
