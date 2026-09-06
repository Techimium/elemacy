<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Support\Utils;

class PluginLinks
{
    public function __construct()
    {
        add_filter('plugin_action_links_' . ELEMACY_PLUGIN_BASE, [$this, 'add_action_links']);
    }

    public function add_action_links(array $links): array
    {
        if (Utils::is_pro_active()) {
            return $links;
        }

        $links['elemacy_pro'] = sprintf(
            '<a href="%s" target="_blank" rel="noopener" class="elemacy-plugin-gopro">%s</a>',
            esc_url('https://elemacy.com'),
            esc_html__('Get Elemacy Pro', 'elemacy')
        );

        return $links;
    }
}
