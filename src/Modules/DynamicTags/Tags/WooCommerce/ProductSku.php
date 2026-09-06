<?php
namespace Elemacy\Modules\DynamicTags\Tags\WooCommerce;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Woo;

if (!defined('ABSPATH')) {
	exit;
}

class ProductSku extends Tag
{

	public function get_name()
	{
		return 'elemacy-product-sku';
	}

	public function get_title()
	{
		return esc_html__('Product SKU', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy-woocommerce';
	}

	public function get_categories()
	{
		return [Module::TEXT_CATEGORY];
	}

	public function render()
	{
		$product = Woo::get_product();

		if (!$product) {
			return;
		}

		echo esc_html($product->get_sku());
	}
}
