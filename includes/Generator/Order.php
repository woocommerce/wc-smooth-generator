<?php
/**
 * Order data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Order data generator.
 */
class Order extends Generator {

	/**
	 * Return a new order.
	 *
	 * @param bool  $save Save the object before returning or not.
	 * @param array $assoc_args Arguments passed via the CLI for additional customization.
	 * @return \WC_Order|false Order object with data populated or false when failed.
	 */
	public static function generate( $save = true, $assoc_args = array() ) {
		parent::maybe_initialize_generators();

		$order    = new \WC_Order();
		$customer = self::get_customer();
		if ( ! $customer instanceof \WC_Customer ) {
			return false;
		}
		$products = self::get_random_products( 1, 10 );

		foreach ( $products as $product ) {
			$quantity = self::$faker->numberBetween( 1, 10 );
			$order->add_product( $product, $quantity );
		}

		$order->set_customer_id( $customer->get_id() );
		$order->set_created_via( 'smooth-generator' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_billing_first_name( $customer->get_billing_first_name() );
		$order->set_billing_last_name( $customer->get_billing_last_name() );
		$order->set_billing_address_1( $customer->get_billing_address_1() );
		$order->set_billing_address_2( $customer->get_billing_address_2() );
		$order->set_billing_email( $customer->get_billing_email() );
		$order->set_billing_phone( $customer->get_billing_phone() );
		$order->set_billing_city( $customer->get_billing_city() );
		$order->set_billing_postcode( $customer->get_billing_postcode() );
		$order->set_billing_state( $customer->get_billing_state() );
		$order->set_billing_country( $customer->get_billing_country() );
		$order->set_billing_company( $customer->get_billing_company() );
		$order->set_shipping_first_name( $customer->get_shipping_first_name() );
		$order->set_shipping_last_name( $customer->get_shipping_last_name() );
		$order->set_shipping_address_1( $customer->get_shipping_address_1() );
		$order->set_shipping_address_2( $customer->get_shipping_address_2() );
		$order->set_shipping_city( $customer->get_shipping_city() );
		$order->set_shipping_postcode( $customer->get_shipping_postcode() );
		$order->set_shipping_state( $customer->get_shipping_state() );
		$order->set_shipping_country( $customer->get_shipping_country() );
		$order->set_shipping_company( $customer->get_shipping_company() );

		// 20% chance
		if ( rand( 0, 100 ) <= 20 ) {
			$country_code = $order->get_shipping_country();

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
			$order->add_item( $fee );
		}
		$status = self::get_status( $assoc_args );
		$order->set_status( $status );
		$order->calculate_totals( true );

		$date  = self::get_date_created( $assoc_args );
		$date .= ' ' . wp_rand( 0, 23 ) . ':00:00';

		$order->set_date_created( $date );

		$include_coupon = ! empty( $assoc_args['coupons'] );
		if ( $include_coupon ) {
			$coupon = Coupon::generate( true );
			$order->apply_coupon( $coupon );
		}

		// Orders created before 2024-01-09	represents orders created before the attribution feature was added.
		if ( ! ( strtotime( $date ) < strtotime( '2024-01-09' ) ) ) {
			OrderAttribution::add_order_attribution_meta( $order, $assoc_args );
		}

		// Set paid and completed dates based on order status.
		if ( 'completed' === $status || 'processing' === $status ) {
			// Add random 0 to 36 hours to creation date.
			$date_paid = date( 'Y-m-d H:i:s', strtotime( $date ) + ( wp_rand( 0, 36 ) * HOUR_IN_SECONDS ) );
			$order->set_date_paid( $date_paid );
			if ( 'completed' === $status ) {
				// Add random 0 to 36 hours to paid date.
				$date_completed = date( 'Y-m-d H:i:s', strtotime( $date_paid ) + ( wp_rand( 0, 36 ) * HOUR_IN_SECONDS ) );
				$order->set_date_completed( $date_completed );
			}
		}

		if ( $save ) {
			$order->save();
		}

		/**
		 * Action: Order generator returned a new order.
		 *
		 * @since 1.2.0
		 *
		 * @param \WC_Order $order
		 */
		do_action( 'smoothgenerator_order_generated', $order );

		return $order;
	}

	/**
	 * Create multiple orders.
	 *
	 * @param int    $amount   The number of orders to create.
	 * @param array  $args     Additional args for order creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$order_ids = array();

		for ( $i = 1; $i <= $amount; $i ++ ) {
			$order       = self::generate( true, $args );
			$order_ids[] = $order->get_id();
		}

		return $order_ids;
	}

	/**
	 * Return a new customer.
	 *
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function get_customer() {
		global $wpdb;

		$guest    = (bool) wp_rand( 0, 1 );
		$existing = (bool) wp_rand( 0, 1 );

		if ( $existing ) {
			$total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
			$offset      = wp_rand( 0, $total_users );
			$user_id     = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY rand() LIMIT $offset, 1" ); // phpcs:ignore
			return new \WC_Customer( $user_id );
		}

		$customer = Customer::generate( ! $guest );

		return $customer;
	}

	/**
	 * Returns a date to use as the order date. If no date arguments have been passed, this will
	 * return the current date. If a `date-start` argument is provided, a random date will be chosen
	 * between `date-start` and the current date. You can pass an `end-date` and a random date between start
	 * and end will be chosen.
	 *
	 * @param array $assoc_args CLI arguments.
	 * @return string Date string (Y-m-d)
	 */
	protected static function get_date_created( $assoc_args ) {
		$current = date( 'Y-m-d', time() );
		if ( ! empty( $assoc_args['date-start'] ) && empty( $assoc_args['date-end'] ) ) {
			$start = $assoc_args['date-start'];
			$end   = $current;
		} elseif ( ! empty( $assoc_args['date-start'] ) && ! empty( $assoc_args['date-end'] ) ) {
			$start = $assoc_args['date-start'];
			$end   = $assoc_args['date-end'];
		} else {
			return $current;
		}

		$dates = array();
		$date  = strtotime( $start );
		while ( $date <= strtotime( $end ) ) {
			$dates[] = date( 'Y-m-d', $date );
			$date    = strtotime( '+1 day', $date );
		}

		return $dates[ array_rand( $dates ) ];
	}

	/**
	 * Returns a status to use as the order's status. If no status argument has been passed, this will
	 * return a random status.
	 *
	 * @param array $assoc_args CLI arguments.
	 * @return string An order status.
	 */
	private static function get_status( $assoc_args ) {
		if ( ! empty( $assoc_args['status'] ) ) {
			return $assoc_args['status'];
		} else {
			return self::random_weighted_element( array(
				'completed'  => 70,
				'processing' => 15,
				'on-hold'    => 5,
				'failed'     => 10,
			) );
		}
	}

	/**
	 *  Get random products selected from existing products.
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
}
