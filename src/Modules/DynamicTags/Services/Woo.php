<?php

namespace Elemacy\Modules\DynamicTags\Services;

defined('ABSPATH') || exit;

class Woo
{
	public static function get_product($post_id = null)
	{
		if (!function_exists('wc_get_product')) {
			return null;
		}

		$product = wc_get_product($post_id ? $post_id : get_the_ID());

		return $product instanceof \WC_Product ? $product : null;
	}
}
