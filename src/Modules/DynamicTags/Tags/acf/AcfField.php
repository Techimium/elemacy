<?php

namespace Elemacy\Modules\DynamicTags\Tags\Acf;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Acf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class AcfField extends Tag
{

	public function get_name()
	{
		return 'elemacy-acf-field';
	}

	public function get_title()
	{
		return esc_html__('ACF Field', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::TEXT_CATEGORY,
			Module::POST_META_CATEGORY,
		];
	}

	protected function register_controls()
	{
		$this->add_control(
			'key',
			[
				'label' => esc_html__('Field Name (Meta Key)', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => esc_html__('Enter ACF field key/name (e.g. my_custom_field)', 'elemacy'),
				'description' => esc_html__('Supported ACF fields: text, textarea, number, email, password, WYSIWYG, select, checkbox, radio, true/false, oEmbed, Google Map, date picker, time picker, date/time picker, color picker.', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);
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
}
