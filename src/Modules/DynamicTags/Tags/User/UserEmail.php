<?php
namespace Elemacy\Modules\DynamicTags\Tags\User;

use Elemacy\Modules\DynamicTags\Services\User;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Renders the currently-rendering user's email address, unrestricted — per
 * proposal.md/design.md D3, whether to bind this into a visible template is
 * the page author's decision, not this tag's to police.
 */
class UserEmail extends Tag
{

	public function get_name()
	{
		return 'elemacy-user-email';
	}

	public function get_title()
	{
		return esc_html__('User Email', 'elemacy');
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

		echo esc_html($user->user_email);
	}
}
