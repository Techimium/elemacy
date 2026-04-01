<?php
namespace Elemacy\Modules\DynamicTags\Tags\Acf;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Acf;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class AcfUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-acf-url';
	}

	public function get_title()
	{
		return __('ACF URL Field', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::URL_CATEGORY];
	}

	public function get_panel_template_setting_key()
	{
		return 'key';
	}

	public function get_value(array $options = [])
	{
		$value = Acf::get_field_value($this->get_settings('key'));

		if (empty($value) && $this->get_settings('fallback')) {
			$value = $this->get_settings('fallback');
		}

		return wp_kses_post($value);
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

		$this->add_control(
			'fallback',
			[
				'label' => esc_html__('Fallback', 'elemacy'),
			]
		);
	}

	public function get_supported_fields()
	{
		return [
			'text',
			'email',
			'image',
			'file',
			'page_link',
			'post_object',
			'relationship',
			'taxonomy',
			'url',
		];
	}
}
