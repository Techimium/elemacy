<?php
namespace Elemacy\Core;

if (!defined('ABSPATH')) {
    exit;
}

class CompatibilityChecker
{
    /**
     * The oldest Elementor release the required fixtures have actually been
     * run against: the full real-WordPress integration suite, both real-
     * browser suites (popup hydration, AJAX Loop Grid pagination) and every
     * fast-harness check, all passing identically to the currently-tested
     * release below.
     */
    const MIN_ELEMENTOR_VERSION = '3.35.9';

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

        if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, self::MIN_ELEMENTOR_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_outdated_elementor_version']);
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

    public function admin_notice_outdated_elementor_version()
    {
        $message = sprintf(
            /* translators: 1: required Elementor version, 2: installed Elementor version */
            __('Elemacy requires Elementor version %1$s or greater. You have version %2$s installed.', 'elemacy'),
            '<strong>' . esc_html(self::MIN_ELEMENTOR_VERSION) . '</strong>',
            '<strong>' . esc_html(defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '?') . '</strong>'
        );

        printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
    }
}
