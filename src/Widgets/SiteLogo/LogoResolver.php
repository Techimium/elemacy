<?php

namespace Elemacy\Widgets\SiteLogo;

defined('ABSPATH') || exit;

/**
 * @internal
 */
class LogoResolver
{
    public function resolve(array $settings): int
    {
        $source = $settings['logo_source'] ?? 'custom_logo';

        switch ($source) {
            case 'custom_logo':
                return (int) get_theme_mod('custom_logo');
            case 'custom':
                return (int) ($settings['image']['id'] ?? 0);
            default:
                return 0;
        }
    }
}
