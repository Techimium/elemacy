<?php

namespace Elemacy\Loop\Sources\Users;

defined('ABSPATH') || exit;

use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\Items\UserLoopItem;
use Elemacy\Loop\Access\PublicLoopLimits;
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
        $settings = PublicLoopLimits::bound_ajax_page_size($settings, UsersSettings::NUMBER);
        $query = new WP_User_Query($this->build_query_args($settings));

        $items = [];
        foreach ($query->get_results() as $user) {
            $items[] = new UserLoopItem($user);
        }

        $number = (int) ($settings[UsersSettings::NUMBER] ?? 0);

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
            'orderby' => $settings[UsersSettings::ORDER_BY] ?? UsersSettings::DEFAULT_ORDER_BY,
            'order' => $settings[UsersSettings::ORDER] ?? UsersSettings::DEFAULT_ORDER,
            'count_total' => true,
        ];

        $roles = $settings[UsersSettings::ROLES] ?? UsersSettings::DEFAULT_ROLES;
        if (!empty($roles) && is_array($roles)) {
            $args['role__in'] = array_values($roles);
        }

        $number = (int) ($settings[UsersSettings::NUMBER] ?? 0);

        if ($number > 0) {
            $args['number'] = $number;

            if (!empty($settings['pagination_type'])) {
                $paged = $settings['paged']
                    ?? (get_query_var('paged') ? get_query_var('paged') : (get_query_var('page') ? get_query_var('page') : 1));
                $args['offset'] = ((int) $paged - 1) * $number;
            }
        }

        if ('yes' === ($settings[UsersSettings::EXCLUDE_CURRENT] ?? '')) {
            if (array_key_exists(UsersSettings::CURRENT_USER_ID, $settings)) {
                if (!empty($settings[UsersSettings::CURRENT_USER_ID])) {
                    $args['exclude'] = [(int) $settings[UsersSettings::CURRENT_USER_ID]];
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
        if (!empty($raw_settings[UsersSettings::ROLES]) && is_array($raw_settings[UsersSettings::ROLES])) {
            $registered_roles = array_keys(wp_roles()->get_names());
            foreach ($raw_settings[UsersSettings::ROLES] as $role) {
                $role = sanitize_key((string) $role);
                if (in_array($role, $registered_roles, true)) {
                    $roles[] = $role;
                }
            }
        }

        $allowed_orderby = ['display_name', 'registered', 'post_count'];
        $orderby = isset($raw_settings[UsersSettings::ORDER_BY]) ? sanitize_key((string) $raw_settings[UsersSettings::ORDER_BY]) : UsersSettings::DEFAULT_ORDER_BY;
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = UsersSettings::DEFAULT_ORDER_BY;
        }

        $order = isset($raw_settings[UsersSettings::ORDER]) ? strtoupper(sanitize_text_field((string) $raw_settings[UsersSettings::ORDER])) : UsersSettings::DEFAULT_ORDER;
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = UsersSettings::DEFAULT_ORDER;
        }

        $number = isset($raw_settings[UsersSettings::NUMBER]) ? (int) $raw_settings[UsersSettings::NUMBER] : UsersSettings::DEFAULT_NUMBER;
        $number = max(0, min(100, $number));

        return [
            UsersSettings::ROLES => $roles,
            UsersSettings::NUMBER => $number,
            UsersSettings::ORDER_BY => $orderby,
            UsersSettings::ORDER => $order,
            UsersSettings::EXCLUDE_CURRENT => 'yes' === ($raw_settings[UsersSettings::EXCLUDE_CURRENT] ?? '') ? 'yes' : 'no',
            UsersSettings::CURRENT_USER_ID => !empty($raw_settings[UsersSettings::CURRENT_USER_ID]) ? (int) $raw_settings[UsersSettings::CURRENT_USER_ID] : 0,
            'pagination_type' => 'ajax',
            'paged' => $paged,
        ];
    }
}
