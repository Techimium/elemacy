<?php
namespace Elemacy\Modules\DynamicTags\Tags\Acf;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Acf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class AcfDateTime extends Tag
{

	public function get_name()
	{
		return 'elemacy-acf-date-time';
	}

	public function get_title()
	{
		return __('ACF Date Time Field', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::DATETIME_CATEGORY,
			Module::TEXT_CATEGORY
		];
	}

	public function render()
	{
		$value = Acf::get_field_value($this->get_settings('key'));

		echo wp_kses_post($value);
	}

	public function get_panel_template_setting_key()
	{
		return 'key';
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
			'date_time_picker',
		];
	}
}
