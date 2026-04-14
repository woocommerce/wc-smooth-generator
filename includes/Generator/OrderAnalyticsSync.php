<?php
/**
 * Analytics sync for orders not yet reflected in WooCommerce Analytics tables.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Populates WooCommerce Analytics lookup tables for HPOS orders that are not
 * yet reflected in the analytics tables — whether they were bulk-inserted via
 * this plugin or created via the normal WooCommerce ORM without a sync step.
 *
 * Implements the same static batch() interface as the Generator classes so it
 * integrates with BatchProcessor / Router for the background-job UI path.
 *
 * Tables populated:
 *   wc_customer_lookup       (INSERT IGNORE — preserves existing customer_id)
 *   wc_order_stats           (INSERT IGNORE or REPLACE INTO for --all re-sync)
 *   wc_order_product_lookup  (INSERT IGNORE or REPLACE INTO for --all re-sync)
 *   wc_order_coupon_lookup   (INSERT IGNORE or REPLACE INTO, only for coupon items)
 *   wc_order_tax_lookup      (INSERT IGNORE or REPLACE INTO, only for tax items)
 *
 * Tables not handled here (require per-order WC_Order loading):
 *   returning_customer in wc_order_stats  (cross-order analysis, left NULL)
 */
class OrderAnalyticsSync {

	/**
	 * Orders synced per batch call.
	 *
	 * @var int
	 */
	const MAX_BATCH_SIZE = 500;

	/**
	 * wp_options key used to persist the order-ID cursor for --all re-sync jobs.
	 *
	 * @var string
	 */
	const CURSOR_OPTION = 'smoothgenerator_analytics_sync_cursor';

	// -------------------------------------------------------------------------
	// Status helpers (used by the Settings UI)
	// -------------------------------------------------------------------------

	/**
	 * Count HPOS shop_orders that are not yet in wc_order_stats.
	 *
	 * @return int
	 */
	public static function get_unsynced_count(): int {
		global $wpdb;

		if ( ! self::is_hpos_available() ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->prefix}wc_orders o
			 LEFT JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = o.id
			 WHERE o.type = 'shop_order'
			   AND s.order_id IS NULL"
		);
	}

	/**
	 * Count all HPOS shop_orders.
	 *
	 * @return int
	 */
	public static function get_total_order_count(): int {
		global $wpdb;

		if ( ! self::is_hpos_available() ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders WHERE type = 'shop_order'"
		);
	}

	// -------------------------------------------------------------------------
	// Batch interface (called by BatchProcessor via Router)
	// -------------------------------------------------------------------------

	/**
	 * Sync a batch of orders into the analytics tables.
	 *
	 * Called by BatchProcessor with the same interface as Generator::batch().
	 *
	 * @param int   $amount     Maximum number of orders to process this call.
	 * @param array $args       Optional args:
	 *                            'all' => true — cursor-based re-sync of every order
	 *                                            (uses REPLACE INTO; default is INSERT IGNORE
	 *                                             which only syncs unsynced orders).
	 * @return int[] Order IDs that were synced.
	 */
	public static function batch( int $amount, array $args = array() ): array {
		global $wpdb;

		$amount = min( $amount, self::MAX_BATCH_SIZE );
		$all    = ! empty( $args['all'] );

		if ( $all ) {
			// Cursor-based scan so every order is processed exactly once.
			$cursor    = (int) get_option( self::CURSOR_OPTION, 0 );
			$order_ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}wc_orders
						 WHERE type = 'shop_order' AND id > %d
						 ORDER BY id ASC LIMIT %d",
						$cursor,
						$amount
					)
				)
			);

			if ( ! empty( $order_ids ) ) {
				update_option( self::CURSOR_OPTION, max( $order_ids ), false );
			}
		} else {
			// Only pick orders that do not yet have a wc_order_stats row.
			// The LEFT JOIN is self-correcting: each batch consumes the oldest
			// unsynced orders and the next call automatically finds the next ones.
			$order_ids = array_map(
				'intval',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT o.id
						 FROM {$wpdb->prefix}wc_orders o
						 LEFT JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = o.id
						 WHERE o.type = 'shop_order' AND s.order_id IS NULL
						 ORDER BY o.id ASC LIMIT %d",
						$amount
					)
				)
			);
		}

		if ( ! empty( $order_ids ) ) {
			self::sync_order_ids( $order_ids, $all );
		}

		return $order_ids;
	}

	// -------------------------------------------------------------------------
	// Core sync logic
	// -------------------------------------------------------------------------

	/**
	 * Write analytics rows for a given list of order IDs.
	 *
	 * Uses five INSERT…SELECT statements to populate all five analytics tables
	 * from the raw HPOS and order-item tables, without loading WC_Order objects.
	 * Coupon/tax lookup queries produce zero rows for orders that have no
	 * coupon or tax line items (e.g. bulk-inserted orders).
	 *
	 * @param int[] $order_ids Order IDs to sync.
	 * @param bool  $replace   When true, use REPLACE INTO for wc_order_stats,
	 *                         wc_order_product_lookup, wc_order_coupon_lookup, and
	 *                         wc_order_tax_lookup so that existing rows are updated
	 *                         (used for the re-sync-all path). When false (default),
	 *                         uses INSERT IGNORE to only add missing rows.
	 *                         wc_customer_lookup always uses INSERT IGNORE to avoid
	 *                         changing AUTO_INCREMENT customer_id values.
	 * @return void
	 */
	public static function sync_order_ids( array $order_ids, bool $replace = false ): void {
		global $wpdb;

		if ( empty( $order_ids ) ) {
			return;
		}

		$in     = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$insert = $replace ? 'REPLACE INTO' : 'INSERT IGNORE INTO';

		// ------------------------------------------------------------------
		// 1. wc_customer_lookup
		//    Always INSERT IGNORE — replacing would generate a new customer_id
		//    (AUTO_INCREMENT) and orphan any existing wc_order_stats rows.
		// ------------------------------------------------------------------
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"INSERT IGNORE INTO {$wpdb->prefix}wc_customer_lookup
				     (user_id, username, first_name, last_name, email,
				      date_last_active, date_registered, country, postcode, city, state)
				 SELECT
				     o.customer_id,
				     MIN( COALESCE( u.user_login, '' ) ),
				     MIN( COALESCE( a.first_name, '' ) ),
				     MIN( COALESCE( a.last_name, '' ) ),
				     MIN( a.email ),
				     MAX( o.date_created_gmt ),
				     MIN( u.user_registered ),
				     MIN( COALESCE( a.country, '' ) ),
				     MIN( COALESCE( a.postcode, '' ) ),
				     MIN( COALESCE( a.city, '' ) ),
				     MIN( COALESCE( a.state, '' ) )
				 FROM {$wpdb->prefix}wc_orders o
				 LEFT JOIN {$wpdb->users} u ON u.ID = o.customer_id
				 LEFT JOIN {$wpdb->prefix}wc_order_addresses a
				        ON a.order_id = o.id AND a.address_type = 'billing'
				 WHERE o.id IN ( $in )
				 GROUP BY o.customer_id",
				...$order_ids
			)
		);

		// ------------------------------------------------------------------
		// 2. wc_order_stats
		//    shipping_total is read from wc_order_operational_data (NULL for
		//    bulk-inserted orders that have no shipping, treated as 0).
		//    returning_customer is left NULL — computing it correctly requires
		//    cross-order analysis that would negate bulk-sync throughput.
		// ------------------------------------------------------------------
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"$insert {$wpdb->prefix}wc_order_stats
				     (order_id, parent_id, date_created, date_created_gmt,
				      date_paid, date_completed, num_items_sold,
				      total_sales, tax_total, shipping_total, net_total,
				      returning_customer, status, customer_id)
				 SELECT
				     o.id,
				     COALESCE( o.parent_order_id, 0 ),
				     o.date_created_gmt,
				     o.date_created_gmt,
				     od.date_paid_gmt,
				     od.date_completed_gmt,
				     COALESCE( ic.num_items, 0 ),
				     o.total_amount,
				     o.tax_amount,
				     COALESCE( od.shipping_total, 0 ),
				     o.total_amount - o.tax_amount - COALESCE( od.shipping_total, 0 ),
				     NULL,
				     IF( o.status LIKE 'wc-%%', SUBSTR( o.status, 4 ), o.status ),
				     COALESCE( cl.customer_id, 0 )
				 FROM {$wpdb->prefix}wc_orders o
				 LEFT JOIN {$wpdb->prefix}wc_order_operational_data od ON od.order_id = o.id
				 LEFT JOIN {$wpdb->prefix}wc_customer_lookup cl ON cl.user_id = o.customer_id
				 LEFT JOIN (
				     SELECT oi.order_id,
				            SUM( CAST( oim.meta_value AS UNSIGNED ) ) AS num_items
				     FROM {$wpdb->prefix}woocommerce_order_items oi
				     JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
				          ON oim.order_item_id = oi.order_item_id AND oim.meta_key = '_qty'
				     WHERE oi.order_item_type = 'line_item'
				       AND oi.order_id IN ( $in )
				     GROUP BY oi.order_id
				 ) ic ON ic.order_id = o.id
				 WHERE o.id IN ( $in )",
				...$order_ids,
				...$order_ids
			)
		);

		// ------------------------------------------------------------------
		// 3. wc_order_product_lookup — one row per line item.
		// ------------------------------------------------------------------
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"$insert {$wpdb->prefix}wc_order_product_lookup
				     (order_item_id, order_id, product_id, variation_id, customer_id,
				      date_created, product_qty, product_net_revenue,
				      product_gross_revenue, coupon_amount, tax_amount,
				      shipping_amount, shipping_tax_amount)
				 SELECT
				     oi.order_item_id,
				     oi.order_id,
				     COALESCE( CAST( pid.meta_value AS UNSIGNED ), 0 ),
				     COALESCE( CAST( vid.meta_value AS UNSIGNED ), 0 ),
				     COALESCE( cl.customer_id, 0 ),
				     o.date_created_gmt,
				     COALESCE( CAST( qty.meta_value AS UNSIGNED ), 0 ),
				     COALESCE( CAST( tot.meta_value AS DECIMAL(20,6) ), 0 ),
				     COALESCE( CAST( tot.meta_value AS DECIMAL(20,6) ), 0 )
				         + COALESCE( CAST( tax.meta_value AS DECIMAL(20,6) ), 0 ),
				     0,
				     COALESCE( CAST( tax.meta_value AS DECIMAL(20,6) ), 0 ),
				     0,
				     0
				 FROM {$wpdb->prefix}woocommerce_order_items oi
				 JOIN {$wpdb->prefix}wc_orders o ON o.id = oi.order_id
				 LEFT JOIN {$wpdb->prefix}wc_customer_lookup cl ON cl.user_id = o.customer_id
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta pid
				        ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta vid
				        ON vid.order_item_id = oi.order_item_id AND vid.meta_key = '_variation_id'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty
				        ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_qty'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta tot
				        ON tot.order_item_id = oi.order_item_id AND tot.meta_key = '_line_total'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta tax
				        ON tax.order_item_id = oi.order_item_id AND tax.meta_key = '_line_tax'
				 WHERE oi.order_item_type = 'line_item'
				   AND oi.order_id IN ( $in )",
				...$order_ids
			)
		);

		// ------------------------------------------------------------------
		// 4. wc_order_coupon_lookup — only produces rows for orders that have
		//    coupon line items (bulk-inserted orders do not; ORM orders may).
		// ------------------------------------------------------------------
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"$insert {$wpdb->prefix}wc_order_coupon_lookup
				     (order_id, coupon_id, date_created, discount_amount)
				 SELECT
				     oi.order_id,
				     COALESCE( CAST( cp_id.meta_value AS UNSIGNED ), 0 ),
				     o.date_created_gmt,
				     COALESCE( CAST( cp_disc.meta_value AS DECIMAL(20,6) ), 0 )
				 FROM {$wpdb->prefix}woocommerce_order_items oi
				 JOIN {$wpdb->prefix}wc_orders o ON o.id = oi.order_id
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta cp_id
				        ON cp_id.order_item_id = oi.order_item_id AND cp_id.meta_key = 'coupon_id'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta cp_disc
				        ON cp_disc.order_item_id = oi.order_item_id AND cp_disc.meta_key = 'discount_amount'
				 WHERE oi.order_item_type = 'coupon'
				   AND oi.order_id IN ( $in )",
				...$order_ids
			)
		);

		// ------------------------------------------------------------------
		// 5. wc_order_tax_lookup — only produces rows for orders that have
		//    tax line items (bulk-inserted orders do not; ORM orders may).
		// ------------------------------------------------------------------
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"$insert {$wpdb->prefix}wc_order_tax_lookup
				     (order_id, tax_rate_id, date_created, shipping_tax, order_tax, total_tax)
				 SELECT
				     oi.order_id,
				     COALESCE( CAST( rate_id.meta_value AS UNSIGNED ), 0 ),
				     o.date_created_gmt,
				     COALESCE( CAST( ship_tax.meta_value AS DECIMAL(20,6) ), 0 ),
				     COALESCE( CAST( ord_tax.meta_value AS DECIMAL(20,6) ), 0 ),
				     COALESCE( CAST( ship_tax.meta_value AS DECIMAL(20,6) ), 0 )
				         + COALESCE( CAST( ord_tax.meta_value AS DECIMAL(20,6) ), 0 )
				 FROM {$wpdb->prefix}woocommerce_order_items oi
				 JOIN {$wpdb->prefix}wc_orders o ON o.id = oi.order_id
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta rate_id
				        ON rate_id.order_item_id = oi.order_item_id AND rate_id.meta_key = 'rate_id'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta ship_tax
				        ON ship_tax.order_item_id = oi.order_item_id AND ship_tax.meta_key = 'shipping_tax_amount'
				 LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta ord_tax
				        ON ord_tax.order_item_id = oi.order_item_id AND ord_tax.meta_key = 'tax_amount'
				 WHERE oi.order_item_type = 'tax'
				   AND oi.order_id IN ( $in )",
				...$order_ids
			)
		);
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Return true when WooCommerce HPOS is active.
	 *
	 * @return bool
	 */
	public static function is_hpos_available(): bool {
		return class_exists( OrderUtil::class )
			&& OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
