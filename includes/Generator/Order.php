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

		// Handle legacy --coupons flag
		$include_coupon = ! empty( $assoc_args['coupons'] );

		// Handle --coupon-ratio parameter
		if ( ! empty( $assoc_args['coupon-ratio'] ) ) {
			$coupon_ratio = floatval( $assoc_args['coupon-ratio'] );
			// Apply coupon based on ratio
			if ( $coupon_ratio > 0 && ( $coupon_ratio >= 1.0 || ( mt_rand() / mt_getrandmax() ) < $coupon_ratio ) ) {
				$include_coupon = true;
			} else {
				$include_coupon = false;
			}
		}

		if ( $include_coupon ) {
			$coupon = self::get_or_create_coupon();
			if ( $coupon ) {
				$order->apply_coupon( $coupon );
				// Recalculate totals after applying coupon
				$order->calculate_totals( true );
			}
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

			// Handle --refund-ratio parameter for completed orders
			if ( ! empty( $assoc_args['refund-ratio'] ) && 'completed' === $status ) {
				$refund_ratio = floatval( $assoc_args['refund-ratio'] );
				$should_refund = false;

				if ( $refund_ratio >= 1.0 ) {
					// Always refund if ratio is 1.0 or higher
					$should_refund = true;
				} elseif ( $refund_ratio > 0 ) {
					// Use random chance for ratios between 0 and 1
					$random = mt_rand() / mt_getrandmax();
					$should_refund = $random < $refund_ratio;
				}

				if ( $should_refund ) {
					$is_partial = self::create_refund( $order );

					// 25% of partial refunds get a second refund (always partial)
					if ( $is_partial && wp_rand( 1, 100 ) <= 25 ) {
						self::create_refund( $order, true );
					}
				}
			}
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

	/**
	 * Get a random existing coupon or create coupons if none exist.
	 * If no coupons exist, creates 6 coupons: 3 fixed value and 3 percentage.
	 *
	 * @return \WC_Coupon|null Coupon object or null if none available.
	 */
	protected static function get_or_create_coupon() {
		global $wpdb;

		// Check if any coupons exist
		$coupon_count = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->posts}
			WHERE post_type = 'shop_coupon'
			AND post_status = 'publish'"
		);

		// If no coupons exist, create 6 (3 fixed, 3 percentage)
		if ( $coupon_count === 0 ) {
			// Create 3 fixed value coupons
			for ( $i = 0; $i < 3; $i++ ) {
				$coupon = new \WC_Coupon();
				$amount = self::$faker->numberBetween( 5, 50 );
				$code   = 'fixed' . $amount . '-' . self::$faker->lexify( '???' );

				$coupon->set_code( $code );
				$coupon->set_discount_type( 'fixed_cart' );
				$coupon->set_amount( $amount );
				$coupon->save();
			}

			// Create 3 percentage coupons
			for ( $i = 0; $i < 3; $i++ ) {
				$coupon = new \WC_Coupon();
				$amount = self::$faker->numberBetween( 5, 25 );
				$code   = 'percent' . $amount . '-' . self::$faker->lexify( '???' );

				$coupon->set_code( $code );
				$coupon->set_discount_type( 'percent' );
				$coupon->set_amount( $amount );
				$coupon->save();
			}

			$coupon_count = 6;
		}

		// Get a random coupon
		$offset    = wp_rand( 0, $coupon_count - 1 );
		$coupon_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID
				FROM {$wpdb->posts}
				WHERE post_type = 'shop_coupon'
				AND post_status = 'publish'
				ORDER BY ID
				LIMIT %d, 1",
				$offset
			)
		);

		if ( $coupon_id ) {
			return new \WC_Coupon( $coupon_id );
		}

		return null;
	}

	/**
	 * Create a refund for an order (either full or partial).
	 *
	 * @param \WC_Order $order The order to refund.
	 * @param bool      $force_partial Force partial refund only.
	 * @return bool True if partial refund, false if full refund or on failure.
	 */
	protected static function create_refund( $order, $force_partial = false ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		// Check if order already has refunds
		$existing_refunds = $order->get_refunds();
		if ( ! empty( $existing_refunds ) ) {
			$force_partial = true;
		}

		// 50% chance of full refund, 50% chance of partial refund (unless forced)
		$is_full_refund = $force_partial ? false : (bool) wp_rand( 0, 1 );

		$line_items = array();

		if ( $is_full_refund ) {
			// Full refund - include all line items and fees
			foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
				$taxes      = $item->get_taxes();
				$refund_tax = array();

				if ( ! empty( $taxes['total'] ) ) {
					foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
						$refund_tax[ $tax_id ] = $tax_amount * -1;
					}
				}

				$line_items[ $item_id ] = array(
					'qty'          => $item->get_quantity(),
					'refund_total' => $item->get_total() * -1,
					'refund_tax'   => $refund_tax,
				);
			}
		} else {
			// Partial refund - randomly select items or partial quantities
			$items = $order->get_items( array( 'line_item', 'fee' ) );

			// Decide whether to refund full items or partial quantities
			$refund_full_items = (bool) wp_rand( 0, 1 );

			if ( $refund_full_items && count( $items ) > 1 ) {
				// Refund a random subset of items completely
				$items_array  = array_values( $items );
				$num_to_refund = wp_rand( 1, count( $items_array ) - 1 );
				$items_to_refund = array_rand( $items_array, $num_to_refund );

				// array_rand returns int if count is 1, array otherwise
				if ( ! is_array( $items_to_refund ) ) {
					$items_to_refund = array( $items_to_refund );
				}

				foreach ( $items_to_refund as $index ) {
					$item       = $items_array[ $index ];
					$item_id    = $item->get_id();
					$taxes      = $item->get_taxes();
					$refund_tax = array();

					if ( ! empty( $taxes['total'] ) ) {
						foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
							$refund_tax[ $tax_id ] = $tax_amount * -1;
						}
					}

					$line_items[ $item_id ] = array(
						'qty'          => $item->get_quantity(),
						'refund_total' => $item->get_total() * -1,
						'refund_tax'   => $refund_tax,
					);
				}
			} else {
				// Refund partial quantities of items
				foreach ( $items as $item_id => $item ) {
					$quantity = $item->get_quantity();

					// Only refund line items with quantity > 1
					if ( 'line_item' === $item->get_type() && $quantity > 1 ) {
						// Refund between 1 and quantity-1 items
						$refund_qty    = wp_rand( 1, $quantity - 1 );
						$refund_amount = ( $item->get_total() / $quantity ) * $refund_qty;
						$taxes         = $item->get_taxes();
						$refund_tax    = array();

						if ( ! empty( $taxes['total'] ) ) {
							foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
								$refund_tax[ $tax_id ] = ( $tax_amount / $quantity ) * $refund_qty * -1;
							}
						}

						$line_items[ $item_id ] = array(
							'qty'          => $refund_qty,
							'refund_total' => $refund_amount * -1,
							'refund_tax'   => $refund_tax,
						);
						break; // Only refund one item partially
					}
				}

				// If no items were added (all quantities were 1), refund one complete item
				if ( empty( $line_items ) && count( $items ) > 0 ) {
					$items_array = array_values( $items );
					$item        = $items_array[ array_rand( $items_array ) ];
					$item_id     = $item->get_id();
					$taxes       = $item->get_taxes();
					$refund_tax  = array();

					if ( ! empty( $taxes['total'] ) ) {
						foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
							$refund_tax[ $tax_id ] = $tax_amount * -1;
						}
					}

					$line_items[ $item_id ] = array(
						'qty'          => $item->get_quantity(),
						'refund_total' => $item->get_total() * -1,
						'refund_tax'   => $refund_tax,
					);
				}
			}
		}

		// Calculate the total refund amount from line items and count items
		$refund_amount = 0;
		$total_items   = 0;
		$total_qty     = 0;

		foreach ( $line_items as $item_id => $item_data ) {
			// Add item total (already negative)
			$refund_amount += abs( $item_data['refund_total'] );

			// Count items and quantities
			$total_items++;
			$total_qty += $item_data['qty'];

			// Add tax amounts (already negative)
			if ( ! empty( $item_data['refund_tax'] ) ) {
				foreach ( $item_data['refund_tax'] as $tax_amount ) {
					$refund_amount += abs( $tax_amount );
				}
			}
		}

		// Create refund reason
		if ( $is_full_refund ) {
			$reason = 'Full refund';
		} else {
			$reason = sprintf(
				'Partial refund - %d %s, %d %s',
				$total_items,
				$total_items === 1 ? 'product' : 'products',
				$total_qty,
				$total_qty === 1 ? 'item' : 'items'
			);
		}

		// Create the refund
		$refund = wc_create_refund(
			array(
				'order_id'   => $order->get_id(),
				'amount'     => $refund_amount,
				'reason'     => $reason,
				'line_items' => $line_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return false;
		}

		// Update order status to refunded if it's a full refund
		if ( $is_full_refund ) {
			$order->set_status( 'refunded' );
			$order->save();
		}

		return ! $is_full_refund;
	}
}
