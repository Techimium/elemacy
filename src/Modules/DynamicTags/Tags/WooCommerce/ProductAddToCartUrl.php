<?php
namespace Elemacy\Modules\DynamicTags\Tags\WooCommerce;

use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use Elemacy\Modules\DynamicTags\Services\Woo;

if (!defined('ABSPATH')) {
	exit;
}

class ProductAddToCartUrl extends Data_Tag
{

	public function get_name()
	{
		return 'elemacy-product-add-to-cart-url';
	}

	public function get_title()
	{
		return esc_html__('Product Add To Cart URL', 'elemacy');
	}

	public function get_group()
	{
		return 'elemacy-woocommerce';
	}

	public function get_categories()
	{
		return [Module::URL_CATEGORY];
	}

	public function get_value(array $options = [])
	{
		$product = Woo::get_product();

		if (!$product) {
			return '';
		}

		return $product->add_to_cart_url();
	}
}
