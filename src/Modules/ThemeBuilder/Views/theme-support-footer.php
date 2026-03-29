<?php
use Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

$elemacy_manager = ThemeBuilderManager::instance();
$elemacy_manager->render_template($elemacy_manager->get_footer_id()); ?>

<?php wp_footer(); ?>

</body>

</html>
