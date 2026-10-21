<?php

namespace Elemacy\Loop\Sources\Users;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Access\PublicLoopLimits;

/**
 * The Users source's public-context policy, kept free of any Elementor
 * widget/document dependency; UsersDataSource delegates get_public_settings()
 * to an instance of this class.
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
        PublicLoopLimits::assert_scalars($settings, [UsersSettings::NUMBER, UsersSettings::ORDER_BY, UsersSettings::ORDER, UsersSettings::EXCLUDE_CURRENT]);
        $roles = $settings[UsersSettings::ROLES] ?? UsersSettings::DEFAULT_ROLES;
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
        $saved = $settings + UsersSettings::defaults();
        $saved[UsersSettings::ROLES] = $roles;
        $saved[UsersSettings::NUMBER] = PublicLoopLimits::page_size($saved[UsersSettings::NUMBER]);
        $saved[UsersSettings::CURRENT_USER_ID] = get_current_user_id();
        return $this->query->sanitize_ajax_settings($saved, $page);
    }
}
