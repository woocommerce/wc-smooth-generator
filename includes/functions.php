<?php
namespace WC\SmoothGenerator;

use Customer as CustomerGenerator;
use Product as ProductGenerator;

/**
 * Generate and save a product.
 *
 * @return int The generated product's ID.
 */
function create_product() {
	$generator = new ProductGenerator();
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
	// add customer
	// a random amount of times 1 to 4
		// do a query for one product with a random offset
		// add the product to the order
	// random status
	// save order
}

/**
 * Create the customer based on generated data.
 *
 * @return int|bool
 */
function create_customer() {
	$generator = new CustomerGenerator();
	$customer  = $generator->generate();
	$customer->save();
	return $customer->get_id();
}
