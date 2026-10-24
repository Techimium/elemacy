<?php
namespace Elemacy\Modules\DynamicTags\Tags\Archive;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
    exit;
}

class ArchiveTitle extends Tag
{
    public function get_name()
    {
        return 'elemacy-archive-title';
    }

    public function get_title()
    {
        return esc_html__('Archive Title', 'elemacy');
    }

    public function get_group()
    {
        return 'elemacy';
    }

    public function get_categories()
    {
        return [Module::TEXT_CATEGORY];
    }

    protected function register_controls()
    {
        $this->add_control(
            'remove_prefix',
            [
                'label' => esc_html__('Remove Prefix', 'elemacy'),
                'description' => esc_html__('Remove the "Category:", "Tag:", "Author:" prefix.', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
    }

    public function render()
    {
        // WordPress's archive title has no search case and would say "Archives".
        // The heading names the search, so Remove Prefix (which strips an archive
        // kind's prefix) does not apply. get_search_query() is already escaped.
        if (is_search()) {
            /* translators: %s: the visitor's search term. */
            echo wp_kses_post(sprintf(esc_html__('Search Results for: %s', 'elemacy'), get_search_query()));

            return;
        }

        $title = get_the_archive_title();

        if ('yes' === $this->get_settings('remove_prefix')) {
            $title = preg_replace('/^[^:]+:\s*/', '', $title);
        }

        echo wp_kses_post($title);
    }
}
