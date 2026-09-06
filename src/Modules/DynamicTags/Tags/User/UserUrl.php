<?php
namespace Elemacy\Modules\DynamicTags\Tags\User;

use Elemacy\Modules\DynamicTags\Services\User;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class UserUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-user-url';
	}

	public function get_title()
	{
		return esc_html__('User URL', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::URL_CATEGORY];
	}

	public function get_value(array $options = [])
	{
		$user = User::get_current_user();

		if (!$user) {
			return '';
		}

		return get_author_posts_url($user->ID);
	}
}
