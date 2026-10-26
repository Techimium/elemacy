<?php

namespace Elemacy\Loop\Sources\Users;

defined('ABSPATH') || exit;

/**
 * The Users source's setting names and defaults, written once. The names are
 * saved in sites' Elementor data, and Elementor stores nothing for a control
 * left at its default, so neither ever changes (loop-control-defaults.php
 * pins both). The control, the query and the public policy all read them
 * from here.
 */
final class UsersSettings
{
    public const ROLES = 'users_roles';
    public const NUMBER = 'users_number';
    public const ORDER_BY = 'users_orderby';
    public const ORDER = 'users_order';
    public const EXCLUDE_CURRENT = 'exclude_current_user';

    public const DEFAULT_ROLES = [];
    public const DEFAULT_NUMBER = 6;
    public const DEFAULT_ORDER_BY = 'display_name';
    public const DEFAULT_ORDER = 'ASC';
    public const DEFAULT_EXCLUDE_CURRENT = '';

    /** Not a control: the logged-in visitor the public policy adds for the query. */
    public const CURRENT_USER_ID = 'current_user_id';

    /** @return array<string, mixed> Each control's default, keyed by its setting name. */
    public static function defaults(): array
    {
        return [
            self::ROLES => self::DEFAULT_ROLES,
            self::NUMBER => self::DEFAULT_NUMBER,
            self::ORDER_BY => self::DEFAULT_ORDER_BY,
            self::ORDER => self::DEFAULT_ORDER,
            self::EXCLUDE_CURRENT => self::DEFAULT_EXCLUDE_CURRENT,
        ];
    }
}
