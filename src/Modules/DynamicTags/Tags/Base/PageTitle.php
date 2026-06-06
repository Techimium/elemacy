<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class PageTitle extends Tag
{

	public function get_name()
	{
		return 'elemacy-page-title';
	}

	public function get_title()
	{
		return esc_html__('Page Title', 'elemacy');
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
		echo wp_kses_post(wp_get_document_title());
	}
}
