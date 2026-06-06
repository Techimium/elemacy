<?php
namespace Elemacy\Modules\DynamicTags\Tags\Utility;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class CurrentDateTime extends Tag
{

	public function get_name()
	{
		return 'elemacy-current-date-time';
	}

	public function get_title()
	{
		return esc_html__('Current Date Time', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::DATETIME_CATEGORY,
			Module::TEXT_CATEGORY,
		];
	}

	protected function register_controls()
	{
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
					'g:i a' => gmdate('g:i a'),
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
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function render()
	{
		$format = $this->get_settings('format');

		if ('default' === $format) {
			$date_format = get_option('date_format');
		} elseif ('custom' === $format) {
			$date_format = $this->get_settings('custom_format');
		} else {
			$date_format = $format;
		}

		echo esc_html(wp_date($date_format));
	}
}
