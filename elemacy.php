<?php

/**
 * Plugin Name: Elemacy
 * Plugin URI: https://elemacy.com
 * Description: Elemacy is a WordPress plugin to extend elementor.
 * Version: 1.0.0
 * Author: Techimium
 * Author URI: https://techimium.com
 * Text Domain: elemacy
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

use Elemacy\Core\Elemacy;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ELEMACY_ENV')) {
    define('ELEMACY_ENV', 'dev');
}

define('ELEMACY_VERSION', '1.0.0');
define('ELEMACY_FILE', __FILE__);
define('ELEMACY_PLUGIN_BASE', plugin_basename(ELEMACY_FILE));
define('ELEMACY_PATH', plugin_dir_path(ELEMACY_FILE));
define('ELEMACY_URL', plugin_dir_url(ELEMACY_FILE));

require_once __DIR__ . '/vendor/autoload.php';

Elemacy::boot();
