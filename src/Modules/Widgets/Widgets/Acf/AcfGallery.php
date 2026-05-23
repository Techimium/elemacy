<?php

namespace Elemacy\Modules\Widgets\Widgets\Acf;

defined('ABSPATH') || exit;

use Elemacy\Modules\Widgets\Services\AcfRepeaterResolver;
use Elementor\Controls_Manager;
use Elementor\Plugin;
use Elementor\Widget_Image_Gallery;

/**
 * Renders an Elementor image gallery populated from an ACF gallery field.
 *
 * Extends Widget_Image_Gallery so column / link / lightbox / style controls
 * work unchanged. Swaps the default media-picker `wp_gallery` control for a
 * text input pointing at an ACF gallery field, then normalises the field
 * value (which may come back as objects, arrays or IDs depending on the ACF
 * Return Format) into the `[{ id: <attachment_id> }, ...]` shape the parent
 * widget passes to the [gallery] shortcode.
 */
class AcfGallery extends Widget_Image_Gallery
{
    public function get_name()
    {
        return 'elemacy-acf-gallery';
    }

    public function get_title()
    {
        return esc_html__('ACF Gallery', 'elemacy');
    }

    public function get_icon()
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories()
    {
        return ['elemacy'];
    }

    public function get_keywords()
    {
        return ['image', 'photo', 'gallery', 'acf', 'dynamic'];
    }

    protected function is_dynamic_content(): bool
    {
        return true;
    }

    public function has_widget_inner_wrapper(): bool
    {
        return !Plugin::$instance->experiments->is_feature_active('e_optimized_markup');
    }

    protected function register_controls()
    {
        parent::register_controls();

        $this->update_control('wp_gallery', [
            'label'       => esc_html__('Gallery Field Name', 'elemacy'),
            'description' => esc_html__('Enter the name of the ACF gallery field that holds the images (e.g., gallery).', 'elemacy'),
            'label_block' => true,
            'show_label'  => true,
            'type'        => Controls_Manager::TEXT,
            'default'     => 'gallery',
            'separator'   => 'after',
        ]);
    }

    public function get_settings_for_display($setting_key = null)
    {
        $settings = parent::get_settings_for_display();

        $settings['wp_gallery'] = $this->build_gallery_items($settings);

        return null !== $setting_key ? ($settings[$setting_key] ?? null) : $settings;
    }

    /**
     * Normalise ACF Gallery output (object / array / id return formats) into
     * the shape Widget_Image_Gallery::render expects: an array of items each
     * containing at least an `id` key (wp_list_pluck is used downstream).
     *
     * @return array<int, array{id: int}>
     */
    private function build_gallery_items(array $settings): array
    {
        $field_name = (string) ($settings['wp_gallery'] ?? '');

        if ('' === $field_name) {
            return [];
        }

        $rows  = AcfRepeaterResolver::resolve($field_name);
        $items = [];

        foreach ($rows as $row) {
            $id = $this->extract_attachment_id($row);

            if ($id > 0) {
                $items[] = ['id' => $id];
            }
        }

        return $items;
    }

    private function extract_attachment_id($row): int
    {
        if (is_array($row) && isset($row['ID'])) {
            return (int) $row['ID'];
        }

        if (is_array($row) && isset($row['id'])) {
            return (int) $row['id'];
        }

        if (is_object($row) && isset($row->ID)) {
            return (int) $row->ID;
        }

        if (is_numeric($row)) {
            return (int) $row;
        }

        return 0;
    }
}
