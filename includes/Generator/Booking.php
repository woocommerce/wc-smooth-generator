<?php
/**
 * Booking data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Booking data generator.
 *
 * Requires the WooCommerce Bookings extension to be active.
 */
class Booking extends Generator {

	/**
	 * Cache of bookable product IDs to avoid repeated queries.
	 *
	 * @var array
	 */
	private static $bookable_product_ids = array();

	/**
	 * Cache of customer IDs.
	 *
	 * @var array
	 */
	private static $customer_ids = array();

	/**
	 * Product types to auto-create when no bookable products exist.
	 *
	 * Each entry generates one product via Product::generate() to provide variety.
	 *
	 * @var string[]
	 */
	private static $auto_create_types = array( 'booking', 'booking', 'bookable-service' );

	/**
	 * Check whether WooCommerce Bookings is active.
	 *
	 * This is the canonical check used across all generators and admin UI.
	 *
	 * @return bool
	 */
	public static function is_bookings_active() {
		return class_exists( 'WC_Bookings' ) || function_exists( 'create_wc_booking' );
	}

	/**
	 * Check whether the experimental Bookings product types are available.
	 *
	 * The bookable-service and bookable-event types require WC_BOOKINGS_EXPERIMENTAL_ENABLED.
	 *
	 * @return bool
	 */
	public static function is_bookings_experimental_active() {
		return self::is_bookings_active() && class_exists( 'WC_Product_Bookable_Service' );
	}

	/**
	 * Check that WooCommerce Bookings is active, returning WP_Error if not.
	 *
	 * @return true|\WP_Error
	 */
	private static function check_dependencies() {
		if ( ! self::is_bookings_active() ) {
			return new \WP_Error(
				'smoothgenerator_missing_bookings',
				'WooCommerce Bookings extension is not installed or active. Please install and activate it before generating bookings.'
			);
		}

		return true;
	}

	/**
	 * Return a new booking.
	 *
	 * @param bool  $save       Save the object before returning or not.
	 * @param array $assoc_args Arguments passed via the CLI for additional customization.
	 *
	 * @return int|array|\WP_Error Booking ID when $save is true, unsaved booking data array when $save is false, or WP_Error on failure.
	 */
	public static function generate( $save = true, array $assoc_args = array() ) {
		$check = self::check_dependencies();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		parent::maybe_initialize_generators();

		$args = wp_parse_args(
			$assoc_args,
			array(
				'product-id'  => 0,
				'date-start'  => gmdate( 'Y-m-d', strtotime( '-14 days' ) ),
				'date-end'    => gmdate( 'Y-m-d', strtotime( '+42 days' ) ),
				'status'      => '',
				'with-orders' => true,
			)
		);

		// Get a bookable product.
		$product_id = absint( $args['product-id'] );

		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || ! $product->is_type( 'booking' ) ) {
				return new \WP_Error(
					'smoothgenerator_invalid_product',
					sprintf( 'Product ID %d is not a valid bookable product.', $product_id )
				);
			}
		} else {
			$product_id = self::get_random_bookable_product_id();
			if ( is_wp_error( $product_id ) ) {
				return $product_id;
			}
			$product = wc_get_product( $product_id );
		}

		// Get a customer.
		$customer_id = self::get_random_customer_id();

		// Generate booking dates.
		$dates = self::get_random_booking_dates( $product, $args['date-start'], $args['date-end'] );

		// Determine status.
		$status = ! empty( $args['status'] ) ? $args['status'] : self::get_random_status();

		// Build booking data.
		$booking_data = array(
			'product_id' => $product_id,
			'start_date' => $dates['start'],
			'end_date'   => $dates['end'],
			'user_id'    => $customer_id,
		);

		// Add person counts if the product supports them.
		if ( method_exists( $product, 'get_has_persons' ) && $product->get_has_persons() ) {
			$min_persons             = max( 1, $product->get_min_persons() );
			$max_persons             = max( $min_persons, $product->get_max_persons() );
			$booking_data['persons'] = wp_rand( $min_persons, $max_persons );
		}

		// Add resource if the product has resources.
		if ( method_exists( $product, 'get_has_resources' ) && $product->get_has_resources() ) {
			$resource_ids = $product->get_resource_ids();
			if ( ! empty( $resource_ids ) ) {
				$booking_data['resource_id'] = $resource_ids[ array_rand( $resource_ids ) ];
			}
		}

		if ( ! $save ) {
			return $booking_data;
		}

		// Create the booking using WooCommerce Bookings' helper.
		$booking = create_wc_booking( $product_id, $booking_data, $status, true );

		if ( is_wp_error( $booking ) ) {
			return $booking;
		}

		$booking_id = is_object( $booking ) ? $booking->get_id() : $booking;

		// Create an associated order if requested.
		if ( ! empty( $args['with-orders'] ) && 'cancelled' !== $status ) {
			$booking_object = is_object( $booking ) ? $booking : get_wc_booking( $booking_id );
			if ( is_object( $booking_object ) ) {
				self::create_associated_order( $booking_object, $product, $customer_id, $status );
			}
		}

		/**
		 * Action: Booking generator returned a new booking.
		 *
		 * @since 1.4.0
		 *
		 * @param int $booking_id The ID of the generated booking.
		 */
		do_action( 'smoothgenerator_booking_generated', $booking_id );

		return $booking_id;
	}

	/**
	 * Create multiple bookings.
	 *
	 * @param int   $amount The number of bookings to create.
	 * @param array $args   Additional args for booking creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, array $args = array() ) {
		$check = self::check_dependencies();
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$booking_ids = array();

		for ( $i = 1; $i <= $amount; $i++ ) {
			$result = self::generate( true, $args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$booking_ids[] = $result;
		}

		return $booking_ids;
	}

	/**
	 * Get a random bookable product ID, creating products if none exist.
	 *
	 * @return int|\WP_Error Product ID on success.
	 */
	private static function get_random_bookable_product_id() {
		if ( empty( self::$bookable_product_ids ) ) {
			// Query for existing bookable products.
			$existing = wc_get_products(
				array(
					'type'   => 'booking',
					'status' => 'publish',
					'limit'  => 50,
					'return' => 'ids',
				)
			);

			if ( ! empty( $existing ) ) {
				self::$bookable_product_ids = $existing;
			} else {
				// Create a set of varied bookable products.
				$created = self::create_bookable_products();
				if ( is_wp_error( $created ) ) {
					return $created;
				}
				self::$bookable_product_ids = $created;
			}
		}

		return self::$bookable_product_ids[ array_rand( self::$bookable_product_ids ) ];
	}

	/**
	 * Create a set of varied bookable products using the Product generator.
	 *
	 * Delegates to Product::generate() so product creation logic is not duplicated.
	 *
	 * @return int[]|\WP_Error Array of product IDs on success.
	 */
	private static function create_bookable_products() {
		$product_ids = array();

		foreach ( self::$auto_create_types as $type ) {
			$product = Product::generate( true, array( 'type' => $type ) );
			if ( is_wp_error( $product ) ) {
				return $product;
			}
			$product_ids[] = $product->get_id();
		}

		return $product_ids;
	}

	/**
	 * Get a random customer ID, using existing customers or creating one.
	 *
	 * @return int WordPress user ID.
	 */
	private static function get_random_customer_id() {
		if ( empty( self::$customer_ids ) ) {
			$customers = get_users(
				array(
					'role'    => 'customer',
					'fields'  => 'ID',
					'number'  => 50,
					'orderby' => 'rand',
				)
			);

			if ( ! empty( $customers ) ) {
				self::$customer_ids = $customers;
			}
		}

		if ( ! empty( self::$customer_ids ) ) {
			return (int) self::$customer_ids[ array_rand( self::$customer_ids ) ];
		}

		// Create a new customer.
		$customer = Customer::generate( true );
		if ( is_wp_error( $customer ) ) {
			return 0;
		}

		$customer_id          = $customer->get_id();
		self::$customer_ids[] = $customer_id;

		return $customer_id;
	}

	/**
	 * Generate random booking start and end dates based on product settings.
	 *
	 * @param \WC_Product_Booking $product    The bookable product.
	 * @param string              $date_start Lower bound date string (Y-m-d).
	 * @param string              $date_end   Upper bound date string (Y-m-d).
	 *
	 * @return array Array with 'start' and 'end' as Unix timestamps.
	 */
	private static function get_random_booking_dates( $product, $date_start, $date_end ) {
		$start_bound = strtotime( $date_start );
		$end_bound   = strtotime( $date_end );

		if ( ! $start_bound || ! $end_bound || $start_bound >= $end_bound ) {
			$start_bound = strtotime( '-14 days' );
			$end_bound   = strtotime( '+42 days' );
		}

		// Treat date-end as inclusive of the full day so random day selection can land on that date.
		$end_bound += DAY_IN_SECONDS - 1;

		$duration_unit = $product->get_duration_unit();
		$duration      = max( 1, $product->get_duration() );

		if ( 'day' === $duration_unit || 'month' === $duration_unit ) {
			// For day-based bookings, pick a random day.
			$start_timestamp = wp_rand( $start_bound, $end_bound );
			// Normalize to beginning of day.
			$start_timestamp = strtotime( 'midnight', $start_timestamp );

			if ( 'month' === $duration_unit ) {
				$end_timestamp = strtotime( '+' . $duration . ' months', $start_timestamp );
			} else {
				$end_timestamp = strtotime( '+' . $duration . ' days', $start_timestamp );
			}
		} else {
			// For hour/minute-based bookings, pick a random day and time within business hours.
			$random_day      = wp_rand( $start_bound, $end_bound );
			$day_start       = strtotime( 'midnight', $random_day );
			$hour            = wp_rand( 8, 17 ); // 8 AM to 5 PM.
			$minute          = self::$faker->randomElement( array( 0, 15, 30, 45 ) );
			$start_timestamp = $day_start + ( $hour * HOUR_IN_SECONDS ) + ( $minute * MINUTE_IN_SECONDS );

			if ( 'minute' === $duration_unit ) {
				$end_timestamp = $start_timestamp + ( $duration * MINUTE_IN_SECONDS );
			} else {
				$end_timestamp = $start_timestamp + ( $duration * HOUR_IN_SECONDS );
			}
		}

		return array(
			'start' => $start_timestamp,
			'end'   => $end_timestamp,
		);
	}

	/**
	 * Get a random booking status using weighted distribution.
	 *
	 * @return string Booking status slug.
	 */
	private static function get_random_status() {
		return self::random_weighted_element(
			array(
				'paid'                 => 35,
				'confirmed'            => 25,
				'complete'             => 20,
				'unpaid'               => 10,
				'pending-confirmation' => 5,
				'cancelled'            => 5,
			)
		);
	}

	/**
	 * Create an associated WooCommerce order for a booking.
	 *
	 * @param \WC_Booking         $booking     The booking object.
	 * @param \WC_Product_Booking $product     The bookable product.
	 * @param int                 $customer_id The customer user ID.
	 * @param string              $status      The booking status.
	 *
	 * @return int|null Order ID on success, null on failure.
	 */
	private static function create_associated_order( $booking, $product, $customer_id, $status ) {
		if ( ! is_object( $booking ) ) {
			return null;
		}

		$order = wc_create_order(
			array(
				'customer_id' => $customer_id,
				'status'      => self::map_booking_status_to_order_status( $status ),
			)
		);

		if ( is_wp_error( $order ) ) {
			return null;
		}

		// Add the booking product as a line item.
		$cost = $booking->get_cost();
		if ( ! $cost ) {
			$cost = $product->get_price();
		}

		$item = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 1 );
		$item->set_subtotal( $cost );
		$item->set_total( $cost );
		$order->add_item( $item );

		$order->calculate_totals( false );
		$order->save();

		// Link the booking to the order.
		$booking->set_order_id( $order->get_id() );
		$booking->save();

		return $order->get_id();
	}

	/**
	 * Map a booking status to an appropriate WooCommerce order status.
	 *
	 * @param string $booking_status The booking status.
	 *
	 * @return string WooCommerce order status.
	 */
	private static function map_booking_status_to_order_status( $booking_status ) {
		$map = array(
			'paid'                 => 'completed',
			'confirmed'            => 'processing',
			'complete'             => 'completed',
			'unpaid'               => 'pending',
			'pending-confirmation' => 'on-hold',
			'cancelled'            => 'cancelled',
		);

		return isset( $map[ $booking_status ] ) ? $map[ $booking_status ] : 'processing';
	}
}
