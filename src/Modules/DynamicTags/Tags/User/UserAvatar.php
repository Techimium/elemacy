<?php
namespace Elemacy\Modules\DynamicTags\Tags\User;

use Elemacy\Modules\DynamicTags\Services\User;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class UserAvatar extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-user-avatar';
	}

	public function get_title()
	{
		return esc_html__('User Avatar', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::IMAGE_CATEGORY,
			Module::MEDIA_CATEGORY,
		];
	}

	public function get_value(array $options = [])
	{
		$user = User::get_current_user();

		if (!$user) {
			return [];
		}

		return [
			'id' => 0,
			'url' => get_avatar_url($user->ID, ['size' => 512]),
		];
	}
}
