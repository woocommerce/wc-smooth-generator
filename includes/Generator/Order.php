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
	 * Probability (percentage) that a partial refund will receive a second refund.
	 */
	const SECOND_REFUND_PROBABILITY = 25;

	/**
	 * Maximum ratio of order total that can be refunded in a partial refund.
	 * Ensures partial refunds don't exceed 50% of order total.
	 */
	const MAX_PARTIAL_REFUND_RATIO = 0.5;

	/**
	 * Maximum days after order completion for first refund (2 months).
	 */
	const FIRST_REFUND_MAX_DAYS = 60;

	/**
	 * Maximum days after first refund for second refund (1 month).
	 */
	const SECOND_REFUND_MAX_DAYS = 30;

	/**
	 * Refund type constants for memory-efficient batch operations.
	 */
	const REFUND_TYPE_NONE = 0;
	const REFUND_TYPE_FULL = 1;
	const REFUND_TYPE_PARTIAL = 2;
	const REFUND_TYPE_MULTI = 3;

	/**
	 * Refund distribution ratios for batch generation with exact ratios.
	 * When generating refunds in batch mode:
	 * - 50% will be full refunds
	 * - 25% will be single partial refunds
	 * - 25% will be multi-partial refunds (two partial refunds)
	 */
	const REFUND_DISTRIBUTION_FULL_RATIO = 0.5;
	const REFUND_DISTRIBUTION_PARTIAL_RATIO = 0.25;

	/**
	 * Maximum batch size for exact ratio distribution using pre-generated arrays.
	 * Above this threshold, falls back to probabilistic approach to manage memory usage.
	 */
	const EXACT_RATIO_BATCH_THRESHOLD = 10000;

	/**
	 * Return a new order.
	 *
	 * @param bool        $save Save the object before returning or not.
	 * @param array       $assoc_args Arguments passed via the CLI for additional customization.
	 * @param string|null $date Optional date string (Y-m-d) to use for order creation. If not provided, will be generated.
	 * @param bool|null   $include_coupon Optional flag to include coupon. If null, will be determined based on coupon-ratio.
	 * @param int|null    $refund_type Optional refund type constant. If null, will be determined based on refund-ratio.
	 * @return \WC_Order|false Order object with data populated or false when failed.
	 */
	public static function generate( $save = true, $assoc_args = array(), $date = null, $include_coupon = null, $refund_type = null ) {
		parent::maybe_initialize_generators();

		$order    = new \WC_Order();
		$customer = self::get_customer();
		if ( ! $customer instanceof \WC_Customer ) {
			error_log( 'Order generation failed: Could not generate or retrieve customer' );
			return false;
		}
		$products = self::get_random_products( 1, 10 );

		if ( empty( $products ) ) {
			error_log( 'Order generation failed: No products available to add to order' );
			return false;
		}

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

		// Use provided date or generate one
		if ( null === $date ) {
			$date = self::get_date_created( $assoc_args );
		}
		$date .= ' ' . wp_rand( 0, 23 ) . ':00:00';

		$order->set_date_created( $date );

		// Coupon parameter precedence:
		// 1. Batch mode flag (from generate_coupon_flags) - takes highest priority
		// 2. Legacy --coupons flag - used if batch flag not provided
		// 3. Probabilistic --coupon-ratio - used if neither batch nor legacy flags are set

		// Handle legacy --coupons flag (only if not provided from batch mode)
		if ( null === $include_coupon ) {
			$include_coupon = ! empty( $assoc_args['coupons'] );
		}

		// Handle --coupon-ratio parameter
		if ( isset( $assoc_args['coupon-ratio'] ) && null === $include_coupon ) {
			// Use probabilistic approach for single order generation or when flag not provided
			$coupon_ratio = floatval( $assoc_args['coupon-ratio'] );

			// Validate ratio is between 0.0 and 1.0
			if ( $coupon_ratio < 0.0 || $coupon_ratio > 1.0 ) {
				$coupon_ratio = max( 0.0, min( 1.0, $coupon_ratio ) );
			}

			// Apply coupon based on ratio
			if ( $coupon_ratio >= 1.0 ) {
				$include_coupon = true;
			} elseif ( $coupon_ratio > 0 && wp_rand( 1, 100 ) <= ( $coupon_ratio * 100 ) ) {
				$include_coupon = true;
			} else {
				$include_coupon = false;
			}
		}

		if ( $include_coupon ) {
			$coupon = self::get_or_create_coupon();
			if ( $coupon ) {
				$apply_result = $order->apply_coupon( $coupon );
				if ( is_wp_error( $apply_result ) ) {
					error_log( 'Coupon application failed: ' . $apply_result->get_error_message() . ' (Coupon: ' . $coupon->get_code() . ')' );
				} else {
					// Recalculate totals after applying coupon
					$order->calculate_totals( true );
				}
			}
		}

		// Orders created before 2024-01-09 represents orders created before the attribution feature was added.
		if ( ! ( strtotime( $date ) < strtotime( '2024-01-09' ) ) ) {
			$attribution_result = OrderAttribution::add_order_attribution_meta( $order, $assoc_args );
			if ( $attribution_result && is_wp_error( $attribution_result ) ) {
				error_log( 'Order attribution meta addition failed: ' . $attribution_result->get_error_message() );
			}
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
			$save_result = $order->save();
			if ( is_wp_error( $save_result ) ) {
				error_log( 'Order save failed: ' . $save_result->get_error_message() );
				return false;
			}

			// Handle --refund-ratio parameter for completed orders
			if ( isset( $assoc_args['refund-ratio'] ) && 'completed' === $status ) {
				// Use provided refund type or determine probabilistically
				if ( null === $refund_type ) {
					$refund_ratio = floatval( $assoc_args['refund-ratio'] );

					// Validate ratio is between 0.0 and 1.0
					if ( $refund_ratio < 0.0 || $refund_ratio > 1.0 ) {
						$refund_ratio = max( 0.0, min( 1.0, $refund_ratio ) );
					}

					$refund_type = self::REFUND_TYPE_NONE;
					if ( $refund_ratio >= 1.0 ) {
						// Always refund if ratio is 1.0 or higher
						$refund_type = self::REFUND_TYPE_FULL;
					} elseif ( $refund_ratio > 0 && wp_rand( 1, 100 ) <= ( $refund_ratio * 100 ) ) {
						// Use random chance for ratios between 0 and 1
						// Split evenly between full and partial
						$refund_type = wp_rand( 0, 1 ) ? self::REFUND_TYPE_FULL : self::REFUND_TYPE_PARTIAL;

						// 25% chance for multi-partial
						if ( self::REFUND_TYPE_PARTIAL === $refund_type && wp_rand( 1, 100 ) <= self::SECOND_REFUND_PROBABILITY ) {
							$refund_type = self::REFUND_TYPE_MULTI;
						}
					}
				}

				// Process refund based on type
				if ( self::REFUND_TYPE_FULL === $refund_type ) {
					self::create_refund( $order, false, null, true ); // Explicitly full
				} elseif ( self::REFUND_TYPE_PARTIAL === $refund_type ) {
					self::create_refund( $order, true, null, false ); // Explicitly partial
				} elseif ( self::REFUND_TYPE_MULTI === $refund_type ) {
					$first_refund = self::create_refund( $order, true, null, false ); // Explicitly partial
					if ( $first_refund && is_object( $first_refund ) ) {
						self::create_refund( $order, true, $first_refund, false ); // Explicitly partial
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
			error_log( 'Batch generation failed: ' . $amount->get_error_message() );
			return $amount;
		}

		// Generate ratio flags for exact distribution (if applicable)
		$coupon_flags = self::generate_coupon_flags( $amount, $args );
		$refund_flags = self::generate_refund_flags( $amount, $args );

		// Pre-generate dates if date-start is provided
		// This ensures chronological order: lower order IDs = earlier dates
		$dates = null;
		if ( ! empty( $args['date-start'] ) ) {
			$dates = self::generate_batch_dates( $amount, $args );
		}

		$order_ids = array();

		for ( $i = 1; $i <= $amount; $i ++ ) {
			// Use pre-generated date if available, otherwise pass null to generate one
			$date = ( null !== $dates && ! empty( $dates ) ) ? array_shift( $dates ) : null;

			// Use pre-generated flags if available
			$include_coupon = ( null !== $coupon_flags && ! empty( $coupon_flags ) ) ? array_shift( $coupon_flags ) : null;
			$refund_type = ( null !== $refund_flags && ! empty( $refund_flags ) ) ? array_shift( $refund_flags ) : null;

			$order = self::generate( true, $args, $date, $include_coupon, $refund_type );
			if ( ! $order instanceof \WC_Order ) {
				error_log( "Batch generation failed: Order {$i} of {$amount} could not be generated" );
				continue;
			}
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

		if ( ! $customer instanceof \WC_Customer ) {
			error_log( 'Customer generation failed: Customer::generate() returned invalid result' );
		}

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

		// Use timestamp-based random selection for single order generation
		$start_timestamp = strtotime( $start );
		$end_timestamp   = strtotime( $end );
		$days_between    = (int) ( ( $end_timestamp - $start_timestamp ) / DAY_IN_SECONDS );

		// If start and end are the same day, return that date (time will be randomized in generate())
		if ( 0 === $days_between ) {
			return date( 'Y-m-d', $start_timestamp );
		}

		// Generate random offset in days and add to start timestamp
		$random_days = wp_rand( 0, $days_between );
		return date( 'Y-m-d', $start_timestamp + ( $random_days * DAY_IN_SECONDS ) );
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

		if ( $num_existing_products === 0 ) {
			error_log( 'No published products found in database' );
			return array();
		}

		$num_products_to_get = wp_rand( $min_amount, $max_amount );

		if ( $num_products_to_get > $num_existing_products ) {
			$num_products_to_get = $num_existing_products;
		}

		$query = new \WC_Product_Query( array(
			'limit'   => $num_products_to_get,
			'return'  => 'ids',
			'orderby' => 'rand',
		) );

		$product_ids = $query->get_products();
		if ( empty( $product_ids ) ) {
			error_log( 'WC_Product_Query returned no product IDs' );
			return array();
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				error_log( "Failed to retrieve product with ID: {$product_id}" );
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				$available_variations = $product->get_available_variations();
				if ( empty( $available_variations ) ) {
					continue;
				}
				$index      = self::$faker->numberBetween( 0, count( $available_variations ) - 1 );
				$variation = new \WC_Product_Variation( $available_variations[ $index ]['variation_id'] );
				if ( $variation && $variation->exists() ) {
					$products[] = $variation;
				}
			} else {
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Get a random existing coupon or create coupons if none exist.
	 * If no coupons exist, creates 6 coupons: 3 fixed value and 3 percentage.
	 *
	 * @return \WC_Coupon|false Coupon object or false if none available.
	 */
	protected static function get_or_create_coupon() {
		// Try to get a random existing coupon
		$coupon = Coupon::get_random();

		// If no coupons exist, create 6 (3 fixed, 3 percentage)
		if ( false === $coupon ) {
			if ( class_exists( 'WP_CLI' ) ) {
				\WP_CLI::log( 'No coupons found. Creating 6 coupons (3 fixed cart $5-$50, 3 percentage 5%-25%)...' );
			}

			// Create 3 fixed cart coupons ($5-$50)
			$fixed_result = Coupon::batch( 3, array( 'min' => 5, 'max' => 50, 'discount_type' => 'fixed_cart' ) );

			// Create 3 percentage coupons (5%-25%)
			$percent_result = Coupon::batch( 3, array( 'min' => 5, 'max' => 25, 'discount_type' => 'percent' ) );

		// If coupon creation failed, return false
		if ( is_wp_error( $fixed_result ) || is_wp_error( $percent_result ) ) {
			$error_message = 'Coupon creation failed: ';
			if ( is_wp_error( $fixed_result ) ) {
				$error_message .= 'Fixed coupons error: ' . $fixed_result->get_error_message() . ' ';
			}
			if ( is_wp_error( $percent_result ) ) {
				$error_message .= 'Percentage coupons error: ' . $percent_result->get_error_message();
			}
			error_log( $error_message );
			return false;
		}

			// Now get a random coupon from the ones we just created
			$coupon = Coupon::get_random();
		}

		return $coupon;
	}

	/**
	 * Create a refund for an order (either full or partial).
	 *
	 * @param \WC_Order      $order The order to refund.
	 * @param bool           $force_partial Force partial refund only (legacy parameter).
	 * @param \WC_Order_Refund|null $previous_refund Previous refund to base date on (for second refunds).
	 * @param bool|null      $force_full Explicitly force full refund (overrides random logic).
	 * @return \WC_Order_Refund|false Refund object on success, false on failure.
	 */
	protected static function create_refund( $order, $force_partial = false, $previous_refund = null, $force_full = null ) {
		if ( ! $order instanceof \WC_Order ) {
			error_log( "Error: Order is not an instance of \WC_Order: " . print_r( $order, true ) );
			return false;
		}

		// Check if order already has refunds
		$existing_refunds = $order->get_refunds();
		if ( ! empty( $existing_refunds ) ) {
			$force_partial = true;
			$force_full = false; // Can't do full refund if already has refunds
		}

		// Calculate already refunded quantities
		$refunded_qty_by_item = self::calculate_refunded_quantities( $existing_refunds );

		// Determine refund type (full or partial)
		if ( null !== $force_full ) {
			// Explicit full/partial specified (batch mode with exact ratios)
			$is_full_refund = $force_full;
		} else {
			// Legacy random logic (single order generation or old code)
			$is_full_refund = $force_partial ? false : wp_rand( 0, 1 );
		}

		// Build refund line items
		$line_items = $is_full_refund
			? self::build_full_refund_items( $order, $refunded_qty_by_item )
			: self::build_partial_refund_items( $order, $refunded_qty_by_item );

		// Ensure we have items to refund
		if ( empty( $line_items ) ) {
			error_log( sprintf(
				'Refund skipped for order %d: No line items to refund. Order has %d items.',
				$order->get_id(),
				count( $order->get_items( array( 'line_item', 'fee' ) ) )
			) );
			return false;
		}

		// Calculate refund totals
		$totals = self::calculate_refund_totals( $line_items );
		$refund_amount = $totals['amount'];
		$total_items = $totals['total_items'];
		$total_qty = $totals['total_qty'];

		// For full refunds, use order's actual remaining total to avoid rounding discrepancies
		if ( $is_full_refund ) {
			$refund_amount = round( $order->get_total() - $order->get_total_refunded(), 2 );
		}

		// For partial refunds, ensure refund is < 50% of order total
		if ( ! $is_full_refund ) {
			$max_partial_refund = $order->get_total() * self::MAX_PARTIAL_REFUND_RATIO;

			// Remove items until refund is under threshold
			while ( $refund_amount >= $max_partial_refund && count( $line_items ) > 1 ) {
				unset( $line_items[ array_rand( $line_items ) ] );
				$totals = self::calculate_refund_totals( $line_items );
				$refund_amount = $totals['amount'];
				$total_items = $totals['total_items'];
				$total_qty = $totals['total_qty'];
			}
		}

		// Cap refund amount to maximum available
		$max_refund = round( $order->get_total() - $order->get_total_refunded(), 2 );
		if ( $refund_amount > $max_refund ) {
			$refund_amount = $max_refund;
		}

		// Validate refund amount
		if ( $refund_amount <= 0 ) {
			error_log( sprintf(
				'Refund skipped for order %d: Invalid refund amount (%s). Order total: %s, Already refunded: %s',
				$order->get_id(),
				$refund_amount,
				$order->get_total(),
				$order->get_total_refunded()
			) );
			return false;
		}

		// Create refund reason
		$reason = $is_full_refund
			? 'Full refund'
			: sprintf(
				'Partial refund - %d %s, %d %s',
				$total_items,
				$total_items === 1 ? 'product' : 'products',
				$total_qty,
				$total_qty === 1 ? 'item' : 'items'
			);

		// Calculate refund date
		$refund_date = self::calculate_refund_date( $order, $previous_refund );

		// Create the refund
		$refund = wc_create_refund(
			array(
				'order_id'     => $order->get_id(),
				'amount'       => $refund_amount,
				'reason'       => $reason,
				'line_items'   => $line_items,
				'date_created' => $refund_date,
			)
		);

		if ( is_wp_error( $refund ) ) {
			error_log( sprintf(
				"Refund creation failed for order %d:\nError: %s\nCalculated Amount: %s\nOrder Total: %s\nOrder Refunded Total: %s\nReason: %s\nLine Items: %s",
				$order->get_id(),
				$refund->get_error_message(),
				$refund_amount,
				$order->get_total(),
				$order->get_total_refunded(),
				$reason,
				print_r( $line_items, true )
			) );
			return false;
		}

		// Update order status to refunded if it's a full refund
		if ( $is_full_refund ) {
			$order->set_status( 'refunded' );
			$order->save();
		}

		return $refund;
	}

	/**
	 * Calculate already refunded quantities per item from existing refunds.
	 *
	 * @param array $existing_refunds Array of existing refund objects.
	 * @return array Associative array of item_id => refunded_quantity.
	 */
	protected static function calculate_refunded_quantities( $existing_refunds ) {
		$refunded_qty_by_item = array();

		foreach ( $existing_refunds as $existing_refund ) {
			foreach ( $existing_refund->get_items( array( 'line_item', 'fee' ) ) as $refund_item ) {
				$item_id = $refund_item->get_meta( '_refunded_item_id' );
				if ( ! $item_id ) {
					continue;
				}
				if ( ! isset( $refunded_qty_by_item[ $item_id ] ) ) {
					$refunded_qty_by_item[ $item_id ] = 0;
				}
				$refunded_qty_by_item[ $item_id ] += abs( $refund_item->get_quantity() );
			}
		}

		return $refunded_qty_by_item;
	}

	/**
	 * Build a refund line item with proper tax and total calculations.
	 *
	 * @param \WC_Order_Item $item Order item to refund.
	 * @param int            $refund_qty Quantity to refund.
	 * @param int            $original_qty Original quantity of the item.
	 * @return array Refund line item data.
	 */
	protected static function build_refund_line_item( $item, $refund_qty, $original_qty ) {
		$taxes      = $item->get_taxes();
		$refund_tax = array();

		// Prorate tax based on refund quantity
		if ( ! empty( $taxes['total'] ) && $original_qty > 0 ) {
			foreach ( $taxes['total'] as $tax_id => $tax_amount ) {
				$tax_per_unit = $tax_amount / $original_qty;
				$refund_tax[ $tax_id ] = ( $tax_per_unit * $refund_qty ) * -1;
			}
		}

		// Prorate the refund total based on refund quantity
		$total_per_unit = $original_qty > 0 ? $item->get_total() / $original_qty : 0;
		$refund_total = $total_per_unit * $refund_qty;

		return array(
			'qty'          => $refund_qty,
			'refund_total' => $refund_total * -1,
			'refund_tax'   => $refund_tax,
		);
	}

	/**
	 * Build line items for a full refund.
	 *
	 * @param \WC_Order $order Order to refund.
	 * @param array     $refunded_qty_by_item Already refunded quantities.
	 * @return array Refund line items.
	 */
	protected static function build_full_refund_items( $order, $refunded_qty_by_item ) {
		$line_items = array();

		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
			$original_qty = $item->get_quantity();
			$refunded_qty = isset( $refunded_qty_by_item[ $item_id ] ) ? $refunded_qty_by_item[ $item_id ] : 0;
			$remaining_qty = $original_qty - $refunded_qty;

			// Skip if nothing left to refund or invalid quantity
			if ( $remaining_qty <= 0 || $original_qty <= 0 ) {
				continue;
			}

			$line_items[ $item_id ] = self::build_refund_line_item( $item, $remaining_qty, $original_qty );
		}

		return $line_items;
	}

	/**
	 * Build line items for a partial refund.
	 *
	 * @param \WC_Order $order Order to refund.
	 * @param array     $refunded_qty_by_item Already refunded quantities.
	 * @return array Refund line items.
	 */
	protected static function build_partial_refund_items( $order, $refunded_qty_by_item ) {
		$items = $order->get_items( array( 'line_item', 'fee' ) );
		$line_items = array();

		// Decide whether to refund full items or partial quantities
		$refund_full_items = (bool) wp_rand( 0, 1 );

		if ( $refund_full_items && count( $items ) > 2 ) {
			// Refund a random subset of items completely (requires at least 3 items)
			$items_array  = array_values( $items );
			$num_to_refund = wp_rand( 1, count( $items_array ) - 1 );
			$items_to_refund = array_rand( $items_array, $num_to_refund );

			// Ensure $items_to_refund is always an array for consistent iteration
			if ( ! is_array( $items_to_refund ) ) {
				$items_to_refund = array( $items_to_refund );
			}

			foreach ( $items_to_refund as $index ) {
				$item = $items_array[ $index ];
				$item_id = $item->get_id();
				$original_qty = $item->get_quantity();
				$refunded_qty = isset( $refunded_qty_by_item[ $item_id ] ) ? $refunded_qty_by_item[ $item_id ] : 0;
				$remaining_qty = $original_qty - $refunded_qty;

				// Skip if nothing left to refund or invalid quantity
				if ( $remaining_qty <= 0 || $original_qty <= 0 ) {
					continue;
				}

				$line_items[ $item_id ] = self::build_refund_line_item( $item, $remaining_qty, $original_qty );
			}
		} else {
			// Refund partial quantities of items
			foreach ( $items as $item_id => $item ) {
				$original_qty = $item->get_quantity();
				$refunded_qty = isset( $refunded_qty_by_item[ $item_id ] ) ? $refunded_qty_by_item[ $item_id ] : 0;
				$remaining_qty = $original_qty - $refunded_qty;

				// Skip if nothing left to refund, if only 1 remaining, or invalid quantity
				if ( $remaining_qty <= 1 || $original_qty <= 0 ) {
					continue;
				}

				// Only refund line items with remaining quantity > 1
				if ( 'line_item' === $item->get_type() ) {
					$refund_qty = wp_rand( 1, $remaining_qty - 1 );
					$line_items[ $item_id ] = self::build_refund_line_item( $item, $refund_qty, $original_qty );
					break; // Only refund one item partially
				}
			}

			// If no items were added, refund one complete remaining item
			if ( empty( $line_items ) && count( $items ) > 0 ) {
				$items_array = array_values( $items );
				shuffle( $items_array );

				foreach ( $items_array as $item ) {
					$item_id = $item->get_id();
					$original_qty = $item->get_quantity();
					$refunded_qty = isset( $refunded_qty_by_item[ $item_id ] ) ? $refunded_qty_by_item[ $item_id ] : 0;
					$remaining_qty = $original_qty - $refunded_qty;

					// Skip if nothing left to refund or invalid quantity
					if ( $remaining_qty <= 0 || $original_qty <= 0 ) {
						continue;
					}

					$line_items[ $item_id ] = self::build_refund_line_item( $item, $remaining_qty, $original_qty );
					break; // Only refund one item
				}
			}
		}

		return $line_items;
	}

	/**
	 * Calculate total refund amount and item counts from line items.
	 *
	 * @param array $line_items Refund line items.
	 * @return array Array containing 'amount', 'total_items', and 'total_qty'.
	 */
	protected static function calculate_refund_totals( $line_items ) {
		$refund_amount = 0;
		$total_items   = 0;
		$total_qty     = 0;

		foreach ( $line_items as $item_data ) {
			// Add item total: refund amounts are stored as negative, convert to positive for total calculation
			$refund_amount += abs( $item_data['refund_total'] );
			$total_items++;
			$total_qty += $item_data['qty'];

			// Add tax amounts
			if ( ! empty( $item_data['refund_tax'] ) ) {
				foreach ( $item_data['refund_tax'] as $tax_amount ) {
					$refund_amount += abs( $tax_amount );
				}
			}
		}

		return array(
			'amount'      => round( $refund_amount, 2 ),
			'total_items' => $total_items,
			'total_qty'   => $total_qty,
		);
	}

	/**
	 * Calculate a realistic refund date based on order completion or previous refund.
	 * Ensures refund dates never exceed current time and second refunds always occur after first.
	 *
	 * @param \WC_Order             $order Order being refunded.
	 * @param \WC_Order_Refund|null $previous_refund Previous refund (for second refunds).
	 * @return string Refund date in 'Y-m-d H:i:s' format.
	 */
	protected static function calculate_refund_date( $order, $previous_refund = null ) {
		$now = time();

		if ( $previous_refund ) {
			// Second refund: must be after first refund but before current time
			$base_timestamp = strtotime( $previous_refund->get_date_created()->date( 'Y-m-d H:i:s' ) );
			$max_timestamp = min( $base_timestamp + ( self::SECOND_REFUND_MAX_DAYS * DAY_IN_SECONDS ), $now );

			// Ensure second refund is always after first refund
			if ( $max_timestamp <= $base_timestamp ) {
				// If there's no time window, use base timestamp + 1 hour
				// If base is in the future, second refund will also be in the future (but after first)
				$refund_timestamp = $base_timestamp + HOUR_IN_SECONDS;
			} else {
				$refund_timestamp = wp_rand( $base_timestamp + 1, $max_timestamp );
			}
		} else {
			// First refund: within 2 months of order completion, but never in the future
			$completion_timestamp = strtotime( $order->get_date_completed()->date( 'Y-m-d H:i:s' ) );
			$max_timestamp = min( $completion_timestamp + ( self::FIRST_REFUND_MAX_DAYS * DAY_IN_SECONDS ), $now );

			// Ensure we have a valid time window
			if ( $max_timestamp < $completion_timestamp ) {
				// Order completed in the future somehow, use current time
				$refund_timestamp = $now;
			} elseif ( $max_timestamp == $completion_timestamp ) {
				// No time window, use completion timestamp
				$refund_timestamp = $completion_timestamp;
			} else {
				$refund_timestamp = wp_rand( $completion_timestamp, $max_timestamp );
			}
		}

		return date( 'Y-m-d H:i:s', $refund_timestamp );
	}

	/**
	 * Generate an array of sorted dates for batch order creation.
	 * Ensures chronological order when creating multiple orders.
	 *
	 * @param int   $count Number of dates to generate.
	 * @param array $args  Arguments containing date-start and optional date-end.
	 * @return array Sorted array of date strings (Y-m-d).
	 */
	protected static function generate_batch_dates( $count, $args ) {
		$current = date( 'Y-m-d', time() );

		if ( ! empty( $args['date-start'] ) && empty( $args['date-end'] ) ) {
			$start = $args['date-start'];
			$end   = $current;
		} elseif ( ! empty( $args['date-start'] ) && ! empty( $args['date-end'] ) ) {
			$start = $args['date-start'];
			$end   = $args['date-end'];
		} else {
			// No date range specified, return array of current dates
			return array_fill( 0, $count, $current );
		}

		$start_timestamp = strtotime( $start );
		$end_timestamp   = strtotime( $end );
		$days_between    = (int) ( ( $end_timestamp - $start_timestamp ) / DAY_IN_SECONDS );

		// If start and end dates are the same, return array of that date
		if ( 0 === $days_between ) {
			return array_fill( 0, $count, date( 'Y-m-d', $start_timestamp ) );
		}

		$dates = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$random_days = wp_rand( 0, $days_between );
			$dates[] = date( 'Y-m-d', $start_timestamp + ( $random_days * DAY_IN_SECONDS ) );
		}

		// Sort chronologically so lower order IDs get earlier dates
		sort( $dates );

		return $dates;
	}

	/**
	 * Generate coupon flags for exact ratio distribution in batch mode.
	 * Creates pre-generated array for exact distribution of coupons.
	 *
	 * Memory Considerations:
	 * - Pre-generating arrays ensures exact ratio distribution but consumes memory
	 * - For batches > EXACT_RATIO_BATCH_THRESHOLD (10,000), returns null to use probabilistic approach
	 * - Typical memory usage: ~100-150 bytes per element (PHP array overhead)
	 * - Example: 10,000 orders at 0.5 ratio ≈ 1-2MB per flag array
	 *
	 * @param int   $count Number of orders to generate.
	 * @param array $args  Arguments containing ratio parameters.
	 * @return array|null Array of boolean flags, or null to use probabilistic approach.
	 */
	protected static function generate_coupon_flags( $count, $args ) {
		// For large batches above threshold, skip exact ratio and use probabilistic approach
		if ( $count > self::EXACT_RATIO_BATCH_THRESHOLD ) {
			$message = sprintf(
				'Batch size (%d) exceeds threshold (%d). Using probabilistic distribution instead of exact ratios to optimize memory usage.',
				$count,
				self::EXACT_RATIO_BATCH_THRESHOLD
			);

			if ( class_exists( 'WP_CLI' ) ) {
				\WP_CLI::warning( $message );
			}
			error_log( 'WC Smooth Generator: ' . $message );
			return null;
		}

		// Generate coupon flags if coupon-ratio is set
		if ( isset( $args['coupon-ratio'] ) ) {
			$coupon_ratio = floatval( $args['coupon-ratio'] );
			$coupon_ratio = max( 0.0, min( 1.0, $coupon_ratio ) );

			$num_with_coupons = (int) round( $count * $coupon_ratio );
			$num_without = $count - $num_with_coupons;

			// Create array with exact counts
			$flags = array_merge(
				array_fill( 0, $num_with_coupons, true ),
				array_fill( 0, $num_without, false )
			);

			// Shuffle for randomness
			shuffle( $flags );

			return $flags;
		}

		return null;
	}

	/**
	 * Generate refund flags for exact ratio distribution in batch mode.
	 * Creates pre-generated array for exact distribution of refunds.
	 *
	 * Memory Considerations:
	 * - Pre-generating arrays ensures exact ratio distribution but consumes memory
	 * - For batches > EXACT_RATIO_BATCH_THRESHOLD (10,000), returns null to use probabilistic approach
	 * - Typical memory usage: ~100-150 bytes per element (PHP array overhead)
	 * - Example: 10,000 orders at 0.5 ratio ≈ 1-2MB per flag array
	 *
	 * @param int   $count Number of orders to generate.
	 * @param array $args  Arguments containing ratio parameters.
	 * @return array|null Array of refund type constants, or null to use probabilistic approach.
	 */
	protected static function generate_refund_flags( $count, $args ) {
		// For large batches above threshold, skip exact ratio and use probabilistic approach
		if ( $count > self::EXACT_RATIO_BATCH_THRESHOLD ) {
			$message = sprintf(
				'Batch size (%d) exceeds threshold (%d). Using probabilistic distribution instead of exact ratios to optimize memory usage.',
				$count,
				self::EXACT_RATIO_BATCH_THRESHOLD
			);

			if ( class_exists( 'WP_CLI' ) ) {
				\WP_CLI::warning( $message );
			}
			error_log( 'WC Smooth Generator: ' . $message );
			return null;
		}

		// Generate refund flags if refund-ratio is set and status is completed
		if ( isset( $args['refund-ratio'] ) && 'completed' === ( $args['status'] ?? '' ) ) {
			$refund_ratio = floatval( $args['refund-ratio'] );
			$refund_ratio = max( 0.0, min( 1.0, $refund_ratio ) );

			$total_refunds = (int) round( $count * $refund_ratio );

			// Split refunds: 50% full, 25% single partial, 25% multi-partial
			$num_full = (int) floor( $total_refunds * self::REFUND_DISTRIBUTION_FULL_RATIO );
			$num_partial = (int) floor( $total_refunds * self::REFUND_DISTRIBUTION_PARTIAL_RATIO );
			$num_multi = $total_refunds - $num_full - $num_partial; // Remainder goes to multi
			$num_none = $count - $total_refunds;

			// Create array with exact counts using integer constants for memory efficiency
			$flags = array_merge(
				array_fill( 0, $num_full, self::REFUND_TYPE_FULL ),
				array_fill( 0, $num_partial, self::REFUND_TYPE_PARTIAL ),
				array_fill( 0, $num_multi, self::REFUND_TYPE_MULTI ),
				array_fill( 0, $num_none, self::REFUND_TYPE_NONE )
			);

			// Shuffle for randomness
			shuffle( $flags );

			return $flags;
		}

		return null;
	}
}
