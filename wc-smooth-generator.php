<?php
/**
 * Plugin Name: WooCommerce Smooth Generator
 * Plugin URI: https://woocommerce.com/
 * Description: A smooth customer, order and product generator for WooCommerce.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://woocommerce.com
 *
 * @package WooCommerce
 */

defined( 'ABSPATH' ) || exit;

// autoloader.

// Checks for vendor directory in the following order:
// 1. COMPOSER_VENDOR_DIR.
// 2. WP_CONTENT_DIR.
// 3. Two directories up, assuming this gets installed in wp-content/plugins vendor may be in the project root.
// 4. One directory up, assuming this gets installed in wp-content/plugins vendor may be in wp-content.
// 5. Defaults to the current directory.
if ( getenv( 'COMPOSER_VENDOR_DIR' ) && is_file( getenv( 'COMPOSER_VENDOR_DIR' ) . '/autoload.php' ) ) {
	require getenv( 'COMPOSER_VENDOR_DIR' ) . '/autoload.php';
} elseif ( defined( 'WP_CONTENT_DIR' ) && is_file( WP_CONTENT_DIR . '/vendor/autoload.php' ) ) {
	require WP_CONTENT_DIR . '/vendor/autoload.php';
} elseif ( is_file( __DIR__ . '/../../vendor/autoload.php' ) ) {
	require __DIR__ . '/../../vendor/autoload.php';
} elseif ( is_file( __DIR__ . '/../vendor/autoload.php' ) ) {
	require __DIR__ . '/../vendor/autoload.php';
} else {
	require __DIR__ . '/vendor/autoload.php';
}

/**
 * Fetch instance of plugin.
 *
 * @return \WC\SmoothGenerator\Plugin
 */
function wc_smooth_generator() {
	static $instance;

	if ( is_null( $instance ) ) {
		$instance = new \WC\SmoothGenerator\Plugin( __FILE__ );
	}

	return $instance;
}

/**
 * Init plugin when WordPress loads.
 */
function load_wc_smooth_generator() {
	wc_smooth_generator();
}

if ( version_compare( PHP_VERSION, '5.3', '>' ) ) {
	add_action( 'plugins_loaded', 'load_wc_smooth_generator', 20 );
}
