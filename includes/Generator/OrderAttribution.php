<?php
/**
 * Order Attribution data helper.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Order Attribution data helper class.
 */
class OrderAttribution {

	/**
	 * Generate order attribution data.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $assoc_args Arguments passed via the CLI for additional customization.
	 */
	public static function add_order_attribution_meta( $order, $assoc_args = array() ) {

		if ( isset( $assoc_args['skip-order-attribution'] ) ) {
			return;
		}

		$device_type    = self::get_random_device_type();
		$source         = 'woo.com';
		$source_type    = self::get_source_type();
		$origin         = self::get_origin( $source_type, $source );
		$order_products = $order->get_items();
		$product_url    = get_permalink( $order_products[ array_rand( $order_products ) ]->get_id() );
		$utm_content    = [ '/', 'campaign_a', 'campaign_b' ];
		$utm_content    = $utm_content[ array_rand( $utm_content ) ];

		$meta = array();

		// For these source types, we only need to set the source type.
		if ( in_array( $source_type, [ 'admin', 'mobile_app', 'unknown' ], true ) ) {
			$meta = array(
				'_wc_order_attribution_source_type' => $source_type,
			);
		} else {
			$meta = array(
				'_wc_order_attribution_origin'             => $origin,
				'_wc_order_attribution_device_type'        => $device_type,
				'_wc_order_attribution_user_agent'         => self::get_random_user_agent_for_device( $device_type ),
				'_wc_order_attribution_session_count'      => wp_rand( 1, 10 ),
				'_wc_order_attribution_session_pages'      => wp_rand( 1, 10 ),
				'_wc_order_attribution_session_start_time' => self::get_random_session_start_time( $order ),
				'_wc_order_attribution_session_entry'      => $product_url,
				'_wc_order_attribution_utm_content'        => $utm_content,
				'_wc_order_attribution_utm_source'         => self::get_source( $source_type ),
				'_wc_order_attribution_referrer'           => self::get_referrer( $source_type ),
				'_wc_order_attribution_source_type'        => $source_type,
			);
		}

		// If the source type is not typein ( Direct ), set a random utm medium.
		if ( ! in_array( $source_type, [ 'typein', 'admin', 'mobile_app', 'unknown' ], true ) ) {
			$meta['_wc_order_attribution_utm_medium'] = self::get_random_utm_medium();
		}

		foreach ( $meta as $key => $value ) {
			$order->add_meta_data( $key, $value );
		}
	}

	/**
	 * Get a random referrer based on the source type.
	 *
	 * @param string $source_type The source type.
	 * @return string The referrer.
	 */
	public static function get_referrer( string $source_type ) {
		// Set up the label based on the source type.
		switch ( $source_type ) {
			case 'utm':
				$utm = array(
					'https://woo.com/',
					'https://twitter.com',
				);
				return $utm[ array_rand( $utm ) ];
			case 'organic':
				$organic = array(
					'https://google.com',
					'https://bing.com',
				);
				return $organic[ array_rand( $organic ) ];
			case 'referral':
				$refferal = array(
					'https://woo.com/',
					'https://facebook.com',
					'https://twitter.com',
				);
				return $refferal[ array_rand( $refferal ) ];
			case 'typein':
				return '';
			case 'admin':
				return '';
			case 'mobile_app':
				return '';
			default:
				return '';
		}
	}

	/**
	 * Get a random utm medium.
	 *
	 * @return string The utm medium.
	 */
	public static function get_random_utm_medium() {
		$utm_mediums = array(
			'referral',
			'cpc',
			'email',
			'social',
			'organic',
			'unknown',
		);

		return $utm_mediums[ array_rand( $utm_mediums ) ];
	}

	/**
	 * Get the origin.
	 *
	 * @param string $source_type The source type.
	 * @param string $source The source.
	 *
	 * @return string The origin.
	 */
	public static function get_origin( string $source_type, string $source ) {
		// Set up the label based on the source type.
		switch ( $source_type ) {
			case 'utm':
				return 'Source: ' . $source;
			case 'organic':
				return 'Organic: ' . $source;
			case 'referral':
				return 'Referral: ' . $source;
			case 'typein':
				return 'Direct';
			case 'admin':
				return 'Web admin';
			case 'mobile_app':
				return 'Mobile app';
			default:
				return 'Unknown';
		}
	}

	/**
	 * Get random source type.
	 *
	 * @return string The source type.
	 */
	public static function get_source_type() {
		$source_types = array(
			'typein',
			'organic',
			'referral',
			'utm',
			'admin',
			'mobile_app',
			'unknown',
		);

		return $source_types[ array_rand( $source_types ) ];
	}

	/**
	 * Get random source based on the source type.
	 *
	 * @param string $source_type The source type.
	 * @return string The source.
	 */
	public static function get_source( $source_type ) {
		switch ( $source_type ) {
			case 'typein':
				return '(direct)';
			case 'organic':
				$organic = array(
					'google',
					'bing',
					'yahoo',
				);
				return $organic[ array_rand( $organic ) ];
			case 'referral':
				$refferal = array(
					'woo.com',
					'facebook.com',
					'twitter.com',
				);
				return $refferal[ array_rand( $refferal ) ];
			case 'social':
				$social = array(
					'facebook.com',
					'twitter.com',
					'instagram.com',
					'pinterest.com',
				);
				return $social[ array_rand( $social ) ];
			case 'utm':
				$utm = array(
					'mailchimp',
					'google',
					'newsletter',
				);
				return $utm[ array_rand( $utm ) ];
			default:
				return 'Unknown';
		}
	}

	/**
	 * Get random device type based on the following distribution:
	 * Mobile:  50%
	 * Desktop: 30%
	 * Tablet:  10%
	 * Unknown: 10%
	 */
	public static function get_random_device_type() {
		$randomNumber = wp_rand( 1, 100 ); // Generate a random number between 1 and 100.

		if ( $randomNumber <= 50 ) {
			return 'Mobile';
		}

		if ( $randomNumber <= 80 ) {
			return 'Desktop';
		}

		if ( $randomNumber <= 90 ) {
			return 'Tablet';
		}

		return 'Unknown';
	}

	/**
	 * Get a random user agent based on the device type.
	 *
	 * @param string $device_type The device type.
	 * @return string The user agent.
	 */
	public static function get_random_user_agent_for_device( $device_type ) {
		switch ( $device_type ) {
			case 'Mobile':
				return self::get_random_mobile_user_agent();
			case 'Tablet':
				return self::get_random_tablet_user_agent();
			case 'Desktop':
				return self::get_random_desktop_user_agent();
			default:
				return '';
		}
	}

	/**
	 * Get a random mobile user agent.
	 *
	 * @return string The user agent.
	 */
	public static function get_random_mobile_user_agent() {
		$user_agents = array(
			'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
			'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/114.0.5735.99 Mobile/15E148 Safari/604.1',
			'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36',
			'Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/21.0 Chrome/110.0.5481.154 Mobile Safari/537.36',
		);

		return $user_agents[ array_rand( $user_agents ) ];
	}

	/**
	 * Get a random tablet user agent.
	 *
	 * @return string The user agent.
	 */
	public static function get_random_tablet_user_agent() {
		$user_agents = array(
			'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/114.0.5735.124 Mobile/15E148 Safari/604.1',
			'Mozilla/5.0 (Linux; Android 12; SM-X906C Build/QP1A.190711.020; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/80.0.3987.119 Mobile Safari/537.36',
		);

		return $user_agents[ array_rand( $user_agents ) ];
	}

	/**
	 * Get a random desktop user agent.
	 *
	 * @return string The user agent.
	 */
	public static function get_random_desktop_user_agent() {
		$user_agents = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246',
			'Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9',
		);

		return $user_agents[ array_rand( $user_agents ) ];
	}

	/**
	 * Get a random session start time based on the order creation time.
	 *
	 * @param \WC_Order $order The order.
	 * @return string The session start time.
	 */
	public static function get_random_session_start_time( $order ) {

		// Clone the order creation date so we don't modify the original.
		$order_created_date = clone $order->get_date_created();

		// Random DateTimeInterval between 10 minutes and 6 hours.
		$random_interval    = new \DateInterval( 'PT' . (string) wp_rand( 10, 360 ) . 'M' );

		// Subtract the random interval from the order creation date.
		$order_created_date->sub( $random_interval );

		return $order_created_date->format( 'Y-m-d H:i:s' );
	}

}
