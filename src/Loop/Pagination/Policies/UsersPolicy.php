<?php

namespace Elemacy\Loop\Pagination\Policies;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\UsersQuery;
use Elemacy\Modules\Widgets\Support\PublicLoopLimits;

/**
 * The Users source's public-context policy (design.md D7) — extracted from
 * Modules\Widgets\DataSources\UsersDataSource so it can be exercised without
 * an Elementor widget/document instance. Behavior is unchanged from before
 * the move; UsersDataSource itself now delegates get_public_settings() to
 * an instance of this class. Depends on UsersQuery directly (constructor
 * injection, defaulting to a fresh instance), the same "validate then run
 * through the query's own sanitize_ajax_settings()" pattern
 * PostsPolicy/TermsPolicy already established.
 */
class UsersPolicy implements PublicLoopSourcePolicyInterface
{
    protected UsersQuery $query;

    public function __construct(?UsersQuery $query = null)
    {
        $this->query = $query ?? new UsersQuery();
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, ['users_number', 'users_orderby', 'users_order', 'exclude_current_user']);
        $roles = $settings['users_roles'] ?? [];
        if (!is_array($roles)) {
            throw PublicLoopLimits::invalid_settings();
        }
        $registered = array_keys(wp_roles()->get_names());
        foreach ($roles as $role) {
            // Dropping an unknown role would broaden an authored restriction.
            if (!is_string($role) || !in_array($role, $registered, true)) {
                throw PublicLoopLimits::invalid_settings();
            }
        }
        $saved = $settings;
        $saved['users_roles'] = $roles;
        $saved['users_number'] = PublicLoopLimits::page_size($settings['users_number'] ?? null);
        $saved['current_user_id'] = get_current_user_id();
        return $this->query->sanitize_ajax_settings($saved, $page);
    }
}
