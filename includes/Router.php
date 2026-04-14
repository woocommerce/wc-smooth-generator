<?php

namespace WC\SmoothGenerator;

/**
 * Methods to retrieve and use a particular generator class based on its slug.
 */
class Router {
	/**
	 * @const array Associative array of available generator classes.
	 */
	const GENERATORS = array(
		'analytics-sync' => Generator\OrderAnalyticsSync::class,
		'coupons'        => Generator\Coupon::class,
		'customers'      => Generator\Customer::class,
		'orders'         => Generator\Order::class,
		'products'       => Generator\Product::class,
		'terms'          => Generator\Term::class,
	);

	/**
	 * Get the classname of a generator via slug.
	 *
	 * @param string $generator_slug The slug of the generator to retrieve.
	 *
	 * @return string|\WP_Error
	 */
	public static function get_generator_class( string $generator_slug ) {
		if ( ! isset( self::GENERATORS[ $generator_slug ] ) ) {
			return new \WP_Error(
				'smoothgenerator_invalid_generator',
				sprintf(
					'A generator class for "%s" can\'t be found.',
					$generator_slug
				)
			);
		}

		return self::GENERATORS[ $generator_slug ];
	}

	/**
	 * Generate a batch of objects using the specified generator.
	 *
	 * @param string $generator_slug The slug identifier of the generator to use.
	 * @param int    $amount         The number of objects to generate.
	 * @param array  $args           Additional args for object generation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function generate_batch( string $generator_slug, int $amount, array $args = array() ) {
		$generator = self::get_generator_class( $generator_slug );

		if ( is_wp_error( $generator ) ) {
			return $generator;
		}

		return $generator::batch( $amount, $args );
	}
}
