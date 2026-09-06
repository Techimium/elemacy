<?php
namespace Elemacy\Modules\DynamicTags\Tags\Taxonomy;

use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class TermDescription extends Tag
{

	public function get_name()
	{
		return 'elemacy-term-description';
	}

	public function get_title()
	{
		return esc_html__('Term Description', 'elemacy');
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

		$description = term_description($term->term_id);

		if (empty($description)) {
			return;
		}

		echo wp_kses_post($description);
	}
}
