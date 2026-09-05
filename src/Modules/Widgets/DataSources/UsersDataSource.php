<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Exceptions\ValidationException;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elemacy\Modules\Widgets\DTO\LoopResultDTO;
use Elemacy\Modules\Widgets\LoopItems\UserLoopItem;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_User_Query;

/**
 * Built-in Users data source: loops over WP_User accounts instead of posts
 * or terms, so a Loop Grid/Carousel can build a team directory, author grid,
 * or staff listing. See openspec/changes/loop-data-source-users/design.md
 * for the role-filtering, pagination-total, and no-op enter()/exit()
 * decisions.
 */
class UsersDataSource implements LoopDataSourceInterface
{
    public function get_key(): string
    {
        return 'users';
    }

    public function get_label(): string
    {
        return esc_html__('Users', 'elemacy');
    }

    protected function get_roles(): array
    {
        $options = [];

        foreach (wp_roles()->get_names() as $role => $label) {
            $options[$role] = translate_user_role($label);
        }

        return $options;
    }

    public function register_controls(Widget_Base $widget): void
    {
        $condition = ['data_source' => $this->get_key()];

        $widget->add_control(
            'users_roles',
            [
                'label' => esc_html__('Roles', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'multiple' => true,
                'label_block' => true,
                'options' => $this->get_roles(),
                'default' => [],
                'description' => esc_html__('Leave empty to include users of any role.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'users_number',
            [
                'label' => esc_html__('Number of Users', 'elemacy'),
                'type' => Controls_Manager::NUMBER,
                'default' => 6,
                'description' => esc_html__('Leave empty to show all users.', 'elemacy'),
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'users_orderby',
            [
                'label' => esc_html__('Order By', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'display_name',
                'options' => [
                    'display_name' => esc_html__('Display Name', 'elemacy'),
                    'registered' => esc_html__('Registration Date', 'elemacy'),
                    'post_count' => esc_html__('Post Count', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'users_order',
            [
                'label' => esc_html__('Order', 'elemacy'),
                'type' => Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => [
                    'ASC' => esc_html__('ASC', 'elemacy'),
                    'DESC' => esc_html__('DESC', 'elemacy'),
                ],
                'condition' => $condition,
            ]
        );

        $widget->add_control(
            'exclude_current_user',
            [
                'label' => esc_html__('Exclude Current User', 'elemacy'),
                'type' => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default' => '',
                'description' => esc_html__('When the visitor is logged in, exclude their own account from the results.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    public function get_items(array $settings): LoopResultDTO
    {
        $query = new WP_User_Query($this->build_query_args($settings));

        $items = [];
        foreach ($query->get_results() as $user) {
            $items[] = new UserLoopItem($user);
        }

        $number = (int) ($settings['users_number'] ?? 0);

        // Unlike WP_Term_Query, WP_User_Query exposes a total directly from
        // the same query object when 'count_total' => true is passed — no
        // second query needed (design.md D2).
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

    public function get_ajax_payload(array $settings): array
    {
        $payload = [
            'users_roles' => $settings['users_roles'] ?? [],
            'users_number' => $settings['users_number'] ?? 6,
            'users_orderby' => $settings['users_orderby'] ?? 'display_name',
            'users_order' => $settings['users_order'] ?? 'ASC',
            'exclude_current_user' => $settings['exclude_current_user'] ?? '',
        ];

        if ('yes' === ($settings['exclude_current_user'] ?? '') && is_user_logged_in()) {
            $payload['current_user_id'] = get_current_user_id();
        }

        return $payload;
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
