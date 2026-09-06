<?php
namespace Elemacy\Modules\DynamicTags\Tags\Taxonomy;

use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class TermCount extends Tag
{

	public function get_name()
	{
		return 'elemacy-term-count';
	}

	public function get_title()
	{
		return esc_html__('Term Count', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::NUMBER_CATEGORY, Module::TEXT_CATEGORY];
	}

	public function render()
	{
		$term = Taxonomy::get_current_term();

		if (!$term) {
			return;
		}

		echo esc_html((string) $term->count);
	}
}
