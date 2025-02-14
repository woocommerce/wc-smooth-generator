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

		$time_start = microtime( true );

		if ( ! empty( $assoc_args['status'] ) ) {
			$status = $assoc_args['status'];
			if ( ! wc_is_order_status( 'wc-' . $status ) ) {
				WP_CLI::error( "The argument \"$status\" is not a valid order status." );
				return;
			}
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating orders', $amount );

		add_action(
			'smoothgenerator_order_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Order::MAX_BATCH_SIZE );

			$result = Generator\Order::batch( $batch, $assoc_args );

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
	 * Generate subscriptions.
	 *
	 * @param array $args Arguments specified.
	 * @param array $assoc_args Associative arguments specified.
	 */
	public static function subscriptions( $args, $assoc_args ) {
		list( $amount ) = $args;
		$amount = absint( $amount );

		$time_start = microtime( true );

		if ( ! class_exists( 'WC_Subscription' ) ) {
			WP_CLI::error( 'WooCommerce Subscriptions is not active.' );
			return;
		}

		if ( ! empty( $assoc_args['status'] ) ) {
			$status = $assoc_args['status'];
			if ( ! array_key_exists( 'wc-' . $status, wcs_get_subscription_statuses() ) ) {
				WP_CLI::error( "The argument \"$status\" is not a valid subscription status." );
				return;
			}
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating subscriptions', $amount );

		add_action(
			'smoothgenerator_subscription_generated',
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$remaining_amount = $amount;
		$generated        = 0;

		while ( $remaining_amount > 0 ) {
			$batch = min( $remaining_amount, Generator\Subscription::MAX_BATCH_SIZE );

			$result = Generator\Subscription::batch( $batch, $assoc_args );

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

		WP_CLI::success( $generated . ' subscriptions generated in ' . $display_time );
	}
}

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
			'description' => 'Create and apply a coupon to each generated order.',
			'optional'    => true,
		),
		array(
			'name'        => 'skip-order-attribution',
			'type'        => 'flag',
			'description' => 'Skip adding order attribution meta to the generated orders.',
			'optional'    => true,
		)
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate orders 10\n\nwc generate orders 50 --date-start=2020-01-01 --date-end=2022-12-31 --status=completed --coupons",
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
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate coupons 10\n\nwc generate coupons 50 --min=1 --max=50",
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

WP_CLI::add_command( 'wc generate subscriptions', array( 'WC\SmoothGenerator\CLI', 'subscriptions' ), array(
	'shortdesc' => 'Generate subscriptions.',
	'synopsis'  => array(
		array(
			'name'        => 'amount',
			'type'        => 'positional',
			'description' => 'The number of orders to generate.',
			'optional'    => true,
			'default'     => 10,
		),
		array(
			'name'        => 'billing-period',
			'type'        => 'assoc',
			'description' => 'Specify a billing period for all the generated subscriptions. Otherwise defaults to a mix.',
			'optional'    => true,
			'options'     => array( 'day', 'week', 'month', 'year' ),
		),
		array(
			'name'        => 'billing-interval',
			'type'        => 'assoc',
			'description' => 'Specify a billing interval (1-6) for all the generated subscriptions. Otherwise defaults to a mix.',
			'optional'    => true,
			'options'     => array( '1', '2', '3', '4', '5', '6' ),
		),
		array(
			'name'        => 'status',
			'type'        => 'assoc',
			'description' => 'Specify one status for all the generated subscriptions. Otherwise defaults to a mix.',
			'optional'    => true,
			'options'     => array( 'active', 'on-hold', 'cancelled', 'pending-cancel', 'expired' ),
		),
		array(
			'name'        => 'create-parent-order',
			'type'        => 'flag',
			'description' => 'Create a parent order for each generated subscription.',
			'optional'    => true,
		),
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate subscriptions 10\n\nwc generate subscriptions 50 --billing-period=month --billing-interval=1 --status=active --create-parent-order",
) );
