<?php

namespace Elemacy\Modules\Widgets\DataSources;

defined('ABSPATH') || exit;

use Elemacy\Core\Documents\PreviewablePageBase;
use Elemacy\Loop\Contracts\LoopItemInterface;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\UsersQuery;
use Elemacy\Loop\DTO\LoopResultDTO;
use Elemacy\Loop\LoopItems\UserLoopItem;
use Elemacy\Loop\Pagination\Policies\UsersPolicy;
use Elemacy\Modules\Widgets\Contracts\LoopDataSourceInterface;
use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use WP_User;

/**
 * Built-in Users data source: loops over WP_User accounts instead of posts
 * or terms, so a Loop Grid/Carousel can build a team directory, author grid,
 * or staff listing. See openspec/changes/loop-data-source-users/design.md
 * for the role-filtering, pagination-total, and no-op enter()/exit()
 * decisions.
 *
 * The runtime query execution and public-context policy live in
 * Loop\DataSources\UsersQuery / Loop\Pagination\Policies\UsersPolicy
 * (design.md D7) — this class keeps only what needs an Elementor
 * widget/document instance plus this source's own identity, delegating
 * everything else to those two collaborators.
 */
class UsersDataSource implements LoopDataSourceInterface, PublicLoopSourcePolicyInterface
{
    protected UsersQuery $query;
    protected UsersPolicy $policy;

    public function __construct(?UsersQuery $query = null, ?UsersPolicy $policy = null)
    {
        $this->query = $query ?? new UsersQuery();
        $this->policy = $policy ?? new UsersPolicy($this->query);
    }

    public function get_key(): string
    {
        return 'users';
    }

    public function get_label(): string
    {
        return esc_html__('Users', 'elemacy');
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        return $this->policy->get_public_settings($settings, $context, $page);
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
        return $this->query->get_items($settings);
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
        return $this->query->sanitize_ajax_settings($raw_settings, $paged);
    }

    public function register_preview_controls(PreviewablePageBase $document): void
    {
        $condition = ['preview_data_source' => $this->get_key()];

        $document->add_control(
            'preview_user',
            [
                'label' => esc_html__('User', 'elemacy'),
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'options' => $this->get_preview_user_options(),
                'default' => '',
                'placeholder' => esc_html__('Auto (first user)', 'elemacy'),
                'description' => esc_html__('Leave empty to preview the first matching user.', 'elemacy'),
                'condition' => $condition,
            ]
        );
    }

    /**
     * Capped, server-populated SELECT2 — same accepted pattern as
     * AcfRepeaterDataSource::get_post_options() (no reusable AJAX
     * user-search endpoint exists to hook a custom control into).
     */
    protected function get_preview_user_options(): array
    {
        $users = get_users([
            'number' => 200,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_login'],
        ]);

        $options = [];

        foreach ($users as $user) {
            $options[$user->ID] = '' !== $user->display_name ? $user->display_name : $user->user_login;
        }

        return $options;
    }

    public function resolve_preview_item(array $settings): ?LoopItemInterface
    {
        $selected = (int) ($settings['preview_user'] ?? 0);

        if ($selected > 0) {
            $user = get_user_by('id', $selected);

            if ($user instanceof WP_User) {
                return new UserLoopItem($user);
            }
        }

        $users = get_users([
            'number' => 1,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);

        return $users ? new UserLoopItem($users[0]) : null;
    }
}
