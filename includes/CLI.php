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
			$batch = $remaining_amount > Generator\Product::MAX_BATCH_SIZE ? Generator\Product::MAX_BATCH_SIZE : $remaining_amount;

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

		Generator\Order::disable_emails();

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
			$batch = $remaining_amount > Generator\Order::MAX_BATCH_SIZE ? Generator\Order::MAX_BATCH_SIZE : $remaining_amount;

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

		Generator\Customer::disable_emails();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating customers', $amount );
		for ( $i = 1; $i <= $amount; $i++ ) {
			Generator\Customer::generate();
			$progress->tick();
		}
		$progress->finish();

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $amount . ' customers generated in ' . $display_time );
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

		$min = 5;
		$max = 100;
		if ( ! empty( $assoc_args['min'] ) ) {
			$min = $assoc_args['min'];
		}
		if ( ! empty( $assoc_args['max'] ) ) {
			$max = $assoc_args['max'];
		}

		if ( $amount > 0 ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Generating coupons', $amount );
			for ( $i = 1; $i <= $amount; $i++ ) {
				Generator\Coupon::generate( true, $min, $max );
				$progress->tick();
			}
			$progress->finish();
		}

		$time_end       = microtime( true );
		$execution_time = round( ( $time_end - $time_start ), 2 );
		$display_time   = $execution_time < 60 ? $execution_time . ' seconds' : human_time_diff( $time_start, $time_end );

		WP_CLI::success( $amount . ' coupons generated in ' . $display_time );
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
			$batch = $remaining_amount > Generator\Term::MAX_BATCH_SIZE ? Generator\Term::MAX_BATCH_SIZE : $remaining_amount;

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
}

WP_CLI::add_command( 'wc generate products', array( 'WC\SmoothGenerator\CLI', 'products' ), array(
	'shortdesc' => 'Generate products.',
	'synopsis' => array(
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
	),
	'longdesc' => "## EXAMPLES\n\nwc generate products 10\n\nwc generate products 20 --type=variable",
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
	),
	'longdesc'  => "## EXAMPLES\n\nwc generate customers 10",
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
			'description' => 'Specify the minimum discount of each coupon.',
			'optional'    => true,
			'default'     => 5,
		),
		array(
			'name'        => 'max',
			'type'        => 'assoc',
			'description' => 'Specify the maximum discount of each coupon.',
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
