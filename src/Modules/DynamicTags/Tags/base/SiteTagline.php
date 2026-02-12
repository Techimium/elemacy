<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class SiteTagline extends Tag
{
	public function get_name()
	{
		return 'elemacy-site-tagline';
	}

	public function get_title()
	{
		return esc_html__('Site Tagline', 'elemacy');
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
		echo wp_kses_post(get_bloginfo('description'));
	}
}
