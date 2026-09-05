<?php
namespace Elemacy\Modules\DynamicTags\Tags\User;

use Elemacy\Modules\DynamicTags\Services\User;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class UserDescription extends Tag
{

	public function get_name()
	{
		return 'elemacy-user-description';
	}

	public function get_title()
	{
		return esc_html__('User Description', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::TEXT_CATEGORY];
	}

	public function render()
	{
		$user = User::get_current_user();

		if (!$user) {
			return;
		}

		$description = get_user_meta($user->ID, 'description', true);

		if (empty($description)) {
			return;
		}

		echo wp_kses_post($description);
	}
}
