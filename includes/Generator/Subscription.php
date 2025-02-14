<?php
/**
 * Subscription data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Subscription data generator.
 */
class Subscription extends Generator {

	/**
	 * Return a new subscription.
	 *
	 * @param bool  $save Save the object before returning or not.
	 * @param array $assoc_args Arguments passed via the CLI for additional customization.
	 *
	 * @return \WC_Subscription|false Subscription object with data populated or false when failed.
	 */
	public static function generate( $save = true, $assoc_args = array() ) {
		parent::maybe_initialize_generators();

		$subscription = new \WC_Subscription();
		$customer     = self::get_customer();

		if ( ! $customer instanceof \WC_Customer ) {
			return false;
		}

		$products = self::get_random_products( 1, 4 );

		foreach ( $products as $product ) {
			$quantity = self::$faker->numberBetween( 1, 3 );
			$subscription->add_product( $product, $quantity );
		}

		$subscription->set_customer_id( $customer->get_id() );
		$subscription->set_created_via( 'smooth-generator' );
		$subscription->set_currency( get_woocommerce_currency() );
		$subscription->set_billing_first_name( $customer->get_billing_first_name() );
		$subscription->set_billing_last_name( $customer->get_billing_last_name() );
		$subscription->set_billing_address_1( $customer->get_billing_address_1() );
		$subscription->set_billing_address_2( $customer->get_billing_address_2() );
		$subscription->set_billing_email( $customer->get_billing_email() );
		$subscription->set_billing_phone( $customer->get_billing_phone() );
		$subscription->set_billing_city( $customer->get_billing_city() );
		$subscription->set_billing_postcode( $customer->get_billing_postcode() );
		$subscription->set_billing_state( $customer->get_billing_state() );
		$subscription->set_billing_country( $customer->get_billing_country() );
		$subscription->set_billing_company( $customer->get_billing_company() );
		$subscription->set_shipping_first_name( $customer->get_shipping_first_name() );
		$subscription->set_shipping_last_name( $customer->get_shipping_last_name() );
		$subscription->set_shipping_address_1( $customer->get_shipping_address_1() );
		$subscription->set_shipping_address_2( $customer->get_shipping_address_2() );
		$subscription->set_shipping_city( $customer->get_shipping_city() );
		$subscription->set_shipping_postcode( $customer->get_shipping_postcode() );
		$subscription->set_shipping_state( $customer->get_shipping_state() );
		$subscription->set_shipping_country( $customer->get_shipping_country() );
		$subscription->set_shipping_company( $customer->get_shipping_company() );

		$subscription->set_billing_period( self::get_billing_period( $assoc_args ) );
		$subscription->set_billing_interval( self::$faker->numberBetween( 1, 3 ) );

		// 20% chance
		if ( rand( 0, 100 ) <= 20 ) {
			$country_code = $subscription->get_shipping_country();

			$calculate_tax_for = array(
				'country' => $country_code,
				'state' => '',
				'postcode' => '',
				'city' => '',
			);

			$fee = new \WC_Order_Item_Fee();
			$randomAmount = self::$faker->randomFloat( 2, 0.05, 100 );

			$fee->set_name( 'Extra Fee' );
			$fee->set_amount( $randomAmount );
			$fee->set_tax_class( '' );
			$fee->set_tax_status( 'taxable' );
			$fee->set_total( $randomAmount );
			$fee->calculate_taxes( $calculate_tax_for );
			$subscription->add_item( $fee );
		}

		$status = self::get_status( $assoc_args );

		$subscription->set_status( $status );
		$subscription->calculate_totals( true );

		$dates = self::generate_subscription_dates( $status );

		$subscription->set_date_created( strtotime( $dates['start'] ) );
		$subscription->set_start_date( $dates['start'] );

		if ( ! empty( $dates['next_payment'] ) ) {
			$subscription->set_next_payment_date( $dates['next_payment'] );
		}

		if ( ! empty( $dates['end'] ) ) {
			$subscription->set_end_date( $dates['end'] );
		}

		if ( $save ) {
			$subscription->save();
		}

		/**
		 * Action: Subscription generator returned a new subscription.
		 *
		 * @since 1.2.0
		 *
		 * @param \WC_Subscription $subscription
		 */
		do_action( 'smoothgenerator_subscription_generated', $subscription );

		return $subscription;
	}

	/**
	 * Create multiple subscriptions.
	 *
	 * @param int    $amount   The number of subscriptions to create.
	 * @param array  $args     Additional args for subscription creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$subscription_ids = array();

		for ( $i = 1; $i <= $amount; $i ++ ) {
			$subscription       = self::generate( true, $args );
			$subscription_ids[] = $subscription->get_id();
		}

		return $subscription_ids;
	}

	/**
	 * Return a new customer.
	 *
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function get_customer() {
		global $wpdb;

		$existing = (bool) wp_rand( 0, 1 );

		if ( $existing ) {
			$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
			$offset      = wp_rand( 0, $total_users );
			$user_id     = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY rand() LIMIT $offset, 1" ); // phpcs:ignore
			return new \WC_Customer( $user_id );
		}

		$customer = Customer::generate();

		return $customer;
	}

	/**
	 * Returns a status to use as the subscriptions's status. If no status argument has been passed, this will
	 * return a random status.
	 *
	 * @param array $assoc_args CLI arguments.
	 * @return string An subscription status.
	 */
	private static function get_status( $assoc_args ) {
		if ( ! empty( $assoc_args['status'] ) ) {
			return $assoc_args['status'];
		} else {
			return self::random_weighted_element( array(
				'active'    => 75,
				'on-hold'   => 15,
				'cancelled' => 10,
				'expired'   => 5,
			) );
		}
	}

	/**
	 * Returns a billing period to use for the subscription. If no billing-period argument has been passed, this will return a random period.
	 *
	 * @param array $assoc_args CLI arguments.
	 *
	 * @return string A billing period
	 */
	private static function get_billing_period( $assoc_args ) {
		if ( ! empty( $assoc_args['billing-period'] ) ) {
			return $assoc_args['billing-period'];
		} else {
			return self::random_weighted_element( array(
				'day'   => 5,
				'week'  => 15,
				'month' => 70,
				'year'  => 10,
			) );
		}
	}

	/**
	 * Get random products selected from existing products.
	 *
	 * @param int $min_amount Minimum amount of products to get.
	 * @param int $max_amount Maximum amount of products to get.
	 * @return array Random list of products.
	 */
	protected static function get_random_products( int $min_amount = 1, int $max_amount = 4 ) {
		global $wpdb;

		$products = array();

		$num_existing_products = (int) $wpdb->get_var(
			"SELECT COUNT( DISTINCT ID )
			FROM {$wpdb->posts}
			WHERE 1=1
			AND post_type='product'
			AND post_status='publish'"
		);

		$num_products_to_get = wp_rand( $min_amount, $max_amount );

		if ( $num_products_to_get > $num_existing_products ) {
			$num_products_to_get = $num_existing_products;
		}

		$query = new \WC_Product_Query( array(
			'limit'   => $num_products_to_get,
			'return'  => 'ids',
			'orderby' => 'rand',
		) );

		foreach ( $query->get_products() as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product->is_type( 'variable' ) ) {
				$available_variations = $product->get_available_variations();
				if ( empty( $available_variations ) ) {
					continue;
				}
				$index      = self::$faker->numberBetween( 0, count( $available_variations ) - 1 );
				$products[] = new \WC_Product_Variation( $available_variations[ $index ]['variation_id'] );
			} else {
				$products[] = new \WC_Product( $product_id );
			}
		}

		return $products;
	}

	/**
	 * Using the status of the subscription, return a random set of dates appropriate for the subscription.
	 *
	 * This function ensures that:
	 * - The start date is always sometime in the past (3 to 12 months ago).
	 * - The next payment date (for active/on-hold subscription) is sometime in the future (1 to 3 months ahead).
	 * - The end date (for cancelled/expired) is always in the past (1 week to 3 months ago) and never before the start date.
	 *
	 * @param string $status The status of the subscription.
	 *
	 * @return array An array of dates.
	 */
	private static function generate_subscription_dates( $status ) {
		$now   = time();
		$start = strtotime( '-' . rand( 3, 12 ) . ' weeks', $now );
		$dates = [ 'start' => gmdate( 'Y-m-d H:i:s', $start ) ];
	
		switch ( $status ) {
			case 'active':
			case 'on-hold':
				$next  = strtotime( '+' . rand( 1, 12 ) . ' weeks', $now );
				$dates['next_payment'] = gmdate( 'Y-m-d H:i:s', $next );
				break;
			case 'cancelled':
			case 'expired':
				// Ensure end date is always in the past (between 1 week and 3 months ago)
				$end = strtotime( '-' . rand( 1, 12 ) . ' weeks', $now );
				// Make sure the end date is not before the start date
				if ( $end < $start ) {
					$end = strtotime( '+1 week', $start ); // Ensure at least 1 week after start

					if ( $end > $now ) {
						$end = strtotime( '-1 week', $now ); // If still in future, cap to last week
					}
				}

				$dates['end'] = gmdate( 'Y-m-d H:i:s', $end );
				break;
		}
	
		return $dates;
	}
}
