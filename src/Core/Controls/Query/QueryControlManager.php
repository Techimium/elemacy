<?php

namespace Elemacy\Core\Controls\Query;

defined('ABSPATH') || exit;

use Elemacy\Core\DTO\QueryResultDTO;
use Elemacy\Support\Utils;
use Elementor\Controls_Manager;

/**
 * Wires the reusable elemacy_query live-search control: registers the control type,
 * the editor-AJAX endpoints that feed its search/value-title requests, and the
 * editor script that turns the base select2 view into an autocomplete one.
 *
 * The AJAX action tags below are also referenced verbatim in
 * assets/editor/query-control.js — keep them in sync.
 */
class QueryControlManager
{
    const AUTOCOMPLETE_ACTION = 'elemacy_query_autocomplete';
    const TITLES_ACTION       = 'elemacy_query_titles';

    const SCRIPT_HANDLE = 'elemacy-query-control';
    const RELATIVE_PATH = 'assets/editor/query-control.js';

    protected QueryService $service;

    public function __construct()
    {
        $this->service = new QueryService();

        add_action('elementor/controls/register', [$this, 'register_control']);
        add_action('elementor/ajax/register_actions', [$this, 'register_ajax_actions']);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue']);
    }

    public function register_control(Controls_Manager $controls_manager): void
    {
        $controls_manager->register(new QueryControl());
    }

    /**
     * @param \Elementor\Core\Common\Modules\Ajax\Module $ajax_manager
     */
    public function register_ajax_actions($ajax_manager): void
    {
        $ajax_manager->register_ajax_action(self::AUTOCOMPLETE_ACTION, [$this, 'handle_autocomplete']);
        $ajax_manager->register_ajax_action(self::TITLES_ACTION, [$this, 'handle_titles']);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{results: array<int, array{id: int|string, text: string}>}
     */
    public function handle_autocomplete(array $data): array
    {
        if (!current_user_can('edit_posts')) {
            return ['results' => []];
        }

        $autocomplete = isset($data['autocomplete']) && is_array($data['autocomplete']) ? $data['autocomplete'] : [];
        $term         = isset($data['q']) ? sanitize_text_field((string) $data['q']) : '';

        return ['results' => QueryResultDTO::to_arrays($this->service->search($autocomplete, $term))];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, string>
     */
    public function handle_titles(array $data): array
    {
        if (!current_user_can('edit_posts')) {
            return [];
        }

        $get_titles = isset($data['get_titles']) && is_array($data['get_titles']) ? $data['get_titles'] : [];
        $ids        = isset($data['id']) ? (array) $data['id'] : [];

        return $this->service->titles($get_titles, $ids);
    }

    public function enqueue(): void
    {
        $file    = Utils::get_plugin_path(self::RELATIVE_PATH);
        $version = is_readable($file) && filemtime($file) ? (string) filemtime($file) : ELEMACY_VERSION;

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            Utils::get_plugin_url(self::RELATIVE_PATH),
            ['elementor-editor'],
            $version,
            true
        );
    }
}
