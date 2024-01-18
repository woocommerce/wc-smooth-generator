<?php
/**
 * Order Attribution data helper.
 *
 * @package SmoothGenerator\Classes
 */

 namespace WC\SmoothGenerator\Generator;
class OrderAttribution {

    /**
     * Generate order attribution data.
     *
     * @param int $order_id Order ID.
     */
    public static function add_order_attribution_meta( $order, $assoc_args = array() ) {

        $device_type = self::get_random_device_type();
        $meta = array(
			'_wc_order_attribution_origin'             => 'Referral: WooCommerce.com',
			'_wc_order_attribution_device_type'        => $device_type,
			'_wc_order_attribution_user_agent'         => self::get_random_user_agent_for_device( $device_type ),
			'_wc_order_attribution_session_count'      => 1,
			'_wc_order_attribution_session_pages'      => 4,
			'_wc_order_attribution_session_start_time' => self::get_random_session_start_time( $order ),
			'_wc_order_attribution_session_entry'      => 'https://wordpress.ddev.site/product/belt/',
			'_wc_order_attribution_utm_content'        => '/',
			'_wc_order_attribution_utm_medium'         => 'referral',
			'_wc_order_attribution_utm_source'         => 'woocommerce.com',
			'_wc_order_attribution_referrer'           => 'https://woocommerce.com/',
			'_wc_order_attribution_source_type'        => 'referral',
		);
		foreach ( $meta as $key => $value ) {
			$order->add_meta_data( $key, $value );
		}
    }

    /**
     * Get random device type based on the following distribution:
     * Mobile:  50%
     * Desktop: 30%
     * Tablet:  10%
     * Unknown: 10%
     *
     */
    public static function get_random_device_type() {
        $randomNumber = rand(1, 100); // Generate a random number between 1 and 100

        if ($randomNumber <= 50) {
            return "Mobile";
        } elseif ($randomNumber <= 80) {
            return "Desktop";
        } elseif ($randomNumber <= 90) {
            return "Tablet";
        } else {
            return "Unknown";
        }
    }

    public static function get_random_user_agent_for_device( $device_type ) {
        switch ( $device_type ) {
            case 'Mobile':
                return self::get_random_mobile_user_agent();
            case 'Tablet':
                return self::get_random_tablet_user_agent();
            case 'Desktop':
                return self::get_random_desktop_user_agent();
            default:
                return "";
        }
    }

    public static function get_random_mobile_user_agent() {
        $user_agents = array(
            "Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/114.0.5735.99 Mobile/15E148 Safari/604.1",
            "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/21.0 Chrome/110.0.5481.154 Mobile Safari/537.36",
        );

        return array_rand( $user_agents );
    }

    public static function get_random_tablet_user_agent() {
        $user_agents = array(
            "Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/114.0.5735.124 Mobile/15E148 Safari/604.1",
            "Mozilla/5.0 (Linux; Android 12; SM-X906C Build/QP1A.190711.020; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/80.0.3987.119 Mobile Safari/537.36",
        );

        return array_rand( $user_agents );
    }

    public static function get_random_desktop_user_agent() {
        $user_agents = array(
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246",
            "Mozilla/5.0 (X11; CrOS x86_64 8172.45.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.64 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/601.3.9 (KHTML, like Gecko) Version/9.0.2 Safari/601.3.9",
        );

        return array_rand( $user_agents );
    }

    public static function get_random_session_start_time( $order ) {
        $order_created_date = $order->get_date_created();
        $session_start_hour = self::get_random_session_start_based_on_order_creation_time( $order_created_date->format('H:i:s') );
        $session_start_time = $order_created_date->format('Y-m-d') . ' ' . $session_start_hour;
        return $session_start_time;
    }

    public static function get_random_session_start_based_on_order_creation_time($endTime) {
        // Convert the end time to seconds
        list($hours, $minutes, $seconds) = explode(':', $endTime);
        $endTimeInSeconds = $hours * 3600 + $minutes * 60 + $seconds;

        // Generate a random time in seconds between midnight and the end time
        $randomTimeInSeconds = rand(0, $endTimeInSeconds);

        // Convert the random time back to HH:MM:SS format
        $hours = floor($randomTimeInSeconds / 3600);
        $minutes = floor(($randomTimeInSeconds / 60) % 60);
        $seconds = $randomTimeInSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

}
