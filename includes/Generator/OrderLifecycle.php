<?php
/**
 * Conservative synthetic order lifecycle with durable claims.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Explicit final transitions only; never processes payments, refunds or stock.
 */
class OrderLifecycle {
	/**
	 * Preview one later-date transition for an explicitly selected owned order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status completed or cancelled.
	 * @param int    $at Effective UTC timestamp, never future.
	 * @return array|\WP_Error
	 */
	public static function preview( $order_id, $status, $at ) {
		if ( ! self::valid_input( $order_id, $status, $at ) ) {
			return new \WP_Error( 'lifecycle_invalid_input', 'Use a positive order ID, completed/cancelled and an explicit past UTC timestamp. Refunds are unsupported.' );
		}
		$order = self::fresh_order( $order_id );
		if ( ! self::owned( $order ) ) {
			return new \WP_Error( 'lifecycle_not_owned', 'Only generator-owned ordinary orders with a marked customer or .invalid guest email qualify.' );
		}
		$before = self::snapshot( $order );
		if ( is_wp_error( $before ) ) {
			return $before;
		}
		if ( $before['refund_ids'] || $before['refunded'] > 0 || '' !== $before['transaction_id'] || $before['completed']
			|| $before['activity'] || $before['attempt'] || ! $before['created'] || $at <= $before['created'] || ( $before['modified'] && $at < $before['modified'] )
			|| gmdate( 'Y-m-d', $at ) <= gmdate( 'Y-m-d', $before['created'] ) ) {
			return new \WP_Error( 'lifecycle_ineligible', 'Refunds, transaction IDs, existing completion/activity or non-later dates require manual review.' );
		}
		if ( 'completed' === $status ) {
			if ( 'processing' !== $before['status'] || ! $before['paid'] || $before['paid'] < $before['created'] || $before['paid'] > $at ) {
				return new \WP_Error( 'lifecycle_ineligible', 'Completion requires processing with coherent existing paid/created dates.' );
			}
		} elseif ( ! in_array( $before['status'], array( 'pending', 'on-hold' ), true ) || $before['paid'] || $before['stock_reduced'] ) {
			return new \WP_Error( 'lifecycle_ineligible', 'Cancellation requires unpaid pending/on-hold with no stock reduction.' );
		}
		return array(
			'preview_only' => true,
			'storage'      => self::storage_mode(),
			'activity_key' => 'lifecycle-v1-' . $order_id . '-' . gmdate( 'Y-m-d', $at ),
			'order_id'     => $order_id,
			'to'           => $status,
			'at'           => $at,
			'before'       => $before,
		);
	}

	/**
	 * Apply once per owned order, retaining per-date evidence.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status Requested final status.
	 * @param int    $at Effective UTC timestamp.
	 * @return array|\WP_Error
	 */
	public static function apply( $order_id, $status, $at ) {
		if ( ! self::valid_input( $order_id, $status, $at ) ) {
			return new \WP_Error( 'lifecycle_invalid_input', 'Invalid lifecycle inputs; no transition attempted.' );
		}
		$name   = self::option_name( $order_id );
		$record = get_option( $name, false );
		if ( false !== $record ) {
			if ( isset( $record['proposal']['to'], $record['proposal']['at'] ) && $status === $record['proposal']['to'] && $at === $record['proposal']['at']
				&& 'completed' === $record['state'] && self::matches( $record ) ) {
				return $record;
			}
			return new \WP_Error( 'lifecycle_recovery_required', 'Existing activity or changed inputs require audit; nothing was retried.' );
		}
		$proposal = self::preview( $order_id, $status, $at );
		if ( is_wp_error( $proposal ) ) {
			return $proposal;
		}
		$record = self::claim( $proposal, array() );
		if ( ! self::insert_claim( $name, $record ) ) {
			return new \WP_Error( 'lifecycle_claimed', 'Another attempt owns this order. Inspect audit.' );
		}
		return self::persist( $name, $record );
	}

	/**
	 * Inspect evidence and current owned-field projection without mutation.
	 *
	 * @param int $order_id Order ID.
	 * @return array|\WP_Error
	 */
	public static function audit( $order_id ) {
		if ( ! is_int( $order_id ) || $order_id <= 0 ) {
			return new \WP_Error( 'lifecycle_invalid_input', 'Supply a positive order ID.' );
		}
		$order = self::fresh_order( $order_id );
		return array(
			'record'  => get_option( self::option_name( $order_id ), null ),
			'current' => self::owned( $order ) ? self::snapshot( $order ) : null,
		);
	}

	/**
	 * Recover after inspecting audit; no deletion or automatic crash retry.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token Current attempt token.
	 * @param string $decision retry, adopt or abandon.
	 * @return array|\WP_Error
	 */
	public static function recover( $order_id, $token, $decision ) {
		if ( ! is_int( $order_id ) || $order_id <= 0 ) {
			return new \WP_Error( 'lifecycle_invalid_input', 'Supply a positive order ID.' );
		}
		$name   = self::option_name( $order_id );
		$record = get_option( $name, false );
		if ( ! is_array( $record ) || ! isset( $record['token'], $record['state'] ) || ! hash_equals( $record['token'], (string) $token )
			|| ! in_array( $record['state'], array( 'claimed', 'failed' ), true ) ) {
			return new \WP_Error( 'lifecycle_recovery_conflict', 'Exact current unfinished attempt token required.' );
		}
		$next = $record;
		if ( 'retry' === $decision && 'failed' === $record['state'] ) {
			$p        = $record['proposal'];
			$proposal = self::preview( $order_id, $p['to'], $p['at'] );
			if ( is_wp_error( $proposal ) || $proposal !== $p ) {
				return new \WP_Error( 'lifecycle_recovery_conflict', 'Retry requires the exact unchanged pre-transition order.' );
			}
			$history   = $record['history'];
			$history[] = array_diff_key( $record, array( 'history' => true ) );
			$next      = self::claim( $proposal, $history );
		} elseif ( 'adopt' === $decision && self::matches( $record ) ) {
			$next['state'] = 'completed';
		} elseif ( 'abandon' === $decision && 'failed' === $record['state'] ) {
			$next['state'] = 'abandoned';
		} else {
			return new \WP_Error( 'lifecycle_recovery_conflict', 'Retry only unchanged failed attempts; adopt only intact owned outcomes; otherwise abandon.' );
		}
		$next['updated_gmt'] = gmdate( 'Y-m-d H:i:s' );
		if ( ! self::compare_swap( $name, $record, $next ) ) {
			return new \WP_Error( 'lifecycle_recovery_conflict', 'Another worker changed the evidence.' );
		}
		return 'retry' === $decision ? self::persist( $name, $next ) : $next;
	}

	/**
	 * Record both authority and compatibility mode to fence recovery across switches.
	 *
	 * @return array
	 */
	private static function storage_mode() {
		return array(
			'authority' => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'hpos' : 'posts',
			'sync'      => 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' ),
		);
	}

	/**
	 * Retain WooCommerce storage infrastructure only, never application callbacks.
	 *
	 * HPOS writes backfill posts directly; legacy writes need the core sync callback.
	 */
	private static function install_storage_hooks() {
		if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
			add_filter( 'woocommerce_order_data_store', array( $controller, 'get_orders_data_store' ), 999 );
			add_filter( 'woocommerce_order-refund_data_store', array( $controller, 'get_refunds_data_store' ), 999 );
			$synchronizer = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
			add_action( 'woocommerce_update_order', array( $synchronizer, 'handle_updated_order' ), 100 );
		}
		// A read must never import a newer compatibility post over authoritative data.
		add_filter( 'woocommerce_hpos_enable_sync_on_read', '__return_false' );
	}

	/**
	 * Read through WooCommerce with fresh metadata and without sync-on-read writes.
	 *
	 * @param int         $id Order ID.
	 * @param string|null $authority Explicit backend for compatibility verification.
	 * @return \WC_Order|false
	 */
	private static function fresh_order( $id, $authority = null ) {
		$authority    = $authority ?? self::storage_mode()['authority'];
		$select_store = static function () use ( $authority ) {
			return 'hpos' === $authority ? wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class ) : 'WC_Order_Data_Store_CPT';
		};
		$disable_sync = static function () {
			return false;
		};
		add_filter( 'woocommerce_order_data_store', $select_store, PHP_INT_MAX );
		add_filter( 'woocommerce_hpos_enable_sync_on_read', $disable_sync, PHP_INT_MAX );
		try {
			if ( 'hpos' === $authority ) {
				wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::class )->clear_cached_data( array( $id ) );
			}
			// WC_Order::get_type() is class-based; validate the persisted type first.
			wp_cache_delete( $id, 'posts' );
			if ( 'shop_order' !== \WC_Data_Store::load( 'order' )->get_order_type( $id ) ) {
				return false;
			}
			$order = new \WC_Order( $id );
			$order->read_meta_data( true );
			return $order;
		} catch ( \Exception $error ) {
			return false;
		} finally {
			remove_filter( 'woocommerce_hpos_enable_sync_on_read', $disable_sync, PHP_INT_MAX );
			remove_filter( 'woocommerce_order_data_store', $select_store, PHP_INT_MAX );
		}
	}

	/**
	 * Strict public inputs; no implicit current-date default.
	 *
	 * @param int    $id Order ID.
	 * @param string $status Target status.
	 * @param int    $at Effective timestamp.
	 * @return bool
	 */
	private static function valid_input( $id, $status, $at ) {
		return is_int( $id ) && $id > 0 && in_array( $status, array( 'completed', 'cancelled' ), true ) && is_int( $at ) && $at > 0 && $at <= time();
	}

	/**
	 * Avoid unrelated accounts, real transaction records and subscription objects.
	 *
	 * @param mixed $order Loaded order.
	 * @return bool
	 */
	private static function owned( $order ) {
		if ( ! $order instanceof \WC_Order || 'shop_order' !== $order->get_type() || 'smooth-generator' !== $order->get_created_via() ) {
			return false;
		}
		if ( $order->get_customer_id() ) {
			try {
				$customer = new \WC_Customer( $order->get_customer_id() );
				return $customer->get_id() && 'yes' === $customer->get_meta( '_smooth_generator_created' );
			} catch ( \Exception $error ) {
				return false;
			}
		}
		return (bool) preg_match( '/@[^@]+\.invalid$/i', $order->get_billing_email() );
	}

	/**
	 * Stable timestamp representation.
	 *
	 * @param mixed $date WooCommerce date or null.
	 * @return int|null
	 */
	private static function timestamp( $date ) {
		return $date ? $date->getTimestamp() : null;
	}

	/**
	 * Fields that must remain intact across this narrow status-only operation.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|\WP_Error
	 */
	private static function snapshot( $order ) {
		global $wpdb;
		// Only existence matters: a bounded query avoids MySQL's unbounded LIMIT
		// sentinel, which the official SQLite driver cannot represent.
		$refund_ids = wc_get_orders( array(
			'type'   => 'shop_order_refund',
			'parent' => $order->get_id(),
			'limit'  => 1,
			'return' => 'ids',
		) );
		if ( $wpdb->last_error || ! is_array( $refund_ids ) ) {
			return new \WP_Error( 'lifecycle_read_failed', 'Refund eligibility could not be read; no transition attempted.' );
		}
		$items = array();
		foreach ( $order->get_items( array( 'line_item', 'shipping', 'fee', 'coupon', 'tax' ) ) as $id => $item ) {
			$items[ $id ] = $item->get_data();
		}
		return array(
			'status'         => $order->get_status(),
			'created'        => self::timestamp( $order->get_date_created() ),
			'paid'           => self::timestamp( $order->get_date_paid() ),
			'completed'      => self::timestamp( $order->get_date_completed() ),
			'modified'       => self::timestamp( $order->get_date_modified() ),
			'customer_id'    => $order->get_customer_id(),
			'created_via'    => $order->get_created_via(),
			'billing_email'  => $order->get_billing_email(),
			'transaction_id' => $order->get_transaction_id(),
			'payment_method' => $order->get_payment_method(),
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'items_sha256'   => hash( 'sha256', wp_json_encode( $items ) ),
			'stock_reduced'  => method_exists( $order, 'get_order_stock_reduced' ) ? (bool) $order->get_order_stock_reduced( 'edit' ) : (bool) $order->get_data_store()->get_stock_reduced( $order ),
			'refund_ids'     => array_map( 'intval', $refund_ids ),
			'refunded'       => $order->get_total_refunded(),
			'activity'       => $order->get_meta( '_smooth_generator_lifecycle_activity' ),
			'attempt'        => $order->get_meta( '_smooth_generator_lifecycle_attempt' ),
		);
	}

	/**
	 * Verify the complete expected projection and attempt marker after save.
	 *
	 * @param array $record Attempt evidence.
	 * @return bool
	 */
	private static function matches( $record ) {
		$p = $record['proposal'];
		if ( ! isset( $p['storage'] ) || self::storage_mode() !== $p['storage'] ) {
			return false;
		}
		$order = self::fresh_order( $p['order_id'] );
		if ( ! self::owned( $order ) ) {
			return false;
		}
		$expected              = $p['before'];
		$expected['status']    = $p['to'];
		$expected['modified']  = $p['at'];
		$expected['completed'] = 'completed' === $p['to'] ? $p['at'] : null;
		$expected['activity']  = $p['activity_key'];
		$expected['attempt']   = $record['token'];
		if ( self::snapshot( $order ) !== $expected ) {
			return false;
		}
		if ( $p['storage']['sync'] ) {
			$copy = self::fresh_order( $p['order_id'], 'hpos' === $p['storage']['authority'] ? 'posts' : 'hpos' );
			return self::owned( $copy ) && self::snapshot( $copy ) === $expected;
		}
		return true;
	}

	/**
	 * Unique per-order claim also prevents conflicting dates or final states.
	 *
	 * @param int $id Order ID.
	 * @return string
	 */
	private static function option_name( $id ) {
		return '_smooth_lifecycle_v1_' . $id;
	}

	/**
	 * Construct a new attempt retaining earlier failures.
	 *
	 * @param array $proposal Preview.
	 * @param array $history Prior attempts.
	 * @return array
	 */
	private static function claim( $proposal, $history ) {
		return array(
			'state'       => 'claimed',
			'token'       => wp_generate_uuid4(),
			'proposal'    => $proposal,
			'history'     => $history,
			'updated_gmt' => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Unique insert; never overwrites a competing claim.
	 *
	 * @param string $name Option name.
	 * @param array  $record Evidence.
	 * @return bool
	 */
	private static function insert_claim( $name, $record ) {
		global $wpdb;
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, maybe_serialize( $record ) ) );
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $inserted;
	}

	/**
	 * Token/state-safe evidence replacement.
	 *
	 * @param string $name Option name.
	 * @param array  $old Expected evidence.
	 * @param array  $next Replacement evidence.
	 * @return bool
	 */
	private static function compare_swap( $name, $old, $next ) {
		global $wpdb;
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND HEX(option_value) = HEX(%s)", maybe_serialize( $next ), $name, maybe_serialize( $old ) ) );
		wp_cache_delete( $name, 'options' );
		return 1 === $updated;
	}

	/**
	 * Save with application hooks suspended, restoring them on every path.
	 *
	 * No payment/status API with gateway behavior, restocking or event delivery.
	 * Analytics projections are deliberately not rebuilt by this slice.
	 *
	 * @param string $name Claim name.
	 * @param array  $record Claimed attempt.
	 * @return array|\WP_Error
	 * @throws \RuntimeException Caught and retained as failure evidence.
	 */
	private static function persist( $name, $record ) {
		global $wp_filter;
		$hooks     = $wp_filter;
		$wp_filter = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		try {
			self::install_storage_hooks();
			$p       = $record['proposal'];
			$current = self::preview( $p['order_id'], $p['to'], $p['at'] );
			if ( get_option( $name ) !== $record || is_wp_error( $current ) || $current !== $p ) {
				throw new \RuntimeException( 'Claim or owned order changed before persistence.' );
			}
			$order = self::fresh_order( $p['order_id'] );
			$order->set_status( $p['to'], false );
			$order->set_date_completed( 'completed' === $p['to'] ? $p['at'] : null );
			$order->set_date_modified( $p['at'] );
			$order->update_meta_data( '_smooth_generator_lifecycle_activity', $p['activity_key'] );
			$order->update_meta_data( '_smooth_generator_lifecycle_attempt', $record['token'] );
			// WordPress otherwise replaces explicit modified dates with wall-clock time.
			// This is the only callback installed while application hooks are suspended.
			add_filter( 'wp_insert_post_data', static function ( $data, $postarr ) use ( $p ) {
				if ( isset( $postarr['ID'] ) && (int) $postarr['ID'] === $p['order_id'] ) {
					$data['post_modified_gmt'] = gmdate( 'Y-m-d H:i:s', $p['at'] );
					$data['post_modified']     = get_date_from_gmt( $data['post_modified_gmt'] );
				}
				return $data;
			}, 10, 2 );
			$order->save();
			if ( ! self::matches( $record ) ) {
				throw new \RuntimeException( 'Persisted order or compatibility copy does not match the exact proposal; inspect audit.' );
			}
			$next                = $record;
			$next['state']       = 'completed';
			$next['updated_gmt'] = gmdate( 'Y-m-d H:i:s' );
			if ( ! self::compare_swap( $name, $record, $next ) ) {
				throw new \RuntimeException( 'Order saved but evidence changed; inspect audit.' );
			}
			return $next;
		} catch ( \Throwable $error ) {
			$next                = $record;
			$next['state']       = 'failed';
			$next['error']       = $error->getMessage();
			$next['updated_gmt'] = gmdate( 'Y-m-d H:i:s' );
			self::compare_swap( $name, $record, $next );
			return new \WP_Error( 'lifecycle_failed', 'Transition failed; inspect audit before explicit recovery.' );
		} finally {
			$wp_filter = $hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
