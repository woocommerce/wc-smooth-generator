<?php
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
		$admin = new WC\SmoothGenerator\Admin\Settings();
		$admin->init();
	}
}
