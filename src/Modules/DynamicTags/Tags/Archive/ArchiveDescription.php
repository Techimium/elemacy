<?php
namespace Elemacy\Modules\DynamicTags\Tags\Archive;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class ArchiveDescription extends Tag
{

	public function get_name()
	{
		return 'elemacy-archive-description';
	}

	public function get_title()
	{
		return esc_html__('Archive Description', 'elemacy');
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
		echo wp_kses_post(get_the_archive_description());
	}
}
