<?php
namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityChecker
{
    protected static $instance = null;

    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function check()
    {
        if (!did_action('elementor/loaded')) {
            (new DependencyNotice('elementor', 'elementor/elementor.php', 'Elementor'))->register();
            return false;
        }

        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_php_version']);
            add_action('admin_init', [$this, 'plugin_deactivate']);
            return false;
        }

        return true;
    }

    public function plugin_deactivate()
    {
        deactivate_plugins(ELEMACY_PLUGIN_BASE);
    }

    public function admin_notice_missing_php_version()
    {
        $message = sprintf(
            /* translators: %s: PHP version */
            __('Elemacy requires PHP version %s or greater.', 'elemacy'),
            '<strong>7.4</strong>'
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
}
