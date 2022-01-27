<?php
/**
 * Extend WP_Background_Process to handle data generation & creation.
 *
 * @package SmoothGenerator\Classes
 */

use WC\SmoothGenerator\Generator;

/**
 * Calls generator for object type.
 *
 * @param string $type  Type of object to generate.
 * @param int    $count Number of objects to generate.
 *
 * @return false If task was successful.
 */
function wc_smooth_generate_object( $type, $count = 1) {

	// Check what generation task to perform
	$i = 0;
	while($i++ < $count) {
		switch ( $type ) {
				case 'order':
						Generator\Order::generate();
						break;
				case 'product':
						Generator\Product::generate();
						break;
				case 'customer':
						Generator\Customer::generate();
							break;
				case 'coupon':
						Generator\Coupon::generate();
						break;
				default:
						return false;
		}
	}
	
	return false;
}

add_action( 'wc_smooth_generate_object', 'wc_smooth_generate_object' , 10, 2);

/**
 * Schedule async actions for generation of objects.
 *
 * @param string $type Type of object to generate.
 * @param int    $qty  Quantity of objects.
 */
function wc_smooth_generate_schedule( $type, $qty ) {
	for ( $i = 0; $i < $qty; $i++ ) {
		as_enqueue_async_action( 'wc_smooth_generate_object', array( 'type' => $type ), 'wc_smooth_generate_object_group' );
	}
}

/**
 * Cancel any scheduled generation actions.
 */
function wc_smooth_generate_cancel_all() {
	as_unschedule_all_actions( '', array(), 'wc_smooth_generate_object_group' );
}
