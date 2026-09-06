<?php
namespace Elemacy\Modules\DynamicTags\Tags\WooCommerce;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Woo;

if (!defined('ABSPATH')) {
	exit;
}

class ProductGallery extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-product-gallery';
	}

	public function get_title()
	{
		return esc_html__('Product Gallery', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy-woocommerce';
	}

	public function get_categories()
	{
		return [Module::GALLERY_CATEGORY];
	}

	public function get_value(array $options = [])
	{
		$product = Woo::get_product();

		if (!$product) {
			return [];
		}

		$image_ids = $product->get_gallery_image_ids();

		return array_map(function ($id) {
			return ['id' => $id];
		}, $image_ids);
	}
}
