<?php
namespace Elemacy\Modules\DynamicTags\Tags\Utility;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class RequestParameter extends Tag
{

	public function get_name()
	{
		return 'elemacy-request-parameter';
	}

	public function get_title()
	{
		return esc_html__('Request Parameter', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::TEXT_CATEGORY];
	}

	public function is_settings_required()
	{
		return true;
	}

	protected function register_controls()
	{
		$this->add_control(
			'type',
			[
				'label' => esc_html__('Type', 'elemacy'),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'get' => 'GET',
					'post' => 'POST',
				],
				'default' => 'get',
			]
		);

		$this->add_control(
			'param',
			[
				'label' => esc_html__('Parameter Name', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => 'utm_source',
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function render()
	{
		$param = $this->get_settings('param');

		if (empty($param)) {
			return;
		}

		$source = 'post' === $this->get_settings('type') ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing

		if (!isset($source[$param])) {
			return;
		}

		echo esc_html(sanitize_text_field(wp_unslash($source[$param])));
	}
}
