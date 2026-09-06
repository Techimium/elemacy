<?php
namespace Elemacy\Modules\DynamicTags\Tags\Author;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class AuthorUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-author-url';
	}

	public function get_title()
	{
		return esc_html__('Author URL', 'elemacy');
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
		$author_id = $this->get_author_id();

		if (!$author_id) {
			return '';
		}

		return get_author_posts_url($author_id);
	}

	protected function get_author_id()
	{
		$post = get_post();

		if ($post) {
			return $post->post_author;
		}

		$author = get_queried_object();

		return ($author instanceof \WP_User) ? $author->ID : 0;
	}
}
