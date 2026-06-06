<?php
namespace Elemacy\Modules\DynamicTags\Tags\Utility;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class ContactUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-contact-url';
	}

	public function get_title()
	{
		return esc_html__('Contact URL', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::URL_CATEGORY];
	}

	protected function register_controls()
	{
		$this->add_control(
			'type',
			[
				'label' => esc_html__('Type', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'mailto' => esc_html__('Email', 'elemacy'),
					'tel' => esc_html__('Phone', 'elemacy'),
					'sms' => esc_html__('SMS', 'elemacy'),
				],
				'default' => 'mailto',
			]
		);

		$this->add_control(
			'value',
			[
				'label' => esc_html__('Value', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'name@example.com',
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function get_value(array $options = [])
	{
		$value = trim($this->get_settings('value'));

		if (empty($value)) {
			return '';
		}

		switch ($this->get_settings('type')) {
			case 'mailto':
				return 'mailto:' . sanitize_email($value);

			case 'tel':
			case 'sms':
				return $this->get_settings('type') . ':' . preg_replace('/[^0-9+]/', '', $value);

			default:
				return '';
		}
	}
}
