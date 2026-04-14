<?php
/**
 * WP-CLI functionality.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator;

use WP_CLI, WP_CLI_Command;

/**
 * WP-CLI Integration class
 */
class CLI extends WP_CLI_Command {
	/**
	 * Generate products.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function products( $args, $assoc_args ) {
		list( $amount ) = $args;
		$amount = absint( $amount );

		$time_start = microtime( true );

		WP_CLI::line( 'Initializing...' );

		// Pre-generate images. Min 20, max 100.
		Generator\Product::seed_images( min( $amount + 19, 100 ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating products', $amount );

		add_action(
			'smoothgenerator_product_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Product::MAX_BATCH_SIZE );

			$result = Generator\Product::batch( $batch, $assoc_args );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result );
			}

			$generated        += count( $result );
			$remaining_amount -= $batch;
		}

		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $generated . ' products generated in ' . $display_time );
	}

	/**
	 * Generate orders.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function orders( $args, $assoc_args ) {
		list( $amount ) = $args;
		$amount = absint( $amount );

		$time_start  = microtime( true );
		$bulk_insert = ! empty( $assoc_args['bulk-insert'] );

		if ( ! empty( $assoc_args['status'] ) ) {
			$status = $assoc_args['status'];
			if ( ! wc_is_order_status( 'wc-' . $status ) ) {
				WP_CLI::error( "The argument \"$status\" is not a valid order status." );
				return;
			}
		}

		if ( $bulk_insert ) {
			if ( ! Generator\OrderBulkInserter::is_hpos_enabled() ) {
				WP_CLI::error(
					'--bulk-insert requires WooCommerce HPOS (High-Performance Order Storage) to be enabled. '
					. 'Enable it under WooCommerce > Settings > Advanced > Features.'
				);
				return;
			}
			WP_CLI::log( 'Bulk-insert mode: writing directly into HPOS tables (no ORM, no refunds).' );
			WP_CLI::log( 'Run `wp wc sync analytics` after generation to populate Analytics report data.' );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating orders', $amount );

		add_action(
			'smoothgenerator_order_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$generated = 0;

		if ( $bulk_insert ) {
			$result = Generator\Order::bulk_insert( $amount, $assoc_args );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
				return;
			}

			$generated = count( $result );
		} else {
			$remaining_amount = $amount;

			while ( $remaining_amount > 0 ) {
				$batch = min( $remaining_amount, Generator\Order::MAX_BATCH_SIZE );

				$result = Generator\Order::batch( $batch, $assoc_args );

				if ( is_wp_error( $result ) ) {
					WP_CLI::error( $result );
				}

				$generated        += count( $result );
				$remaining_amount -= $batch;
			}
		}

		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		if ( $generated === 0 && $amount > 0 ) {
			WP_CLI::error( 'No orders were generated. Make sure there are published products in your store.' );
		}

		WP_CLI::success( $generated . ' orders generated in ' . $display_time );
	}

	/**
	 * Generate customers.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function customers( $args, $assoc_args ) {
		list( $amount ) = $args;
		$amount = absint( $amount );

		$time_start = microtime( true );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating customers', $amount );

		add_action(
			'smoothgenerator_customer_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Customer::MAX_BATCH_SIZE );

			$result = Generator\Customer::batch( $batch, $assoc_args );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result );
			}

			$generated        += count( $result );
			$remaining_amount -= $batch;
		}

		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $generated . ' customers generated in ' . $display_time );
	}

	/**
	 * Generate coupons.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function coupons( $args, $assoc_args ) {
		list( $amount ) = $args;
		$amount = absint( $amount );

		$time_start = microtime( true );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating coupons', $amount );

		add_action(
			'smoothgenerator_coupon_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Coupon::MAX_BATCH_SIZE );

			$result = Generator\Coupon::batch( $batch, $assoc_args );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result );
			}

			$generated        += count( $result );
			$remaining_amount -= $batch;
		}

		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $generated . ' coupons generated in ' . $display_time );
	}

	/**
	 * Generate terms for the Product Category taxonomy.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function terms( $args, $assoc_args ) {
		list( $taxonomy, $amount ) = $args;
		$amount = absint( $amount );

		$time_start = microtime( true );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating terms', $amount );

		add_action(
			'smoothgenerator_term_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Term::MAX_BATCH_SIZE );

			$result = Generator\Term::batch( $amount, $taxonomy, $assoc_args );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result );
			}

			$generated        += count( $result );
			$remaining_amount -= $batch;
		}

		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $generated . ' terms generated in ' . $display_time );
	}

	/**
	 * Sync orders into WooCommerce Analytics tables.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function sync_analytics( $args, $assoc_args ) {
		if ( ! Generator\OrderAnalyticsSync::is_hpos_available() ) {
			WP_CLI::error(
				'Analytics sync requires WooCommerce HPOS (High-Performance Order Storage) to be enabled. '
				. 'Enable it under WooCommerce > Settings > Advanced > Features.'
			);
			return;
		}

		$all        = ! empty( $assoc_args['all'] );
		$time_start = microtime( true );

		if ( $all ) {
			$total = Generator\OrderAnalyticsSync::get_total_order_count();
			WP_CLI::log( "Re-syncing all $total orders into Analytics tables." );
			delete_option( Generator\OrderAnalyticsSync::CURSOR_OPTION );
		} else {
			$total = Generator\OrderAnalyticsSync::get_unsynced_count();
			if ( $total === 0 ) {
				WP_CLI::success( 'All orders are already reflected in the Analytics tables.' );
				return;
			}
			WP_CLI::log( "Syncing $total unsynced orders into Analytics tables." );
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Syncing analytics', $total );
		$synced   = 0;

		while ( true ) {
			$batch_size = min( Generator\OrderAnalyticsSync::MAX_BATCH_SIZE, $total - $synced );
			if ( $batch_size <= 0 ) {
				break;
			}

			$result = Generator\OrderAnalyticsSync::batch( $batch_size, $assoc_args );

			if ( empty( $result ) ) {
				break; // No more orders to process.
			}

			$synced += count( $result );
			$progress->tick( count( $result ) );
		}

		$progress->finish();

		if ( $all ) {
			delete_option( Generator\OrderAnalyticsSync::CURSOR_OPTION );
		}

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( "$synced orders synced into Analytics tables in $display_time." );
	}
}

WP_CLI::add_command( 'wc sync analytics', array( 'WC\SmoothGenerator\CLI', 'sync_analytics' ), array(
	'shortdesc' => 'Sync orders into WooCommerce Analytics tables.',
	'synopsis'  => array(
		array(
			'name'        => 'all',
			'type'        => 'flag',
			'description' => 'Re-sync every order, including those already in the Analytics tables. Useful after bulk imports. Without this flag only orders missing from the Analytics tables are processed.',
			'optional'    => true,
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc sync analytics\n\nwc sync analytics --all",
) );

WP_CLI::add_command( 'wc generate products', array( 'WC\SmoothGenerator\CLI', 'products' ), array(
	'shortdesc' => 'Generate products.',
	'synopsis'  => array(
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of products to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'type',
			'type'        => 'assoc',
			'description' => 'Specify one type of product to generate. Otherwise defaults to a mix.',
			'optional'    => true,
			'options'     => array( 'simple', 'variable' ),
		),
		array(
			'name'        => 'use-existing-terms',
			'type'        => 'flag',
			'description' => 'Only apply existing categories and tags to products, rather than generating new ones.',
			'optional'    => true,
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate products 10\n\nwc generate products 20 --type=variable --use-existing-terms",
) );

WP_CLI::add_command( 'wc generate orders', array( 'WC\SmoothGenerator\CLI', 'orders' ), array(
	'shortdesc' => 'Generate orders.',
	'synopsis'  => array(
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of orders to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'date-start',
			'type'        => 'assoc',
			'description' => 'Randomize the order date using this as the lower limit. Format as YYYY-MM-DD.',
			'optional'    => true,
		),
		array(
			'name'        => 'date-end',
			'type'        => 'assoc',
			'description' => 'Randomize the order date using this as the upper limit. Only works in conjunction with date-start. Format as YYYY-MM-DD.',
			'optional'    => true,
		),
		array(
			'name'        => 'status',
			'type'        => 'assoc',
			'description' => 'Specify one status for all the generated orders. Otherwise defaults to a mix.',
			'optional'    => true,
			'options'     => array( 'completed', 'processing', 'on-hold', 'failed' ),
		),
		array(
			'name'        => 'coupons',
			'type'        => 'flag',
			'description' => 'Create and apply a coupon to each generated order. Equivalent to --coupon-ratio=1.0.',
			'optional'    => true,
		),
		array(
			'name'        => 'coupon-ratio',
			'type'        => 'assoc',
			'description' => 'Decimal ratio (0.0-1.0) of orders that should have coupons applied. If no coupons exist, 6 will be created (3 fixed value, 3 percentage). Note: Decimal values are converted to percentages using integer rounding (e.g., 0.505 becomes 50%).',
			'optional'    => true,
		),
		array(
			'name'        => 'refund-ratio',
			'type'        => 'assoc',
			'description' => 'Decimal ratio (0.0-1.0) of completed orders that should be refunded (wholly or partially). Note: Decimal values are converted to percentages using integer rounding (e.g., 0.505 becomes 50%).',
			'optional'    => true,
		),
		array(
			'name'        => 'skip-order-attribution',
			'type'        => 'flag',
			'description' => 'Skip adding order attribution meta to the generated orders.',
			'optional'    => true,
		),
		array(
			'name'        => 'bulk-insert',
			'type'        => 'flag',
			'description' => 'Write orders directly into HPOS tables via raw SQL, bypassing the WC_Order ORM. Requires HPOS to be enabled. Much faster for large volumes but skips refunds. Combine with --coupons, --shipping, --taxes for those features. Run `wp wc sync analytics` after to populate Analytics report data.',
			'optional'    => true,
		),
		array(
			'name'        => 'shipping',
			'type'        => 'flag',
			'description' => 'Bulk-insert only: add a shipping line item to each order using a random enabled shipping zone method. If no shipping zones are defined, shipping is skipped.',
			'optional'    => true,
		),
		array(
			'name'        => 'taxes',
			'type'        => 'flag',
			'description' => 'Bulk-insert only: add a tax line item to each order using a random defined tax rate. If no tax rates are defined, taxes are skipped.',
			'optional'    => true,
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate orders 10\n\nwc generate orders 50 --date-start=2020-01-01 --date-end=2022-12-31 --status=completed --coupons\n\nwc generate orders 1000000 --bulk-insert --status=completed --date-start=2020-01-01 --date-end=2024-12-31\n\nwc generate orders 1000000 --bulk-insert --coupons --shipping --taxes --date-start=2020-01-01 --date-end=2024-12-31",
) );

WP_CLI::add_command( 'wc generate customers', array( 'WC\SmoothGenerator\CLI', 'customers' ), array(
	'shortdesc' => 'Generate customers.',
	'synopsis'  => array(
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of customers to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'country',
			'type'        => 'assoc',
			'description' => 'The ISO 3166-1 alpha-2 country code to use for localizing the customer data. If none is specified, any country in the "Selling location(s)" setting may be used.',
			'optional'    => true,
			'default'     => '',
		),
		array(
			'name'        => 'type',
			'type'        => 'assoc',
			'description' => 'The type of customer to generate data for. If none is specified, it will be a 70% person, 30% company mix.',
			'optional'    => true,
			'options'     => array( 'company', 'person' ),
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate customers 10\n\nwc generate customers --country=ES --type=company",
) );

WP_CLI::add_command( 'wc generate coupons', array( 'WC\SmoothGenerator\CLI', 'coupons' ), array(
	'shortdesc' => 'Generate coupons.',
	'synopsis'  => array(
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of coupons to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'min',
			'type'        => 'assoc',
			'description' => 'Specify the minimum discount of each coupon, as an integer.',
			'optional'    => true,
			'default'     => 5,
		),
		array(
			'name'        => 'max',
			'type'        => 'assoc',
			'description' => 'Specify the maximum discount of each coupon, as an integer.',
			'optional'    => true,
			'default'     => 100,
		),
		array(
			'name'        => 'discount_type',
			'type'        => 'assoc',
			'description' => 'The type of discount for the coupon. If not specified, defaults to WooCommerce default (fixed_cart).',
			'optional'    => true,
			'options'     => array( 'fixed_cart', 'percent' ),
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate coupons 10\n\nwc generate coupons 50 --min=1 --max=50\n\nwc generate coupons 20 --discount_type=percent --min=5 --max=25",
) );

WP_CLI::add_command( 'wc generate terms', array( 'WC\SmoothGenerator\CLI', 'terms' ), array(
	'shortdesc' => 'Generate product categories.',
	'synopsis'  => array(
		array(
			'name'        => 'taxonomy',
			'type'        => 'positional',
			'description' => 'The taxonomy to generate the terms for.',
			'options'     => array( 'product_cat', 'product_tag' ),
		),
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of terms to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'max-depth',
			'type'        => 'assoc',
			'description' => 'The maximum number of hierarchy levels for the terms. A value of 1 means all categories will be top-level. Max value 5. Only applies to taxonomies that are hierarchical.',
			'optional'    => true,
			'options'     => array( 1, 2, 3, 4, 5 ),
			'default'     => 1,
		),
		array(
			'name'        => 'parent',
			'type'        => 'assoc',
			'description' => 'Specify an existing term ID as the parent for the new terms. Only applies to taxonomies that are hierarchical.',
			'optional'    => true,
			'default'     => 0,
		),
	),
	'longdesc' => "## EXAMPLES\n\nwc generate terms product_tag 10\n\nwc generate terms product_cat 50 --max-depth=3",
) );
