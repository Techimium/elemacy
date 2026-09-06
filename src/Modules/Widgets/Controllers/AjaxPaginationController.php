<?php

namespace Elemacy\Modules\Widgets\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Core\Http\SiteRequest as Request;
use Elemacy\Modules\Widgets\Services\LoopContext;
use Elemacy\Modules\Widgets\Services\LoopDataSourceRegistry;
use Elemacy\Modules\Widgets\Services\LoopItemStyles;
use Elemacy\Modules\Widgets\Widgets\LoopGrid;
use Elemacy\TemplateLibrary\Services\BlockTemplateService;
use Elementor\Plugin;

class AjaxPaginationController
{
    public function index(Request $request)
    {
        if (empty($request->get_string('settings')) || empty($request->get_int('paged'))) {
            throw new ValidationException(esc_html__('Missing required parameters.', 'elemacy'));
        }

        $settings = json_decode($request->get_string('settings'), true);

        if (!$settings) {
            throw new ValidationException(esc_html__('Invalid settings format.', 'elemacy'));
        }

        $paged = max(1, $request->get_int('paged'));

        $data_source_key = isset($settings['data_source']) ? sanitize_key((string) $settings['data_source']) : 'posts';
        $source = LoopDataSourceRegistry::instance()->get($data_source_key);

        if (!$source) {
            wp_send_json_error(esc_html__('The selected data source is not available.', 'elemacy'));
        }

        $item_settings = $source->sanitize_ajax_settings($settings, $paged);

        $result = $source->get_items($item_settings);

        if (empty($result->items)) {
            wp_send_json_error(esc_html__('No posts found.', 'elemacy'));
        }

        $template_id = isset($settings['template_id']) ? (int) $settings['template_id'] : 0;

        // The template id round-trips through the browser, so confirm it is a real
        // library block item before handing it to Elementor to render — never an
        // arbitrary (possibly private) post id.
        if ($template_id <= 0 || !(new BlockTemplateService())->get($template_id)) {
            throw new ValidationException(esc_html__('Invalid loop template.', 'elemacy'));
        }

        ob_start();

        $loop_item_styles = new LoopItemStyles();
        $loop_item_styles->print_base_css($template_id);

        foreach ($result->items as $item) {
            LoopContext::push($item);

            try {
                $item->enter();

                try {
                    $loop_item_styles->print_item_css($template_id, $item->get_identity());

                    echo '<div class="elemacy-loop-item elemacy-loop-item-' . esc_attr($item->get_identity()) . '">';
                    echo Plugin::instance()->frontend->get_builder_content_for_display($template_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '</div>';
                } finally {
                    $item->exit();
                }
            } finally {
                LoopContext::pop();
            }
        }

        $items_html = ob_get_clean();

        // Pagination HTML
        ob_start();
        if (!empty($settings['pagination_type']) && $result->max_num_pages > 1) {
            LoopGrid::render_pagination_html($settings, $result->max_num_pages, $paged);
        }
        $pagination_html = ob_get_clean();

        wp_send_json_success([
            'items' => $items_html,
            'pagination' => $pagination_html,
        ]);
    }
}
