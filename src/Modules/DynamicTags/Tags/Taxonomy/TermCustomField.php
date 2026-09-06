<?php
namespace Elemacy\Modules\DynamicTags\Tags\Taxonomy;

use Elemacy\Modules\DynamicTags\Services\Acf;
use Elemacy\Modules\DynamicTags\Services\Taxonomy;
use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Reads an ACF term-meta field (or plain term meta, when ACF is inactive —
 * see Acf::get_field_value()'s fallback) for the current term. Uses a plain
 * text "Key" control rather than PostCustomField's key dropdown: WordPress
 * has no get_term_custom_keys()-equivalent to populate one from, so this
 * mirrors the ACF single-field tags (AcfNumber, AcfUrl, ...), which already
 * use a plain text key control for the same reason.
 */
class TermCustomField extends Tag
{

	public function get_name()
	{
		return 'elemacy-term-custom-field';
	}

	public function get_title()
	{
		return esc_html__('Term Custom Field', 'elemacy');
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
				'label' => esc_html__('Field Name (Meta Key)', 'elemacy'),
				'type' => Controls_Manager::TEXT,
				'placeholder' => esc_html__('Enter ACF field key/name (e.g. my_custom_field)', 'elemacy'),
				'ai' => [
					'active' => false,
				],
			]
		);
	}

	public function render()
	{
		$key = $this->get_settings('key');

		if (empty($key)) {
			return;
		}

		$term = Taxonomy::get_current_term();

		if (!$term) {
			return;
		}

		$value = Acf::get_field_value($key, 'term_' . $term->term_id);

		if (!is_scalar($value) || '' === (string) $value) {
			return;
		}

		echo wp_kses_post((string) $value);
	}
}
