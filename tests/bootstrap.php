<?php
/**
 * Bootstrap file for PHPUnit tests.
 *
 * @package WC\SmoothGenerator\Tests
 */

declare( strict_types=1 );

namespace WC\SmoothGenerator\Tests;

use WC_Install;

define( 'PLUGIN_TESTS_DIR', __DIR__ );

global $plugin_dir;
global $wp_plugins_dir;
global $wc_dir;

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress-tests-lib' );
validate_file_exists( "{$wp_tests_dir}/includes/functions.php" );

$wp_core_dir    = getenv( 'WP_CORE_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress' );
$wp_plugins_dir = path_join( $wp_core_dir, '/wp-content/plugins' );

$plugin_dir = dirname( __DIR__ );

$wc_dir = getenv( 'WC_DIR' );
if ( ! $wc_dir ) {
	// Check if WooCommerce exists in the core plugin folder.
	$wc_dir = path_join( $wp_plugins_dir, '/woocommerce' );
	if ( ! file_exists( "{$wc_dir}/woocommerce.php" ) ) {
		// Check if WooCommerce exists in parent directory of the plugin.
		$wc_dir = path_join( dirname( $plugin_dir ), '/woocommerce' );
	}
}
validate_file_exists( "{$wc_dir}/woocommerce.php" );

// Require the composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once "{$wp_tests_dir}/includes/functions.php";

tests_add_filter(
	'muplugins_loaded',
	function () {
		load_plugins();
	}
);

tests_add_filter(
	'init',
	function () {
		install_woocommerce();
	},
	0
);

// Start up the WP testing environment.
require "{$wp_tests_dir}/includes/bootstrap.php";

// Start up the WC testing environment.
$wc_tests_dir = $wc_dir . '/tests';
if ( file_exists( $wc_dir . '/tests/legacy/bootstrap.php' ) ) {
	$wc_tests_dir .= '/legacy';
}

// Load WooCommerce test helpers.
$wc_helper_files = array(
	'/framework/helpers/class-wc-helper-product.php',
	'/framework/helpers/class-wc-helper-shipping.php',
	'/framework/helpers/class-wc-helper-customer.php',
	'/framework/helpers/class-wc-helper-order.php',
	'/framework/helpers/class-wc-helper-coupon.php',
);

foreach ( $wc_helper_files as $helper_file ) {
	if ( file_exists( $wc_tests_dir . $helper_file ) ) {
		require_once $wc_tests_dir . $helper_file;
	}
}

/**
 * Load WooCommerce for testing.
 *
 * @global $wc_dir
 */
function install_woocommerce() {
	global $wc_dir;

	define( 'WP_UNINSTALL_PLUGIN', true );
	define( 'WC_REMOVE_ALL_DATA', true );

	include $wc_dir . '/uninstall.php';

	WC_Install::install();

	// Initialize the WC Admin extension if available.
	if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Install' ) ) {
		\Automattic\WooCommerce\Internal\Admin\Install::create_tables();
		\Automattic\WooCommerce\Internal\Admin\Install::create_events();
	} elseif ( class_exists( '\Automattic\WooCommerce\Admin\Install' ) ) {
		\Automattic\WooCommerce\Admin\Install::create_tables();
		\Automattic\WooCommerce\Admin\Install::create_events();
	}

	// Reload capabilities after install.
	$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	wp_roles();

	echo 'Installing WooCommerce...' . PHP_EOL;
}

/**
 * Manually load plugins.
 *
 * @global $plugin_dir
 * @global $wc_dir
 */
function load_plugins() {
	global $plugin_dir;
	global $wc_dir;

	require_once $wc_dir . '/woocommerce.php';
	update_option( 'woocommerce_db_version', WC()->version );

	require $plugin_dir . '/wc-smooth-generator.php';
}

/**
 * Checks whether a file exists and throws an error if it doesn't.
 *
 * @param string $file_name The file path to check.
 */
function validate_file_exists( string $file_name ) {
	if ( ! file_exists( $file_name ) ) {
		echo "Could not find {$file_name}, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}
}

/**
 * Join two paths together.
 *
 * @param string $base The base path.
 * @param string $path The path to append.
 *
 * @return string
 */
function path_join( string $base, string $path ) {
	return rtrim( $base, '/\\' ) . '/' . ltrim( $path, '/\\' );
}
