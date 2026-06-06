<?php
namespace Elemacy\Modules\DynamicTags\Tags\Archive;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class ArchiveUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-archive-url';
	}

	public function get_title()
	{
		return esc_html__('Archive URL', 'elemacy');
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
		$queried_object = get_queried_object();

		if ($queried_object instanceof \WP_Term) {
			$term_link = get_term_link($queried_object);

			return is_wp_error($term_link) ? '' : $term_link;
		}

		if ($queried_object instanceof \WP_User) {
			return get_author_posts_url($queried_object->ID);
		}

		if ($queried_object instanceof \WP_Post_Type) {
			return get_post_type_archive_link($queried_object->name);
		}

		return '';
	}
}
