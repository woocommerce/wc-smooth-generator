<?php
/**
 * CLI class
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
	 * default: 50
	 * ---
	 *
	 * ## EXAMPLES
	 * wc generate products 50
	 */
	public function products( $args, $assoc_args ) {
		list( $amount ) = $args;
	}

	/**
	 * Generate orders.
	 *
	 * ## OPTIONS
	 *
	 * <amount>
	 * : The amount of orders to generate
	 * ---
	 * default: 10
	 * ---
	 *
	 * ## EXAMPLES
	 * wc generate orders 10
	 */
	public function orders( $args, $assoc_args ) {
		list( $amount ) = $args;
	}
}
WP_CLI::add_command( 'wc generate', 'WC\SmoothGenerator\CLI' );
