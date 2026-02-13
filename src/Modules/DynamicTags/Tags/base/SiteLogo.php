<?php
namespace Elemacy\Modules\DynamicTags\Tags\Base;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class SiteLogo extends Data_Tag
{
	public function get_name()
	{
		return 'elemacy-site-logo';
	}

	public function get_title()
	{
		return esc_html__('Site Logo', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy';
	}

	public function get_categories()
	{
		return [Module::IMAGE_CATEGORY];
	}

	public function get_value(array $options = [])
	{
		$custom_logo_id = get_theme_mod('custom_logo');

		if ($custom_logo_id) {
			$url = wp_get_attachment_image_src($custom_logo_id, 'full')[0];
		} else {
			$url = ELEMACY_URL . '/assets/images/logo-placeholder.png';
		}

		return [
			'id' => $custom_logo_id,
			'url' => $url,
		];
	}
}
