<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elementor\Controls_Manager;

/**
 * Elementor document for Search Results templates. Search results are a query, so
 * the preview runs a sample search and resolves the results title and post loop
 * against it.
 */
class SearchDocument extends PreviewablePageBase
{
    const PREVIEW_TERM = 'elemacy_preview_search_term';

    public static function get_type()
    {
        return 'elemacy_search';
    }

    public function get_name()
    {
        return 'elemacy_search';
    }

    public static function get_title()
    {
        return esc_html__('Search Results', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Search Results', 'elemacy');
    }

    public static function get_properties()
    {
        $properties = parent::get_properties();

        $properties['support_kit']     = true;
        $properties['show_in_library'] = false;
        $properties['admin_tab_group'] = '';

        return $properties;
    }

    protected function register_preview_controls(): void
    {
        $this->add_control(
            self::PREVIEW_TERM,
            [
                'label'       => esc_html__('Preview Search Term', 'elemacy'),
                'type'        => Controls_Manager::TEXT,
                'label_block' => true,
                'default'     => 'a',
                'description' => esc_html__('A sample term to preview this search results template with.', 'elemacy'),
            ]
        );
    }

    protected function preview_query_args(): array
    {
        $term = trim((string) $this->get_settings(self::PREVIEW_TERM));

        if ('' === $term) {
            $term = 'a';
        }

        return [
            's'                   => $term,
            'posts_per_page'      => (int) get_option('posts_per_page'),
            'ignore_sticky_posts' => true,
        ];
    }
}
