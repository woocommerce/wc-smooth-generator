<?php
namespace WC\SmoothGenerator;

use Generator\Customer as CustomerGenerator;
use Generator\Product as ProductGenerator;

/**
 * Generate and save a product.
 *
 * @return int The generated product's ID.
 */
function create_product() {
	$generator = new ProductGenerator;
	$product = $generator->generate();
	$product->save();
	return $product->get_id();
}

/**
 * Generate and save an order.
 *
 * @return int The generated order's ID.
 */
function create_order() {
	// figure out how many products exist
	// do a query for
}
