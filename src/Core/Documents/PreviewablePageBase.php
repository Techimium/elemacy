<?php

namespace Elemacy\Core\Documents;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elementor\Controls_Manager;
use Elementor\Core\DocumentTypes\PageBase;

/**
 * Base for library documents that render against real content in the editor
 * preview. Subclasses add their own "Preview Settings" pickers and resolve the
 * content to preview; the shared PreviewManager swaps the global post/query for
 * that content while the document renders, so dynamic widgets and tags show real
 * data — like Elementor Pro's theme-builder preview.
 */
abstract class PreviewablePageBase extends PageBase
{
    const PREVIEW_SECTION = 'elemacy_preview_section';
    const PREVIEW_APPLY   = 'elemacy_preview_apply';

    // The Apply button dispatches this on Elementor's editor channel; preview-bridge.js listens.
    const APPLY_EVENT = 'elemacy:preview:apply';

    /**
     * Subclasses add their content pickers here (post selector, search term, …).
     */
    abstract protected function register_preview_controls(): void;

    protected function register_controls()
    {
        parent::register_controls();

        $this->register_preview_section();
    }

    /**
     * The single post whose context the preview renders in (loop item, single).
     * Return 0 when the type previews a query rather than one post.
     */
    public function get_preview_post_id(): int
    {
        return 0;
    }

    /**
     * WP_Query args for query-context previews (archive, search, …), passed
     * through the shared filter. Empty for single-post previews, which use
     * get_preview_post_id() instead.
     *
     * @return array<string, mixed>
     */
    public function get_preview_query_args(): array
    {
        return (array) apply_filters(Hooks::PREVIEW_QUERY_ARGS_FILTER, $this->preview_query_args(), $this);
    }

    /**
     * Subclasses that preview a query (archive, search) return their base
     * WP_Query args here; single-post types leave it empty.
     *
     * @return array<string, mixed>
     */
    protected function preview_query_args(): array
    {
        return [];
    }

    /**
     * Render the shared "Preview Settings" section. Subclasses call this from
     * register_controls() after their own controls so it sits in the Settings tab.
     */
    protected function register_preview_section(): void
    {
        $this->start_controls_section(
            self::PREVIEW_SECTION,
            [
                'label' => esc_html__('Preview Settings', 'elemacy'),
                'tab'   => Controls_Manager::TAB_SETTINGS,
            ]
        );

        $this->register_preview_controls();

        $this->add_control(
            self::PREVIEW_APPLY,
            [
                'type'        => Controls_Manager::BUTTON,
                'text'        => esc_html__('Apply & Preview', 'elemacy'),
                'event'       => self::APPLY_EVENT,
                'button_type' => 'success',
                'separator'   => 'before',
                'description' => esc_html__('Preview this template with the selected content.', 'elemacy'),
            ]
        );

        $this->end_controls_section();
    }
}
