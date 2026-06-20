<?php

namespace Elemacy\Modules\Widgets\Walkers;

use Walker_Nav_Menu;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom walker for Elemacy Nav Menu widget.
 * Outputs BEM-style classes and no item IDs; no filters required.
 */
class NavMenuWalker extends Walker_Nav_Menu
{
    /**
     * Starts the list before the elements are added.
     *
     * @param string    $output Used to append additional content (passed by reference).
     * @param int       $depth  Depth of menu item. Used for padding.
     * @param \stdClass $args   An object of wp_nav_menu() arguments.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        [$tab, $newline] = $this->getSpacingCharacters($args);

        $indent           = str_repeat($tab, $depth);
        $submenu_classes  = ['elemacy-nav__submenu', 'sub-menu'];
        $filtered_classes = apply_filters('nav_menu_submenu_css_class', $submenu_classes, $args, $depth);
        $class_attribute  = esc_attr(implode(' ', $filtered_classes));

        $output .= "{$newline}{$indent}<ul class=\"{$class_attribute}\">{$newline}";
    }

    /**
     * Ends the list after the elements are added.
     *
     * @param string    $output Used to append additional content (passed by reference).
     * @param int       $depth  Depth of menu item. Used for padding.
     * @param \stdClass $args   An object of wp_nav_menu() arguments.
     */
    public function end_lvl(&$output, $depth = 0, $args = null): void
    {
        [$tab, $newline] = $this->getSpacingCharacters($args);

        $indent  = str_repeat($tab, $depth);
        $output .= "{$indent}</ul>{$newline}";
    }

    /**
     * Starts the element output.
     *
     * @param string    $output            Used to append additional content (passed by reference).
     * @param \WP_Post  $data_object       Menu item data object.
     * @param int       $depth             Depth of menu item. Used for padding.
     * @param \stdClass $args              An object of wp_nav_menu() arguments.
     * @param int       $current_object_id Optional. ID of the current menu item. Default 0.
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0): void
    {
        $args      = (object) $args;
        $menu_item = $data_object;

        [$tab]  = $this->getSpacingCharacters($args);
        $indent = $depth ? str_repeat($tab, $depth) : '';

        $item_classes    = $this->buildListItemClasses($menu_item, $depth);
        $class_names     = implode(' ', apply_filters('nav_menu_css_class', array_filter($item_classes), $menu_item, $args, $depth));
        $is_current_item = in_array('current-menu-item', $item_classes, true);
        $has_children    = in_array('menu-item-has-children', $item_classes, true);

        $list_item_attributes        = ['class' => $class_names];
        $list_item_attributes        = apply_filters('nav_menu_item_attributes', $list_item_attributes, $menu_item, $args, $depth);
        $list_item_attribute_string  = $this->buildAttributeString($list_item_attributes, false);

        $output .= $indent . '<li' . $list_item_attribute_string . '>';

        $link_attributes        = $this->buildLinkAttributes($menu_item, $is_current_item, $has_children);
        $link_attributes        = apply_filters('nav_menu_link_attributes', $link_attributes, $menu_item, $args, $depth);
        $link_attribute_string  = $this->buildAttributeString($link_attributes, true);

        $title = apply_filters('the_title', $menu_item->title, $menu_item->ID);
        $title = apply_filters('nav_menu_item_title', $title, $menu_item, $args, $depth);

        $item_output  = $args->before ?? '';
        $item_output .= '<a' . $link_attribute_string . '>';
        $item_output .= ($args->link_before ?? '') . $title . ($args->link_after ?? '');
        $item_output .= '</a>';
        $item_output .= $args->after ?? '';

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $menu_item, $depth, $args);
    }

    /**
     * Ends the element output.
     *
     * @param string    $output      Used to append additional content (passed by reference).
     * @param \WP_Post  $data_object Menu item data object.
     * @param int       $depth       Depth of menu item. Used for padding.
     * @param \stdClass $args        An object of wp_nav_menu() arguments.
     */
    public function end_el(&$output, $data_object, $depth = 0, $args = null): void
    {
        [, $newline] = $this->getSpacingCharacters($args);
        $output     .= "</li>{$newline}";
    }

    /**
     * Returns the tab and newline characters based on the item_spacing argument.
     *
     * @param \stdClass|null $args
     * @return array{string, string} [$tab, $newline]
     */
    private function getSpacingCharacters($args): array
    {
        if (isset($args->item_spacing) && 'discard' === $args->item_spacing) {
            return ['', ''];
        }

        return ["\t", "\n"];
    }

    /**
     * Builds the CSS class list for a list item element.
     *
     * @param \WP_Post $menu_item
     * @param int      $depth
     * @return array<string>
     */
    private function buildListItemClasses($menu_item, int $depth): array
    {
        $classes   = empty($menu_item->classes) ? [] : (array) $menu_item->classes;
        $classes[] = 'elemacy-nav__item';
        $classes[] = 'menu-item-' . $menu_item->ID;

        if ($depth) {
            $classes[] = 'elemacy-nav__item--depth-' . $depth;
        }

        if (in_array('menu-item-has-children', $classes, true)) {
            $classes[] = 'elemacy-nav__item--has-children';
        }

        return $classes;
    }

    /**
     * Builds the anchor tag attributes for a menu item link.
     *
     * @param \WP_Post $menu_item
     * @param bool     $is_current_item
     * @param bool     $has_children
     * @return array<string, string>
     */
    private function buildLinkAttributes($menu_item, bool $is_current_item, bool $has_children): array
    {
        $link_classes   = ['elemacy-nav__link'];
        $link_classes[] = $is_current_item ? 'elemacy-is-active' : '';

        $attributes = [
            'title'        => $menu_item->attr_title ?? '',
            'target'       => $menu_item->target ?? '',
            'rel'          => ('_blank' === $menu_item->target && empty($menu_item->xfn)) ? 'noopener' : ($menu_item->xfn ?? ''),
            'href'         => $menu_item->url ?? '',
            'class'        => implode(' ', array_filter($link_classes)),
            'aria-current' => $is_current_item ? 'page' : '',
        ];

        if ($has_children) {
            $attributes['aria-haspopup'] = 'menu';
        }

        return $attributes;
    }

    /**
     * Converts an associative array of attributes into an HTML attribute string.
     *
     * @param array<string, mixed> $attributes
     * @param bool                 $escape_urls  Whether to apply esc_url() to href values.
     * @return string
     */
    private function buildAttributeString(array $attributes, bool $escape_urls = true): string
    {
        $attribute_string = '';

        foreach ($attributes as $attribute_name => $attribute_value) {
            if (!is_scalar($attribute_value) || '' === $attribute_value || false === $attribute_value) {
                continue;
            }

            $escaped_value     = ($escape_urls && 'href' === $attribute_name) ? esc_url($attribute_value) : esc_attr($attribute_value);
            $attribute_string .= ' ' . $attribute_name . '="' . $escaped_value . '"';
        }

        return $attribute_string;
    }
}
