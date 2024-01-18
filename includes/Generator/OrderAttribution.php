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

        $meta = array(
			'_wc_order_attribution_origin'             => 'Referral: WooCommerce.com',
			'_wc_order_attribution_device_type'        => 'Desktop',
			'_wc_order_attribution_user_agent'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
			'_wc_order_attribution_session_count'      => 1,
			'_wc_order_attribution_session_pages'      => 4,
			'_wc_order_attribution_session_start_time' => '2023-11-16 13:47:50',
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
}
