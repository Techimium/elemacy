<?php
namespace Elemacy\Modules\DynamicTags\Tags\Taxonomy;

use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class TermUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-term-url';
	}

	public function get_title()
	{
		return esc_html__('Term URL', 'elemacy');
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
		$term = Taxonomy::get_current_term();

		if (!$term) {
			return '';
		}

		$link = get_term_link($term);

		return is_wp_error($link) ? '' : $link;
	}
}
