<?php

namespace Elemacy\Modules\ThemeBuilder\Compatibility\Themes;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Compatibility\Contracts\ThemeCompatibilityInterface;
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

/**
 * Default compatibility.
 *
 * Replaces header/footer templates with Elemacy wrappers, then renders
 * the builder templates via wrapper actions.
 */
class DefaultCompatibility implements ThemeCompatibilityInterface
{
    public function register(ThemeBuilderManager $manager): void
    {
        if ($manager->get_header_id()) {
            add_action('get_header', function ($name = '') use ($manager) {
                require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-header.php';

                $templates = [];
                $name = (string) $name;
                if ('' !== $name) {
                    $templates[] = "header-{$name}.php";
                }
                $templates[] = 'header.php';

                remove_all_actions('wp_head');
                ob_start();
                locate_template($templates, true);
                ob_get_clean();
            }, 1);
        }

        if ($manager->get_footer_id()) {
            add_action('get_footer', function ($name = '') use ($manager) {
                require ELEMACY_PATH . 'src/Modules/ThemeBuilder/Views/theme-support-footer.php';

                $templates = [];
                $name = (string) $name;
                if ('' !== $name) {
                    $templates[] = "footer-{$name}.php";
                }
                $templates[] = 'footer.php';

                remove_all_actions('wp_footer');
                ob_start();
                locate_template($templates, true);
                ob_get_clean();
            }, 1);
        }
    }
}

