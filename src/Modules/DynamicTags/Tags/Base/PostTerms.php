<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class PostTerms extends Tag
{

	public function get_name()
	{
		return 'elemacy-post-terms';
	}

	public function get_title()
	{
		return esc_html__('Post Terms', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::TEXT_CATEGORY];
	}

	protected function register_controls()
	{
		$this->add_control(
			'taxonomy',
			[
				'label' => esc_html__('Taxonomy', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_taxonomies_array(),
				'default' => 'post_tag',
			]
		);

		$this->add_control(
			'separator',
			[
				'label' => esc_html__('Separator', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'default' => ', ',
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'link',
			[
				'label' => esc_html__('Link', 'elemacy'),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
			]
		);
	}

	public function render()
	{
		$taxonomy = $this->get_settings('taxonomy');
		$separator = $this->get_settings('separator');

		if (empty($taxonomy)) {
			return;
		}

		if ('yes' === $this->get_settings('link')) {
			$value = get_the_term_list(get_the_ID(), $taxonomy, '', $separator);

			if (is_wp_error($value) || empty($value)) {
				return;
			}

			echo wp_kses_post($value);
			return;
		}

		$terms = get_the_terms(get_the_ID(), $taxonomy);

		if (is_wp_error($terms) || empty($terms)) {
			return;
		}

		$names = wp_list_pluck($terms, 'name');

		echo esc_html(implode($separator, $names));
	}

	protected function get_taxonomies_array()
	{
		$taxonomies = get_taxonomies(['show_in_nav_menus' => true], 'objects');
		$options = [];

		foreach ($taxonomies as $taxonomy) {
			$options[$taxonomy->name] = $taxonomy->label;
		}

		return $options;
	}
}
