<?php
/**
 * Extend WP_Background_Process to handle data generation & creation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator;

defined( 'ABSPATH' ) || exit;

use WP_Background_Process;

/**
 * Background data generation and creation class.
 */
class GenerateBackgroundProcess extends WP_Background_Process {

	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'wc_smoothgenerator_generate';
		parent::__construct();
	}

	/**
	 * Code to execute for each item in the queue
	 *
	 * @param array $item Item data.
	 * @return boolean
	 */
	protected function task( $item ) {
		if ( ! is_array( $item ) && ! isset( $item['task'] ) ) {
			return false;
		}

		// Check what generation task to perform.
		switch ( $item['task'] ) {
			case 'order':
				Generator\Order::generate();
				break;
			case 'product':
				Generator\Product::generate();
				break;
			case 'customer':
				Generator\Customer::generate();
				break;
			default:
				return false;
		}

		return false;
	}
}
