<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class PostDate extends Tag
{

	public function get_name()
	{
		return 'elemacy-post-date';
	}

	public function get_title()
	{
		return esc_html__('Post Date', 'elemacy');
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
			'type',
			[
				'label' => esc_html__('Type', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'post_date_gmt' => esc_html__('Post Published', 'elemacy'),
					'post_modified_gmt' => esc_html__('Post Modified', 'elemacy'),
				],
				'default' => 'post_date_gmt',
			]
		);

		$this->add_control(
			'format',
			[
				'label' => esc_html__('Format', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'default' => esc_html__('Default', 'elemacy'),
					'F j, Y' => gmdate('F j, Y'),
					'Y-m-d' => gmdate('Y-m-d'),
					'm/d/Y' => gmdate('m/d/Y'),
					'd/m/Y' => gmdate('d/m/Y'),
					'human' => esc_html__('Human Readable', 'elemacy'),
					'custom' => esc_html__('Custom', 'elemacy'),
				],
				'default' => 'default',
			]
		);

		$this->add_control(
			'custom_format',
			[
				'label' => esc_html__('Custom Format', 'elemacy'),
				'default' => '',
				'description' => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url('https://go.elementor.com/wordpress-date-time/'),
					esc_html__('Documentation on date and time formatting', 'elemacy')
				),
				'condition' => [
					'format' => 'custom',
				],
			]
		);
	}

	public function render()
	{
		$post = get_post();
		if (!$post) {
			return;
		}

		$date_type = $this->get_settings('type');
		$format = $this->get_settings('format');

		if ('human' === $format) {
			$timestamp = strtotime($post->{$date_type});
			if (!$timestamp) {
				return;
			}
			$value = sprintf(
				/* translators: %s: Post date. */
				esc_html__('%s ago', 'elemacy'),
				human_time_diff($timestamp)
			);
			echo esc_html($value);
			return;
		}

		// Determine the correct date format
		if ('default' === $format) {
			$date_format = '';
		} elseif ('custom' === $format) {
			$date_format = $this->get_settings('custom_format');
		} else {
			$date_format = $format;
		}

		if ('post_modified_gmt' === $date_type) {
			$value = get_the_modified_date($date_format);
		} else {
			$value = get_the_date($date_format);
		}

		echo wp_kses_post($value);
	}
}
