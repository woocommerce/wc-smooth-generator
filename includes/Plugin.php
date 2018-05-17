<?php
/**
 * Main plugin class.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin __FILE__ reference.
	 */
	public function __construct( $file ) {
		if ( is_admin() ) {
			Admin\Settings::init();
		}

		if ( class_exists( 'WP_CLI' ) ) {
			$cli = new CLI();
		}
	}
}
