<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class PostExcerpt extends Tag
{
	public function get_name()
	{
		return 'post-excerpt';
	}

	public function get_title()
	{
		return esc_html__('Post Excerpt', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	protected function register_controls()
	{

		$this->add_control(
			'max_length',
			[
				'label' => esc_html__('Excerpt Length', 'elemacy'),
				'type' => Controls_Manager::NUMBER,
			]
		);

		$this->add_control(
			'apply_to_post_content',
			[
				'label' => esc_html__('Apply to post content', 'elemacy'),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__('Yes', 'elemacy'),
				'label_off' => esc_html__('No', 'elemacy'),
				'default' => 'no',
			]
		);
	}

	public function get_categories()
	{
		return [Module::TEXT_CATEGORY];
	}

	public function should_get_excerpt_from_post_content($settings)
	{
		return $settings['apply_to_post_content'] === 'yes';
	}

	public function is_post_excerpt_valid($settings, $post)
	{
		if (!$post) {
			return false;
		}

		if (empty($post->post_excerpt) && !$this->should_get_excerpt_from_post_content($settings)) {
			return false;
		}

		if (empty($post->post_excerpt) && empty($post->post_content) && $this->should_get_excerpt_from_post_content($settings)) {
			return false;
		}

		if (empty($post->post_excerpt) && empty($post->post_content)) {
			return false;
		}

		return true;
	}

	public function get_post_excerpt($settings, $post)
	{
		$post_excerpt = $post->post_excerpt ?? '';

		if (empty($post_excerpt) && !empty($post->post_content) && $this->should_get_excerpt_from_post_content($settings)) {
			$post_excerpt = apply_filters('the_excerpt', get_the_excerpt($post));
		}

		return $post_excerpt;
	}

	public function render()
	{
		$post = get_post();
		$settings = $this->get_settings_for_display();

		if (!$this->is_post_excerpt_valid($settings, $post)) {
			return;
		}

		$max_length = (int) $settings['max_length'];
		$excerpt = $this->get_post_excerpt($settings, $post);
		$excerpt = $this->trim_words($excerpt, $max_length);

		echo wp_kses_post($excerpt);
	}

	protected function trim_words($text, $max_length)
	{
		$words = explode(' ', $text);
		return implode(' ', array_slice($words, 0, $max_length));
	}
}
