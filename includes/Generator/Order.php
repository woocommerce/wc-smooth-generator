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
	 * Return a new customer.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return \WC_Order|false Order object with data populated or false when failed.
	 */
	public static function generate( $save = true ) {
		// Set this to avoid notices as when you run via WP-CLI SERVER vars are not set, order emails uses this variable.
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'localhost';
		}
		$faker    = \Faker\Factory::create( 'en_US' );
		$order    = new \WC_Order();
		$customer = self::get_customer();
		if ( ! $customer instanceof \WC_Customer ) {
			return false;
		}
		$products = self::get_random_products( 1, 10 );

		foreach ( $products as $product ) {
			$quantity = $faker->numberBetween( 1, 10 );
			$order->add_product( $product, $quantity );
		}

		$order->set_customer_id( $customer->get_id() );
		$order->set_created_via( 'smooth-generator' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_billing_address_1( $customer->get_billing_address_1() );
		$order->set_billing_address_2( $customer->get_billing_address_2() );
		$order->set_billing_city( $customer->get_billing_city() );
		$order->set_billing_postcode( $customer->get_billing_postcode() );
		$order->set_billing_state( $customer->get_billing_state() );
		$order->set_billing_country( $customer->get_billing_country() );
		$order->set_shipping_address_1( $customer->get_shipping_address_1() );
		$order->set_shipping_address_2( $customer->get_shipping_address_2() );
		$order->set_shipping_city( $customer->get_shipping_city() );
		$order->set_shipping_postcode( $customer->get_shipping_postcode() );
		$order->set_shipping_state( $customer->get_shipping_state() );
		$order->set_shipping_country( $customer->get_shipping_country() );
		$order->set_status( self::random_weighted_element( array(
			'completed'  => 70,
			'processing' => 15,
			'on-hold'    => 5,
			'failed'     => 10,
		) ) );
		$order->calculate_totals( true );

		if ( $save ) {
			$order->save();
		}
		return $order;
	}

	/**
	 * Return a new customer.
	 *
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function get_customer() {
		global $wpdb;

		$guest    = (bool) rand( 0, 1 );
		$existing = (bool) rand( 0, 1 );

		if ( $existing ) {
			$user_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY rand() LIMIT 1" ); // phpcs:ignore
			return new \WC_Customer( $user_id );
		}

		$customer = Customer::generate( ! $guest );

		return $customer;
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

		$num_products_to_get = rand( $min_amount, $max_amount );

		if ( $num_products_to_get > $num_existing_products ) {
			$num_products_to_get = $num_existing_products;
		}

		$query = new \WC_Product_Query( array(
			'limit'   => $num_products_to_get,
			'return'  => 'ids',
			'orderby' => 'rand',
		) );

		foreach ( $query->get_products() as $product_id ) {
			$products[] = new \WC_Product( $product_id );
		}

		return $products;
	}
}
