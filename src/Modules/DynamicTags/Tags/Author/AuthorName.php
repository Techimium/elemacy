<?php
namespace Elemacy\Modules\DynamicTags\Tags\Author;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class AuthorName extends Tag
{

	public function get_name()
	{
		return 'elemacy-author-name';
	}

	public function get_title()
	{
		return esc_html__('Author Name', 'elemacy');
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
		$author_id = $this->get_author_id();

		if (!$author_id) {
			return;
		}

		echo esc_html(get_the_author_meta('display_name', $author_id));
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
