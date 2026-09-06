<?php
namespace Elemacy\Modules\DynamicTags\Tags\Taxonomy;

use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class TermName extends Tag
{

	public function get_name()
	{
		return 'elemacy-term-name';
	}

	public function get_title()
	{
		return esc_html__('Term Name', 'elemacy');
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
		$term = Taxonomy::get_current_term();

		if (!$term) {
			return;
		}

		echo esc_html($term->name);
	}
}
