<?php
/**
 * Isolated, explicitly opted-in integration environment.
 *
 * @package SmoothGenerator\Tests
 */

$root = getenv( 'SMOOTH_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/wp-config.php' ) || false === strpos( file_get_contents( $root . '/wp-config.php' ), "define( 'SMOOTH_LIFECYCLE_TEST_ENV', true );" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local opt-in config before loading WordPress.
	throw new RuntimeException( 'Use only a newly provisioned disposable site with SMOOTH_LIFECYCLE_TEST_ENV=true.' );
}
require $root . '/wp-load.php';
if ( ! defined( 'SMOOTH_LIFECYCLE_TEST_ENV' ) || ! SMOOTH_LIFECYCLE_TEST_ENV || 0 !== strpos( DB_NAME, 'smooth_lifecycle_' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'Disposable test database and blocked external HTTP are required.' );
}
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'pre_http_request', function () {
	return new WP_Error( 'test_network_blocked' );
} );
WC\SmoothGenerator\Generator\Generator::disable_emails();

if ( getenv( 'SMOOTH_TEST_STORAGE' ) ) {
	if ( ! in_array( getenv( 'SMOOTH_TEST_STORAGE' ), array( 'posts', 'hpos' ), true ) || ! in_array( getenv( 'SMOOTH_TEST_SYNC' ), array( 'yes', 'no' ), true ) ) {
		throw new RuntimeException( 'Explicit posts/hpos and yes/no sync test modes required.' );
	}
	update_option( 'woocommerce_custom_orders_table_data_sync_enabled', getenv( 'SMOOTH_TEST_SYNC' ) );
	update_option( 'woocommerce_custom_orders_table_enabled', 'hpos' === getenv( 'SMOOTH_TEST_STORAGE' ) ? 'yes' : 'no' );
	update_option( 'woocommerce_hpos_datastore_caching_enabled', 'yes' );
	if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() !== ( 'hpos' === getenv( 'SMOOTH_TEST_STORAGE' ) ) ) {
		throw new RuntimeException( 'Requested storage authority was not enabled.' );
	}
}
