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
	 * Available Locales.
	 *
	 * @var array
	 */
	public $locales = array(
		'en_AU',
		'en_CA',
		'en_GB',
		'en_HK',
		'en_IN',
		'en_NG',
		'en_NZ',
		'en_PH',
		'en_SG',
		'en_UG',
		'en_US',
		'en_ZA',
	);

	/**
	 * Return a new customer.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return WC_Order Order object with data populated.
	 */
	public static function generate( $save = true ) {
		$faker    = \Faker\Factory::create( 'en_US' );
		$order    = new \WC_Order();
		$customer = self::get_customer();
		$products = self::get_random_products( 1, 10 );

		foreach ( $products as $product ) {
			$item     = new \WC_Order_Item_Product();
			$quantity = $faker->numberBetween( 1, 10 );
			$item->set_props(
				array(
					'quantity'     => $quantity,
					'variation'    => array(),
					'subtotal'     => wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) ),
					'total'        => wc_get_price_excluding_tax( $product, array( 'qty' => $quantity ) ),
					'name'         => $product->get_name(),
					'tax_class'    => $product->get_tax_class(),
					'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
					'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				)
			);

			$order->add_item( $item );
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
		$order->set_status( $faker->randomElement( array( 'completed', 'processing', 'on-hold', 'failed' ) ) );

		if ( $save ) {
			$order->save();
		}
		return $order;
	}

	/**
	 * Return a new customer.
	 *
	 * @return WC_Customer Customer object with data populated.
	 */
	public static function get_customer() {
		global $wpdb;

		$faker    = \Faker\Factory::create( 'en_US' );
		$guest    = (bool) rand( 0, 1 );
		$existing = (bool) rand( 0, 1 );

		if ( $existing ) {
			$user_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} ORDER BY rand() LIMIT 1" ); //@phpcs:ignore
			return new \WC_Customer( $user_id );
		}

		$customer = Generator\Customer( ! $guest );

		return $customer;
	}

	/**
	 *  Get random products selected from existing products.
	 *
	 * @param int $min_amount Minimum amount of products to get.
	 * @param int $max_amount Maximum amount of products to get.
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
		for ( $i = 0; $i < $num_products_to_get; ++$i ) {
			$offset = rand( 0, $num_existing_products );
			$query_args = array(
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'fields' => 'ids',
				'offset' => $offset,
			);
			$id = current( get_posts( $query_args ) );
			if ( $id ) {
				$products[] = new WC_Product( $id );
			}
		}

		return $products;
	}
}
