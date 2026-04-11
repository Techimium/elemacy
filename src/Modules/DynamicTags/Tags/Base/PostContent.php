<?php

namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class PostContent extends Tag
{

	public function get_name()
	{
		return 'elemacy-post-content';
	}

	public function get_title()
	{
		return __('Post Content', 'elemacy');
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
		$content = !empty(get_the_content()) ? get_the_content() : $this->get_settings('fallback');

		echo wp_kses_post($content);
	}
}
