<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

class CommentsNumber extends Tag
{

	public function get_name()
	{
		return 'elemacy-comments-number';
	}

	public function get_title()
	{
		return esc_html__('Comments Number', 'elemacy');
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
			'none_text',
			[
				'label' => esc_html__('No Comments Text', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__('No Comments', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'one_text',
			[
				'label' => esc_html__('One Comment Text', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__('One Comment', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'many_text',
			[
				'label' => esc_html__('Many Comments Text', 'elemacy'),
				/* translators: %s: The comments count placeholder token. */
				'description' => esc_html__('Use %s as a placeholder for the comments count.', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				/* translators: %s: The comments count. */
				'default' => esc_html__('%s Comments', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function render()
	{
		$count = (int) get_comments_number();

		if (0 === $count) {
			$value = $this->get_settings('none_text');
		} elseif (1 === $count) {
			$value = $this->get_settings('one_text');
		} else {
			$value = sprintf($this->get_settings('many_text'), number_format_i18n($count));
		}

		echo esc_html($value);
	}
}
