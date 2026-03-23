<?php
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

$manager = ThemeBuilderManager::instance();
$manager->render_template($manager->get_footer_id()); ?>

<?php wp_footer(); ?>

</body>

</html>
