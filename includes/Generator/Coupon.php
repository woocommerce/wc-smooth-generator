<?php
/**
 * Customer data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

use WC_Data_Store;

/**
 * Customer data generator.
 */
class Coupon extends Generator {
	/**
	 * Create a new coupon.
	 *
	 * @param bool  $save       Whether to save the new coupon to the database.
	 * @param array $assoc_args Arguments passed via the CLI for additional customization.
	 *
	 * @return \WC_Coupon|\WP_Error Coupon object with data populated.
	 */
	public static function generate( $save = true, $assoc_args = array() ) {
		parent::maybe_initialize_generators();

		$defaults = array(
			'min' => 5,
			'max' => 100,
		);

		list( 'min' => $min, 'max' => $max ) = filter_var_array(
			wp_parse_args( $assoc_args, $defaults ),
			array(
				'min' => array(
					'filter'  => FILTER_VALIDATE_INT,
					'options' => array(
						'min_range' => 1,
					),
				),
				'max' => array(
					'filter'  => FILTER_VALIDATE_INT,
					'options' => array(
						'min_range' => 1,
					),
				),
			)
		);

		if ( false === $min ) {
			return new \WP_Error(
				'smoothgenerator_coupon_invalid_min_max',
				'The minimum coupon amount must be a valid positive integer.'
			);
		}

		if ( false === $max ) {
			return new \WP_Error(
				'smoothgenerator_coupon_invalid_min_max',
				'The maximum coupon amount must be a valid positive integer.'
			);
		}

		if ( $min > $max ) {
			return new \WP_Error(
				'smoothgenerator_coupon_invalid_min_max',
				'The maximum coupon amount must be an integer that is greater than or equal to the minimum amount.'
			);
		}

		$code        = substr( self::$faker->promotionCode( 1 ), 0, -1 ); // Omit the random digit.
		$amount      = self::$faker->numberBetween( $min, $max );
		$coupon_code = sprintf(
			'%s%d',
			$code,
			$amount
		);

		$coupon = new \WC_Coupon( $coupon_code );
		$coupon->set_props( array(
			'code'   => $coupon_code,
			'amount' => $amount,
		) );

		if ( $save ) {
			$data_store = WC_Data_Store::load( 'coupon' );
			$data_store->create( $coupon );
		}

		/**
		 * Action: Coupon generator returned a new coupon.
		 *
		 * @since 1.2.0
		 *
		 * @param \WC_Coupon $coupon
		 */
		do_action( 'smoothgenerator_coupon_generated', $coupon );

		return $coupon;
	}

	/**
	 * Create multiple coupons.
	 *
	 * @param int   $amount The number of coupons to create.
	 * @param array $args   Additional args for coupon creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$coupon_ids = array();

		for ( $i = 1; $i <= $amount; $i ++ ) {
			$coupon = self::generate( true, $args );
			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}
			$coupon_ids[] = $coupon->get_id();
		}

		return $coupon_ids;
	}
}

