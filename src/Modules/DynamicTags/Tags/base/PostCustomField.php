<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class PostCustomField extends Tag
{

	public function get_name()
	{
		return 'elemacy-post-custom-field';
	}

	public function get_title()
	{
		return esc_html__('Post Custom Field', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [
			Module::TEXT_CATEGORY,
			Module::URL_CATEGORY,
			Module::POST_META_CATEGORY,
			Module::COLOR_CATEGORY,
			Module::DATETIME_CATEGORY,
			Module::MEDIA_CATEGORY,
		];
	}

	public function get_panel_template_setting_key()
	{
		return 'key';
	}

	public function is_settings_required()
	{
		return true;
	}

	protected function register_controls()
	{
		$this->add_control(
			'key',
			[
				'label' => esc_html__('Key', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => $this->get_custom_keys_array(),
			]
		);

		$this->add_control(
			'custom_key',
			[
				'label' => esc_html__('Custom Key', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'key',
				'condition' => [
					'key' => '',
				],
				'ai' => [
					'active' => false,
				],
			]
		);

	}

	public function get_custom_field_value(string $key): string
	{
		$value = get_post_meta(get_the_ID(), $key, true);

		if (!is_string($value)) {
			return '';
		}

		return $value;
	}

	public function render()
	{
		$key = $this->get_settings('key');

		if (empty($key)) {
			$key = $this->get_settings('custom_key');
		}

		if (empty($key)) {
			return;
		}

		$value = $this->get_custom_field_value($key);

		if (empty($value)) {
			return;
		}

		echo wp_kses_post($value);
	}

	protected function get_custom_keys_array()
	{
		$custom_keys = get_post_custom_keys();
		$options = [
			'' => esc_html__('Select...', 'elemacy'),
		];

		if (!empty($custom_keys)) {
			foreach ($custom_keys as $custom_key) {
				if ('_' !== substr($custom_key, 0, 1)) {
					$options[$custom_key] = $custom_key;
				}
			}
		}

		return $options;
	}
}
