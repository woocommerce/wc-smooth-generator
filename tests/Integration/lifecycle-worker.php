<?php
/**
 * Independent worker for lifecycle concurrency tests.
 *
 * @package SmoothGenerator\Tests
 */

// Validate and load the explicitly opted-in site before starting the worker.
require __DIR__ . '/lifecycle-bootstrap.php';
if ( ! empty( $argv[3] ) ) {
	$barrier = $argv[3];
	add_filter( 'query', function ( $query ) use ( $barrier ) {
		if ( 0 === strpos( $query, 'INSERT IGNORE INTO' ) && false !== strpos( $query, '_smooth_lifecycle_v1_' ) ) {
			touch( $barrier . '/' . getmypid() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- Each PHP worker creates its own barrier sentinel.
			$deadline = microtime( true ) + 15;
			$arrivals = count( glob( $barrier . '/*' ) );
			while ( $arrivals < 2 ) {
				if ( microtime( true ) > $deadline ) {
					throw new RuntimeException( 'Claim barrier timed out.' );
				}
				usleep( 10000 );
				$arrivals = count( glob( $barrier . '/*' ) );
			}
		}
		return $query;
	} );
}
$result = WC\SmoothGenerator\Generator\OrderLifecycle::apply( (int) $argv[1], 'completed', (int) $argv[2] );
echo wp_json_encode( is_wp_error( $result ) ? array( 'error' => $result->get_error_code() ) : $result );
