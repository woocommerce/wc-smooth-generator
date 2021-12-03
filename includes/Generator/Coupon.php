<?php
/**
 * Customer data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Customer data generator.
 */
class Coupon extends Generator {


	/**
	 * Return a new customer.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @param int  $min minimum coupon amount.
	 * @param int  $max maximum coupon amount.
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function generate( $save = true, $min = 5, $max = 100 ) {
		self::init_faker();

		$amount = random_int( $min, $max );
		$coupon = new \WC_Coupon();
		$coupon->set_props( array(
			'code'   => "discount$amount",
			'amount' => $amount,
		) );
		$coupon->save();

		return new \WC_Coupon( $coupon->get_id() );
	}

}

