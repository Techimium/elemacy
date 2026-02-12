<?php

namespace Elemacy\Modules\DynamicTags\Tags\Acf;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Acf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class AcfColor extends Tag
{

	public function get_name()
	{
		return 'elemacy-acf-color';
	}

	public function get_title()
	{
		return __('ACF Color Picker Field', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::COLOR_CATEGORY];
	}

	public function get_panel_template_setting_key()
	{
		return 'key';
	}

	public function render()
	{
		$key = $this->get_settings('key');

		if (empty($key)) {
			return;
		}

		$value = Acf::get_field_value($key);

		if (is_string($value) || is_numeric($value)) {
			echo wp_kses_post($value);
		}
	}

	protected function register_controls()
	{
		$this->add_control(
			'key',
			[
				'label' => esc_html__('Field Name (Meta Key)', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => esc_html__('Enter ACF field key/name (e.g. my_custom_field)', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function get_supported_fields()
	{
		return [
			'color_picker',
		];
	}
}
