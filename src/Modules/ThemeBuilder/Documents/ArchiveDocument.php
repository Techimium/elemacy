<?php

namespace Elemacy\Modules\ThemeBuilder\Documents;

defined('ABSPATH') || exit;

use Elemacy\Elementor\Controls\Query\QueryControl;
use Elemacy\Elementor\Controls\Query\QueryService;
use Elemacy\Elementor\Documents\PreviewablePageBase;
use Elemacy\Modules\ThemeBuilder\Services\ArchivePreviewQuery;
use Elementor\Controls_Manager;
use WP_Post_Type;

/** Elementor document for Archive templates. */
class ArchiveDocument extends PreviewablePageBase
{
    public const PREVIEW_POST_TYPE = 'elemacy_preview_post_type';
    public const PREVIEW_TYPE      = 'elemacy_preview_type';
    public const PREVIEW_TERM      = 'elemacy_preview_term';
    public const PREVIEW_AUTHOR    = 'elemacy_preview_author';
    public const PREVIEW_MONTH     = 'elemacy_preview_month';

    public static function get_type()
    {
        return 'elemacy_archive';
    }

    public function get_name()
    {
        return 'elemacy_archive';
    }

    public static function get_title()
    {
        return esc_html__('Archive', 'elemacy');
    }

    public static function get_plural_title()
    {
        return esc_html__('Archives', 'elemacy');
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
            self::PREVIEW_TYPE,
            [
                'label'       => esc_html__('Preview Type', 'elemacy'),
                'type'        => Controls_Manager::SELECT,
                'label_block' => true,
                'options'     => [
                    ArchivePreviewQuery::TYPE_POST_TYPE => esc_html__('Post Type Archive', 'elemacy'),
                    ArchivePreviewQuery::TYPE_TERM      => esc_html__('Taxonomy Term (Category, Tag, ...)', 'elemacy'),
                    ArchivePreviewQuery::TYPE_AUTHOR    => esc_html__('Author', 'elemacy'),
                    ArchivePreviewQuery::TYPE_MONTH     => esc_html__('Month', 'elemacy'),
                ],
                'default'     => ArchivePreviewQuery::TYPE_POST_TYPE,
                'description' => esc_html__('Choose which kind of archive to preview this template as.', 'elemacy'),
            ]
        );

        $this->add_control(
            self::PREVIEW_POST_TYPE,
            [
                'label'       => esc_html__('Post Type', 'elemacy'),
                'type'        => Controls_Manager::SELECT2,
                'label_block' => true,
                'options'     => $this->post_type_options(),
                'default'     => 'post',
                'description' => esc_html__('Choose a post type to preview this archive with.', 'elemacy'),
                'condition'   => [self::PREVIEW_TYPE => ArchivePreviewQuery::TYPE_POST_TYPE],
            ]
        );

        $this->add_control(
            self::PREVIEW_TERM,
            [
                'label'        => esc_html__('Term', 'elemacy'),
                'type'         => QueryControl::TYPE,
                'label_block'  => true,
                'placeholder'  => esc_html__('Search for a category, tag or term', 'elemacy'),
                'autocomplete' => [
                    'object' => QueryService::OBJECT_TERM,
                    'query'  => [],
                ],
                'default'      => '',
                'description'  => esc_html__('The category, tag or other term whose archive to preview.', 'elemacy'),
                'condition'    => [self::PREVIEW_TYPE => ArchivePreviewQuery::TYPE_TERM],
            ]
        );

        $this->add_control(
            self::PREVIEW_AUTHOR,
            [
                'label'        => esc_html__('Author', 'elemacy'),
                'type'         => QueryControl::TYPE,
                'label_block'  => true,
                'placeholder'  => esc_html__('Search for an author', 'elemacy'),
                'autocomplete' => [
                    'object' => QueryService::OBJECT_USER,
                    'query'  => [],
                ],
                'default'      => '',
                'description'  => esc_html__('The author whose archive to preview.', 'elemacy'),
                'condition'    => [self::PREVIEW_TYPE => ArchivePreviewQuery::TYPE_AUTHOR],
            ]
        );

        $this->add_control(
            self::PREVIEW_MONTH,
            [
                'label'          => esc_html__('Month', 'elemacy'),
                'type'           => Controls_Manager::DATE_TIME,
                'picker_options' => ['enableTime' => false],
                'default'        => '',
                'description'    => esc_html__('Pick any day in the month to preview that monthly archive.', 'elemacy'),
                'condition'      => [self::PREVIEW_TYPE => ArchivePreviewQuery::TYPE_MONTH],
            ]
        );
    }

    protected function preview_query_args(): array
    {
        return (new ArchivePreviewQuery())->build([
            'type'      => $this->get_settings(self::PREVIEW_TYPE),
            'post_type' => $this->get_settings(self::PREVIEW_POST_TYPE),
            'term'      => $this->get_settings(self::PREVIEW_TERM),
            'author'    => $this->get_settings(self::PREVIEW_AUTHOR),
            'month'     => $this->get_settings(self::PREVIEW_MONTH),
        ]);
    }

    /**
     * Public, archive-capable post types as SELECT2 options. Built in-memory, so
     * no DB query and no editor-only gate is needed.
     *
     * @return array<string, string>
     */
    protected function post_type_options(): array
    {
        $options = [];

        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            if (!$post_type instanceof WP_Post_Type || 'attachment' === $post_type->name) {
                continue;
            }

            $options[$post_type->name] = $post_type->labels->name;
        }

        return $options;
    }
}
