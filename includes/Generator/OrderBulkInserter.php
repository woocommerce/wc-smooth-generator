<?php
/**
 * Bulk order insertion via raw SQL for HPOS-enabled stores.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

use Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Generates orders by writing directly into HPOS tables, bypassing the
 * WC_Order ORM entirely for maximum throughput.
 *
 * What this path skips vs the ORM path:
 * - Tax calculation  (all tax amounts stored as zero)
 * - Coupon application
 * - Refund creation
 * - WooCommerce Analytics lookup tables (wc_order_stats etc.) — run
 *   `wp wc analytics sync` afterwards if you need analytics to reflect the data
 * - Billing/shipping addresses are freshly generated fake data rather than
 *   being loaded from existing WC_Customer records
 *
 * What it includes:
 * - All six HPOS tables (wp_posts, wc_orders, wc_order_addresses,
 *   wc_order_operational_data, wc_orders_meta, woocommerce_order_items +
 *   woocommerce_order_itemmeta)
 * - Full order attribution meta (same data as the ORM path)
 * - Realistic billing + shipping addresses (Faker-generated)
 * - Weighted random order statuses (or a fixed status via --status)
 * - Date ranges via --date-start / --date-end
 */
class OrderBulkInserter {

	/**
	 * Orders inserted per DB round-trip. Large enough for efficiency,
	 * small enough to avoid hitting PHP memory or MySQL max_allowed_packet limits.
	 */
	const SUB_BATCH_SIZE = 500;

	/**
	 * Faker instance shared across the full generation run.
	 *
	 * @var \Faker\Generator|null
	 */
	private static $faker = null;

	/**
	 * Pre-fetched product pool (stdObjects with id, name, price).
	 *
	 * @var object[]|null
	 */
	private static $product_pool = null;

	/**
	 * Pre-fetched user IDs for customer association.
	 *
	 * @var int[]|null
	 */
	private static $user_ids = null;

	/**
	 * Post type written to wp_posts. Determined by the data-sync setting:
	 * 'shop_order' when sync is on, DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE when off.
	 *
	 * @var string|null
	 */
	private static $post_type = null;

	/**
	 * Monotonically increasing counter used to generate unique customer emails.
	 *
	 * @var int
	 */
	private static $email_counter = 0;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Return true if HPOS is active on this site.
	 *
	 * @return bool
	 */
	public static function is_hpos_enabled(): bool {
		if ( ! class_exists( OrderUtil::class ) ) {
			return false;
		}
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Insert $amount orders using raw SQL.
	 *
	 * @param int   $amount Number of orders to generate.
	 * @param array $args   CLI args (same keys as the ORM path).
	 * @return int[]|\WP_Error IDs of inserted orders, or a WP_Error on failure.
	 */
	public static function run( int $amount, array $args = array() ) {
		if ( ! self::is_hpos_enabled() ) {
			return new \WP_Error(
				'hpos_required',
				'--bulk-insert requires WooCommerce HPOS (High-Performance Order Storage) to be enabled. '
				. 'Enable it under WooCommerce > Settings > Advanced > Features.'
			);
		}

		self::init();

		if ( empty( self::$product_pool ) ) {
			return new \WP_Error(
				'no_products',
				'--bulk-insert requires at least one published product with a price greater than zero.'
			);
		}

		$all_ids   = array();
		$remaining = $amount;

		while ( $remaining > 0 ) {
			$batch_size = min( $remaining, self::SUB_BATCH_SIZE );
			$ids        = self::insert_batch( $batch_size, $args );

			if ( is_wp_error( $ids ) ) {
				return $ids;
			}

			$all_ids   = array_merge( $all_ids, $ids );
			$remaining -= $batch_size;

			// Fire the progress action once per inserted order.
			// Note: the order parameter is null in bulk mode — the action is used
			// solely for progress tracking (ticking the CLI progress bar).
			foreach ( $ids as $unused ) {
				do_action( 'smoothgenerator_order_generated', null );
			}
		}

		return $all_ids;
	}

	// -------------------------------------------------------------------------
	// Initialisation
	// -------------------------------------------------------------------------

	/**
	 * Load shared data that stays constant for the full generation run.
	 *
	 * @return void
	 */
	private static function init(): void {
		global $wpdb;

		// Disable WC email sending and ensure SERVER_NAME is set
		// (mirrors Generator::maybe_initialize_generators()).
		Generator::disable_emails();
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'localhost'; // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage
		}

		if ( null === self::$faker ) {
			self::$faker = \Faker\Factory::create( 'en_US' );
		}

		if ( null === self::$product_pool ) {
			self::$product_pool = $wpdb->get_results(
				"SELECT p.ID AS id,
				        p.post_title AS name,
				        CAST( COALESCE( pm.meta_value, '0' ) AS DECIMAL(10,2) ) AS price
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm
				         ON p.ID = pm.post_id AND pm.meta_key = '_price'
				 WHERE p.post_type   = 'product'
				   AND p.post_status = 'publish'
				   AND CAST( pm.meta_value AS DECIMAL(10,2) ) > 0
				 LIMIT 500"
			);
		}

		if ( null === self::$user_ids ) {
			self::$user_ids = array_map(
				'intval',
				$wpdb->get_col( "SELECT ID FROM {$wpdb->users}" )
			);
		}

		if ( null === self::$post_type ) {
			$sync            = wc_get_container()->get( DataSynchronizer::class );
			self::$post_type = $sync->data_sync_is_enabled()
				? 'shop_order'
				: DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE;
		}
	}

	// -------------------------------------------------------------------------
	// Batch orchestration
	// -------------------------------------------------------------------------

	/**
	 * Insert one sub-batch of orders across all required HPOS tables.
	 *
	 * @param int   $count Number of orders in this sub-batch.
	 * @param array $args  CLI args.
	 * @return int[]|\WP_Error
	 */
	private static function insert_batch( int $count, array $args ) {
		$data = self::prepare_batch_data( $count, $args );

		$order_ids = self::insert_posts( $data );
		if ( is_wp_error( $order_ids ) ) {
			return $order_ids;
		}

		self::insert_wc_orders( $order_ids, $data );
		self::insert_addresses( $order_ids, $data );
		self::insert_operational_data( $order_ids, $data );
		self::insert_meta( $order_ids, $data );
		self::insert_order_items( $order_ids, $data );

		return $order_ids;
	}

	// -------------------------------------------------------------------------
	// PHP-side data generation (no DB access)
	// -------------------------------------------------------------------------

	/**
	 * Generate all in-memory order data for a sub-batch.
	 *
	 * @param int   $count Number of orders.
	 * @param array $args  CLI args.
	 * @return array[]
	 */
	private static function prepare_batch_data( int $count, array $args ): array {
		$skip_attribution = isset( $args['skip-order-attribution'] );

		$dates = ! empty( $args['date-start'] ) ? self::generate_dates( $count, $args ) : null;

		$data = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$status         = ! empty( $args['status'] ) ? $args['status'] : self::random_status();
			$date_gmt       = $dates ? $dates[ $i ] : gmdate( 'Y-m-d H:i:s' );
			$date_paid_gmt  = null;
			$date_compl_gmt = null;

			if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
				$date_paid_gmt = gmdate(
					'Y-m-d H:i:s',
					strtotime( $date_gmt ) + wp_rand( 0, 36 ) * HOUR_IN_SECONDS
				);
				if ( 'completed' === $status ) {
					$date_compl_gmt = gmdate(
						'Y-m-d H:i:s',
						strtotime( $date_paid_gmt ) + wp_rand( 0, 36 ) * HOUR_IN_SECONDS
					);
				}
			}

			$items       = self::pick_products();
			$total       = (float) array_sum( array_map( fn( $item ) => $item['line_total'], $items ) );
			$billing     = self::generate_address_data();
			$shipping    = wp_rand( 0, 1 ) ? self::generate_address_data() : $billing;
			$customer_id = ! empty( self::$user_ids ) ? self::$user_ids[ array_rand( self::$user_ids ) ] : 0;
			$order_key   = 'wc_' . wp_generate_password( 13, false );

			$attribution = array();
			if ( ! $skip_attribution && strtotime( $date_gmt ) >= strtotime( '2024-01-09' ) ) {
				$attribution = OrderAttribution::generate_meta_array( $date_gmt );
			}

			$data[] = compact(
				'status', 'date_gmt', 'date_paid_gmt', 'date_compl_gmt',
				'total', 'items', 'billing', 'shipping',
				'customer_id', 'order_key', 'attribution'
			);
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// SQL insert helpers
	// -------------------------------------------------------------------------

	/**
	 * Bulk-insert rows into wp_posts and return the assigned IDs.
	 *
	 * wp_posts provides the canonical IDs that all other HPOS tables reference.
	 * After the INSERT we read the IDs back to handle MySQL 8.0's non-consecutive
	 * auto-increment behaviour (innodb_autoinc_lock_mode=2).
	 *
	 * @param array[] $data Batch data.
	 * @return int[]|\WP_Error
	 */
	private static function insert_posts( array $data ) {
		global $wpdb;

		$post_type = self::$post_type;
		$rows      = array();

		foreach ( $data as $row ) {
			$post_status = ( 'shop_order' === $post_type )
				? 'wc-' . $row['status']
				: 'draft';

			$rows[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d, %s, %s, %d)',
				$row['customer_id'],   // post_author
				$row['date_gmt'],      // post_date
				$row['date_gmt'],      // post_date_gmt
				'',                    // post_content
				'',                    // post_title
				'',                    // post_excerpt
				$post_status,          // post_status
				'open',                // comment_status
				'closed',              // ping_status
				'',                    // post_name
				'',                    // to_ping
				'',                    // pinged
				$row['date_gmt'],      // post_modified
				$row['date_gmt'],      // post_modified_gmt
				'',                    // post_content_filtered
				0,                     // post_parent
				'',                    // guid
				0,                     // menu_order
				$post_type,            // post_type
				'',                    // post_mime_type
				0                      // comment_count
			);
		}

		$cols = '(post_author, post_date, post_date_gmt, post_content, post_title,
		          post_excerpt, post_status, comment_status, ping_status, post_name,
		          to_ping, pinged, post_modified, post_modified_gmt,
		          post_content_filtered, post_parent, guid, menu_order,
		          post_type, post_mime_type, comment_count)';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- rows are individually prepared above
		$result = $wpdb->query( "INSERT INTO {$wpdb->posts} {$cols} VALUES " . implode( ',', $rows ) );

		if ( false === $result ) {
			return new \WP_Error(
				'db_insert_failed',
				'Failed to insert placeholder posts: ' . $wpdb->last_error
			);
		}

		$first_id = (int) $wpdb->insert_id;
		$count    = count( $data );

		// Read back the actual IDs. This handles MySQL 8.0's non-consecutive mode
		// and protects against concurrent inserts from other wp-cli processes.
		$order_ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE ID >= %d AND post_type = %s
					 ORDER BY ID ASC LIMIT %d",
					$first_id,
					$post_type,
					$count
				)
			)
		);

		if ( count( $order_ids ) !== $count ) {
			return new \WP_Error(
				'id_mismatch',
				sprintf(
					'Expected %d post IDs after bulk insert, got %d. '
					. 'This can happen under heavy concurrent load — retry the operation.',
					$count,
					count( $order_ids )
				)
			);
		}

		return $order_ids;
	}

	/**
	 * Bulk-insert rows into wp_wc_orders.
	 *
	 * @param int[]   $order_ids Ordered list of order IDs.
	 * @param array[] $data      Batch data aligned with $order_ids.
	 */
	private static function insert_wc_orders( array $order_ids, array $data ): void {
		global $wpdb;

		$rows  = array();
		$table = $wpdb->prefix . 'wc_orders';
		$cols  = '(id, status, currency, type, tax_amount, total_amount,
		           customer_id, billing_email, date_created_gmt, date_updated_gmt)';

		foreach ( $data as $i => $row ) {
			$rows[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %f, %f, %d, %s, %s, %s)',
				$order_ids[ $i ],
				'wc-' . $row['status'],
				get_woocommerce_currency(),
				'shop_order',
				0.0,
				$row['total'],
				$row['customer_id'],
				$row['billing']['email'],
				$row['date_gmt'],
				$row['date_gmt']
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$table} {$cols} VALUES " . implode( ',', $rows ) );
	}

	/**
	 * Bulk-insert billing and shipping rows into wp_wc_order_addresses.
	 *
	 * @param int[]   $order_ids Ordered list of order IDs.
	 * @param array[] $data      Batch data aligned with $order_ids.
	 */
	private static function insert_addresses( array $order_ids, array $data ): void {
		global $wpdb;

		$rows  = array();
		$table = $wpdb->prefix . 'wc_order_addresses';
		$cols  = '(order_id, address_type, first_name, last_name, company,
		           address_1, address_2, city, state, postcode, country, email, phone)';

		foreach ( $data as $i => $row ) {
			foreach ( array( 'billing', 'shipping' ) as $type ) {
				$addr   = $row[ $type ];
				$rows[] = $wpdb->prepare(
					'(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
					$order_ids[ $i ],
					$type,
					$addr['first_name'],
					$addr['last_name'],
					$addr['company'],
					$addr['address_1'],
					$addr['address_2'],
					$addr['city'],
					$addr['state'],
					$addr['postcode'],
					$addr['country'],
					'billing' === $type ? $addr['email'] : '',
					'billing' === $type ? $addr['phone'] : ''
				);
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$table} {$cols} VALUES " . implode( ',', $rows ) );
	}

	/**
	 * Bulk-insert rows into wp_wc_order_operational_data.
	 *
	 * @param int[]   $order_ids Ordered list of order IDs.
	 * @param array[] $data      Batch data aligned with $order_ids.
	 */
	private static function insert_operational_data( array $order_ids, array $data ): void {
		global $wpdb;

		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : '';
		$rows       = array();
		$table      = $wpdb->prefix . 'wc_order_operational_data';
		$cols       = '(order_id, created_via, woocommerce_version, prices_include_tax,
		                coupon_usages_are_counted, download_permission_granted,
		                new_order_email_sent, order_key, order_stock_reduced,
		                date_paid_gmt, date_completed_gmt)';

		foreach ( $data as $i => $row ) {
			$date_paid  = self::nullable_datetime( $row['date_paid_gmt'] );
			$date_compl = self::nullable_datetime( $row['date_compl_gmt'] );

			$base   = $wpdb->prepare(
				'(%d, %s, %s, %d, %d, %d, %d, %s, %d',
				$order_ids[ $i ],
				'smooth-generator',
				$wc_version,
				0,   // prices_include_tax
				1,   // coupon_usages_are_counted
				0,   // download_permission_granted
				0,   // new_order_email_sent
				$row['order_key'],
				0    // order_stock_reduced
			);
			$rows[] = $base . ', ' . $date_paid . ', ' . $date_compl . ')';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$table} {$cols} VALUES " . implode( ',', $rows ) );
	}

	/**
	 * Bulk-insert order attribution meta into wp_wc_orders_meta.
	 *
	 * @param int[]   $order_ids Ordered list of order IDs.
	 * @param array[] $data      Batch data aligned with $order_ids.
	 */
	private static function insert_meta( array $order_ids, array $data ): void {
		global $wpdb;

		$rows  = array();
		$table = $wpdb->prefix . 'wc_orders_meta';

		foreach ( $data as $i => $row ) {
			if ( empty( $row['attribution'] ) ) {
				continue;
			}
			foreach ( $row['attribution'] as $meta_key => $meta_value ) {
				$rows[] = $wpdb->prepare(
					'(%d, %s, %s)',
					$order_ids[ $i ],
					$meta_key,
					(string) $meta_value
				);
			}
		}

		if ( empty( $rows ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$table} (order_id, meta_key, meta_value) VALUES " . implode( ',', $rows ) );
	}

	/**
	 * Bulk-insert order line items and their meta.
	 *
	 * woocommerce_order_items has AUTO_INCREMENT, so we do the same
	 * read-back trick as insert_posts to get the real item IDs.
	 *
	 * @param int[]   $order_ids Ordered list of order IDs.
	 * @param array[] $data      Batch data aligned with $order_ids.
	 */
	private static function insert_order_items( array $order_ids, array $data ): void {
		global $wpdb;

		$item_rows   = array();
		$items_table = $wpdb->prefix . 'woocommerce_order_items';

		foreach ( $data as $i => $row ) {
			foreach ( $row['items'] as $item ) {
				$item_rows[] = $wpdb->prepare(
					'(%s, %s, %d)',
					$item['name'],
					'line_item',
					$order_ids[ $i ]
				);
			}
		}

		if ( empty( $item_rows ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"INSERT INTO {$items_table} (order_item_name, order_item_type, order_id) VALUES "
			. implode( ',', $item_rows )
		);

		$first_item_id = (int) $wpdb->insert_id;
		$total_items   = count( $item_rows );

		$item_ids = array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT order_item_id FROM {$items_table}
					 WHERE order_item_id >= %d
					 ORDER BY order_item_id ASC LIMIT %d",
					$first_item_id,
					$total_items
				)
			)
		);

		$meta_rows  = array();
		$meta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$meta_idx   = 0;

		$line_tax_data = serialize( array( 'total' => array(), 'subtotal' => array() ) );

		foreach ( $data as $row ) {
			foreach ( $row['items'] as $item ) {
				$item_id = $item_ids[ $meta_idx ] ?? null;
				if ( null === $item_id ) {
					$meta_idx++;
					continue;
				}

				foreach (
					array(
						'_product_id'        => $item['product_id'],
						'_variation_id'      => 0,
						'_qty'               => $item['qty'],
						'_tax_class'         => '',
						'_line_subtotal'     => $item['line_total'],
						'_line_subtotal_tax' => 0,
						'_line_total'        => $item['line_total'],
						'_line_tax'          => 0,
						'_line_tax_data'     => $line_tax_data,
					) as $meta_key => $meta_val
				) {
					$meta_rows[] = $wpdb->prepare( '(%d, %s, %s)', $item_id, $meta_key, $meta_val );
				}

				$meta_idx++;
			}
		}

		if ( ! empty( $meta_rows ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"INSERT INTO {$meta_table} (order_item_id, meta_key, meta_value) VALUES "
				. implode( ',', $meta_rows )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Data generation helpers (pure PHP, no DB)
	// -------------------------------------------------------------------------

	/**
	 * Randomly pick 1–5 products from the pre-fetched pool.
	 *
	 * @return array[]
	 */
	private static function pick_products(): array {
		$pool  = self::$product_pool;
		$count = count( $pool );
		$num   = wp_rand( 1, min( 5, $count ) );
		$keys  = (array) array_rand( (array) $pool, min( $num, $count ) );
		$items = array();

		foreach ( $keys as $key ) {
			$product = $pool[ $key ];
			$qty     = wp_rand( 1, 5 );
			$price   = (float) $product->price;
			$items[] = array(
				'product_id' => (int) $product->id,
				'name'       => $product->name,
				'qty'        => $qty,
				'line_total' => round( $price * $qty, 2 ),
			);
		}

		return $items;
	}

	/**
	 * Generate a fake customer address.
	 * Uses a single shared Faker instance to avoid the overhead of
	 * creating a new locale-specific Faker per order (as CustomerInfo does).
	 *
	 * @return array
	 */
	private static function generate_address_data(): array {
		$f          = self::$faker;
		$is_company = $f->boolean( 30 );

		return array(
			'first_name' => $is_company ? '' : $f->firstName(),
			'last_name'  => $is_company ? '' : $f->lastName(),
			'company'    => $is_company ? $f->company() : '',
			'address_1'  => $f->streetAddress(),
			'address_2'  => '',
			'city'       => $f->city(),
			'state'      => $f->stateAbbr(),
			'postcode'   => $f->postcode(),
			'country'    => 'US',
			'email'      => sprintf( 'order-%d@example.com', ++self::$email_counter ),
			'phone'      => $f->phoneNumber(),
		);
	}

	/**
	 * Return a random order status using the same weighted distribution as the ORM path.
	 *
	 * @return string
	 */
	private static function random_status(): string {
		$rand = wp_rand( 1, 100 );
		if ( $rand <= 70 ) {
			return 'completed';
		}
		if ( $rand <= 85 ) {
			return 'processing';
		}
		if ( $rand <= 90 ) {
			return 'on-hold';
		}
		return 'failed';
	}

	/**
	 * Pre-generate a sorted array of GMT datetime strings for a batch.
	 * Chronological sort ensures lower order IDs get earlier dates.
	 *
	 * @param int   $count Number of dates.
	 * @param array $args  CLI args with date-start / date-end.
	 * @return string[]
	 */
	private static function generate_dates( int $count, array $args ): array {
		$start    = $args['date-start'];
		$end      = ! empty( $args['date-end'] ) ? $args['date-end'] : gmdate( 'Y-m-d' );
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		$range    = max( 1, $end_ts - $start_ts );

		$dates = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$ts      = $start_ts + wp_rand( 0, $range );
			$dates[] = gmdate( 'Y-m-d ', $ts ) . wp_rand( 0, 23 ) . ':00:00';
		}

		sort( $dates );
		return $dates;
	}

	/**
	 * Return a SQL-safe datetime literal or the string 'NULL' for nullable columns.
	 *
	 * @param string|null $dt Datetime string or null.
	 * @return string
	 */
	private static function nullable_datetime( ?string $dt ): string {
		return $dt ? "'" . esc_sql( $dt ) . "'" : 'NULL';
	}
}
