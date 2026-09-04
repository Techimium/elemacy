<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

class BBThemeCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_filter('fl_header_enabled', '__return_false');
            add_action('fl_before_header', function () use ($manager) {
                if (class_exists('FLTheme') && method_exists('FLTheme', 'get_setting')) {
                    $layout = \FLTheme::get_setting('fl-header-layout');
                    if ('none' === $layout || is_page_template('tpl-no-header-footer.php')) {
                        return;
                    }
                }

                echo '<header id="masthead" itemscope="itemscope" itemtype="https://schema.org/WPHeader">';
                $manager->render_header();
                echo '</header>';
            }, 10);
        }

        if ($manager->get_footer_id()) {
            add_filter('fl_footer_enabled', '__return_false');
            add_action('fl_after_content', function () use ($manager) {
                if (is_page_template('tpl-no-header-footer.php')) {
                    return;
                }

                echo '<footer itemscope="itemscope" itemtype="https://schema.org/WPFooter">';
                $manager->render_footer();
                echo '</footer>';
            }, 10);
        }
    }
}

