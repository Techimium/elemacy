<?php

namespace Elemacy\Loop\Sources;

defined('ABSPATH') || exit;

use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\UserLoopItem;
use Elemacy\Loop\Pagination\PublicLoopLimits;
use WP_User_Query;

/**
 * The Users source's runtime query execution, kept free of any Elementor
 * widget/document dependency; UsersDataSource delegates get_items() and
 * sanitize_ajax_settings() to an instance of this class.
 */
class UsersQuery
{
    public function get_items(array $settings): LoopResultDTO
    {
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, 'users_number');
        $query = new WP_User_Query($this->build_query_args($settings));

        $items = [];
        foreach ($query->get_results() as $user) {
            $items[] = new UserLoopItem($user);
        }

        $number = (int) ($settings['users_number'] ?? 0);

        // Unlike WP_Term_Query, WP_User_Query exposes a total directly from
        // the same query object when 'count_total' => true is passed — no
        // second query needed.
        $total_items = $number > 0 ? (int) $query->get_total() : count($items);
        $max_num_pages = $number > 0 ? (int) ceil($total_items / $number) : 1;

        return new LoopResultDTO($items, $total_items, $max_num_pages, true);
    }

    protected function build_query_args(array $settings): array
    {
        $args = [
            'orderby' => $settings['users_orderby'] ?? 'display_name',
            'order' => $settings['users_order'] ?? 'ASC',
            'count_total' => true,
        ];

        $roles = $settings['users_roles'] ?? [];
        if (!empty($roles) && is_array($roles)) {
            $args['role__in'] = array_values($roles);
        }

        $number = (int) ($settings['users_number'] ?? 0);

        if ($number > 0) {
            $args['number'] = $number;

            if (!empty($settings['pagination_type'])) {
                $paged = $settings['paged']
                    ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
                $args['offset'] = ((int) $paged - 1) * $number;
            }
        }

        if ('yes' === ($settings['exclude_current_user'] ?? '')) {
            if (array_key_exists('current_user_id', $settings)) {
                if (!empty($settings['current_user_id'])) {
                    $args['exclude'] = [(int) $settings['current_user_id']];
                }
            } elseif (is_user_logged_in()) {
                $args['exclude'] = [get_current_user_id()];
            }
        }

        return $args;
    }

    public function sanitize_ajax_settings(array $raw_settings, int $paged): array
    {
        $roles = [];
        if (!empty($raw_settings['users_roles']) && is_array($raw_settings['users_roles'])) {
            $registered_roles = array_keys(wp_roles()->get_names());
            foreach ($raw_settings['users_roles'] as $role) {
                $role = sanitize_key((string) $role);
                if (in_array($role, $registered_roles, true)) {
                    $roles[] = $role;
                }
            }
        }

        $allowed_orderby = ['display_name', 'registered', 'post_count'];
        $orderby = isset($raw_settings['users_orderby']) ? sanitize_key((string) $raw_settings['users_orderby']) : 'display_name';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'display_name';
        }

        $order = isset($raw_settings['users_order']) ? strtoupper(sanitize_text_field((string) $raw_settings['users_order'])) : 'ASC';
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        $number = isset($raw_settings['users_number']) ? (int) $raw_settings['users_number'] : 6;
        $number = max(0, min(100, $number));

        return [
            'users_roles' => $roles,
            'users_number' => $number,
            'users_orderby' => $orderby,
            'users_order' => $order,
            'exclude_current_user' => 'yes' === ($raw_settings['exclude_current_user'] ?? '') ? 'yes' : 'no',
            'current_user_id' => !empty($raw_settings['current_user_id']) ? (int) $raw_settings['current_user_id'] : 0,
            'pagination_type' => 'ajax',
            'paged' => $paged,
        ];
    }
}
