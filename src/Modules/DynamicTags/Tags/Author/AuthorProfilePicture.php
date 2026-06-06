<?php
namespace Elemacy\Modules\DynamicTags\Tags\Author;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class AuthorProfilePicture extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-author-profile-picture';
	}

	public function get_title()
	{
		return esc_html__('Author Profile Picture', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::IMAGE_CATEGORY,
			Module::MEDIA_CATEGORY,
		];
	}

	public function get_value(array $options = [])
	{
		$author_id = $this->get_author_id();

		if (!$author_id) {
			return [];
		}

		return [
			'id' => 0,
			'url' => get_avatar_url($author_id, ['size' => 512]),
		];
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
