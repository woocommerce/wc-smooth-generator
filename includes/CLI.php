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
	 * ## OPTIONS
	 *
	 * <amount>
	 * : The amount of products to generate
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 * wc generate products 100
	 *
	 * @param array $args Argumens specified.
	 * @param arrat $assoc_args Associative arguments specified.
	 */
	public function products( $args, $assoc_args ) {
		list( $amount ) = $args;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating products', $amount );
		for ( $i = 1; $i <= $amount; $i++ ) {
			Generator\Product::generate();
			$progress->tick();
		}
		$progress->finish();
		WP_CLI::success( $amount . ' products generated.' );
	}

	/**
	 * Generate orders.
	 *
	 * ## OPTIONS
	 *
	 * <amount>
	 * : The amount of orders to generate
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 * wc generate orders 100
	 *
	 * @param array $args Argumens specified.
	 * @param arrat $assoc_args Associative arguments specified.
	 */
	public function orders( $args, $assoc_args ) {
		list( $amount ) = $args;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating orders', $amount );
		for ( $i = 1; $i <= $amount; $i++ ) {
			Generator\Order::generate();
			$progress->tick();
		}
		$progress->finish();
		WP_CLI::success( $amount . ' orders generated.' );
	}

	/**
	 * Generate customers.
	 *
	 * ## OPTIONS
	 *
	 * <amount>
	 * : The amount of customers to generate
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 * wc generate customers 100
	 *
	 * @param array $args Argumens specified.
	 * @param arrat $assoc_args Associative arguments specified.
	 */
	public function customers( $args, $assoc_args ) {
		list( $amount ) = $args;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Generating customers', $amount );
		for ( $i = 1; $i <= $amount; $i++ ) {
			Generator\Customer::generate();
			$progress->tick();
		}
		$progress->finish();
		WP_CLI::success( $amount . ' customers generated.' );
	}
}
WP_CLI::add_command( 'wc generate', 'WC\SmoothGenerator\CLI' );
