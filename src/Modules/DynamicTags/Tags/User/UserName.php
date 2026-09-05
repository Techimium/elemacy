<?php
namespace Elemacy\Modules\DynamicTags\Tags\User;

use Elemacy\Modules\DynamicTags\Services\User;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class UserName extends Tag
{

	public function get_name()
	{
		return 'elemacy-user-name';
	}

	public function get_title()
	{
		return esc_html__('User Name', 'elemacy');
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

		echo esc_html($user->display_name);
	}
}
