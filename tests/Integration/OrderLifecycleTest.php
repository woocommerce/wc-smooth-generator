<?php
/**
 * Real WooCommerce order status integration tests.
 *
 * @package SmoothGenerator\Tests
 */

namespace WC\SmoothGenerator\Tests;

use PHPUnit\Framework\TestCase;
use WC\SmoothGenerator\Generator\OrderLifecycle as Lifecycle;

/** Exercises real WooCommerce persistence in an explicitly disposable site. */
class OrderLifecycleTest extends TestCase {
	private const AT = 1735948800;
	/**
	 * Create an eligible historical owned order.
	 *
	 * @param string $status Initial status.
	 * @return \WC_Order
	 */
	private function fixture( $status = 'processing' ) {
		$hooks                = $GLOBALS['wp_filter'];
		$GLOBALS['wp_filter'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Fixtures suppress application effects and restore hooks in finally.
		$controller           = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class );
		add_filter( 'woocommerce_order_data_store', array( $controller, 'get_orders_data_store' ), 999 );
		add_filter( 'woocommerce_order-refund_data_store', array( $controller, 'get_refunds_data_store' ), 999 );
		try {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Lifecycle fixture' );
			$product->set_regular_price( '20' );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 50 );
			$product->save();
			$order = new \WC_Order();
			$order->set_created_via( 'smooth-generator' );
			$order->set_billing_email( 'demo@example.invalid' );
			$order->set_date_created( self::AT - 3 * DAY_IN_SECONDS );
			$order->set_date_modified( self::AT - 3 * DAY_IN_SECONDS );
			$order->set_status( $status );
			$order->set_date_paid( 'processing' === $status ? self::AT - 2 * DAY_IN_SECONDS : null );
			$order->add_product( $product, 1 );
			$order->calculate_totals();
			$order->save();
		} finally {
			$GLOBALS['wp_filter'] = $hooks; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the exact pre-fixture hooks.
		}
		$this->historical_modified( $order );
		return $order;
	}
	/**
	 * Verify authoritative rows and compatibility copies.
	 *
	 * @param int    $id Order ID.
	 * @param string $status Expected status.
	 */
	private function assert_storage( $id, $status ) {
		global $wpdb;
		$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos || 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT status, date_updated_gmt FROM {$wpdb->prefix}wc_orders WHERE id = %d", $id ), ARRAY_A );
			$this->assertSame( 'wc-' . $status, $row['status'] );
			$this->assertSame( gmdate( 'Y-m-d H:i:s', self::AT ), $row['date_updated_gmt'] );
			$this->assertSame( 'completed' === $status ? gmdate( 'Y-m-d H:i:s', self::AT ) : null, $wpdb->get_var( $wpdb->prepare( "SELECT date_completed_gmt FROM {$wpdb->prefix}wc_order_operational_data WHERE order_id = %d", $id ) ) );
			$this->assertNotEmpty( $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = '_smooth_generator_lifecycle_attempt'", $id ) ) );
		}
		if ( ! $hpos || 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' ) ) {
			$post = $wpdb->get_row( $wpdb->prepare( "SELECT post_status, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
			$this->assertSame( 'wc-' . $status, $post['post_status'] );
			$this->assertSame( gmdate( 'Y-m-d H:i:s', self::AT ), $post['post_modified_gmt'] );
			$this->assertSame( Lifecycle::audit( $id )['record']['token'], get_post_meta( $id, '_smooth_generator_lifecycle_attempt', true ) );
			$this->assertSame( 'completed' === $status ? (string) self::AT : '', get_post_meta( $id, '_date_completed', true ) );
		} else {
			$this->assertSame( 'shop_order_placehold', get_post_type( $id ) );
			$this->assertSame( '', get_post_meta( $id, '_smooth_generator_lifecycle_attempt', true ) );
		}
		wp_cache_flush();
		$fresh = new \WC_Order( $id );
		$this->assertSame( $status, $fresh->get_status() );
		$this->assertSame( self::AT, $fresh->get_date_modified()->getTimestamp() );
	}

	/**
	 * Keep a mutated fixture eligible for chronology, isolating the guard under test.
	 *
	 * @param \WC_Order $order Fixture order.
	 */
	private function historical_modified( $order ) {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array(
			'post_modified_gmt' => '2025-01-02 00:00:00',
			'post_modified'     => get_date_from_gmt( '2025-01-02 00:00:00' ),
		), array( 'ID' => $order->get_id() ) );
		if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$wpdb->update( $wpdb->prefix . 'wc_orders', array( 'date_updated_gmt' => '2025-01-02 00:00:00' ), array( 'id' => $order->get_id() ) );
		}
		wp_cache_flush();
		clean_post_cache( $order->get_id() );
		$this->assertLessThan( self::AT, wc_get_order( $order->get_id() )->get_date_modified()->getTimestamp() );
	}

	/**
	 * Assert a specific public API error.
	 *
	 * @param string $code Error code.
	 * @param mixed  $result API result.
	 */
	private function error( $code, $result ) {
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
	}
	/** Verify the pre-WooCommerce 7 stock getter fallback without claiming old-runtime coverage. */
	public function test_legacy_stock_getter_fallback() {
		$order = $this->fixture();
		$store = new class() {
			/**
			 * Getter calls.
			 *
			 * @var int
			 */
			public $calls = 0;
			/**
			 * Reduced-stock fixture flag.
			 *
			 * @var bool
			 */
			public $reduced = true;
			/**
			 * Read the legacy datastore flag.
			 *
			 * @param object $order Unused fixture proxy.
			 * @return bool
			 */
			public function get_stock_reduced( $order ) {
				++$this->calls;
				return $this->reduced;
			}
		};
		$proxy = new class( $order, $store ) {
			/**
			 * Real fixture.
			 *
			 * @var \WC_Order
			 */
			private $order;
			/**
			 * Legacy datastore spy.
			 *
			 * @var object
			 */
			private $store;
			/**
			 * Wrap a current fixture without declaring the newer stock getter.
			 *
			 * @param \WC_Order $order Fixture.
			 * @param object    $store Legacy datastore spy.
			 */
			public function __construct( $order, $store ) {
				$this->order = $order;
				$this->store = $store;
			}
			/**
			 * Forward ordinary getters to the fixture.
			 *
			 * @param string $method Getter name.
			 * @param array  $args Getter arguments.
			 * @return mixed
			 */
			public function __call( $method, $args ) {
				return 'get_data_store' === $method ? $this->store : $this->order->{$method}( ...$args );
			}
		};
		$this->assertFalse( method_exists( $proxy, 'get_order_stock_reduced' ) );
		$snapshot = new \ReflectionMethod( Lifecycle::class, 'snapshot' );
		$this->assertTrue( $snapshot->invoke( null, $proxy )['stock_reduced'] );
		$store->reduced = false;
		$this->assertFalse( $snapshot->invoke( null, $proxy )['stock_reduced'] );
		$this->assertSame( 2, $store->calls );
	}

	/** Verify completion preservation side effects and noop. */
	public function test_completion_preservation_side_effects_and_noop() {
		$order   = $this->fixture();
		$id      = $order->get_id();
		$preview = Lifecycle::preview( $id, 'completed', self::AT );
		$warm    = wc_get_order( $id );
		$this->assertSame( 'processing', $warm->get_status() );
		$this->assertTrue( $preview['preview_only'] );
		$this->assertNull( Lifecycle::audit( $id )['record'] );
		$effects  = 0;
		$tripwire = function () use ( &$effects ) {
			++$effects;
		};
		$names    = array( 'woocommerce_order_status_completed', 'woocommerce_order_status_changed', 'woocommerce_update_order', 'save_post', 'updated_post_meta', 'added_post_meta', 'woocommerce_reduce_order_stock', 'woocommerce_restore_order_stock', 'woocommerce_payment_complete', 'wp_mail', 'http_api_debug' );
		foreach ( $names as $name ) {
			add_action( $name, $tripwire ); }
		$hooks  = $GLOBALS['wp_filter'];
		$result = Lifecycle::apply( $id, 'completed', self::AT );
		$this->assertIsArray( $result );
		$this->assertSame( 'completed', $result['state'] );
		$this->assertSame( 0, $effects );
		$this->assertSame( $hooks, $GLOBALS['wp_filter'] );
		foreach ( $names as $name ) {
			remove_action( $name, $tripwire ); }
		$audit = Lifecycle::audit( $id );
		$this->assertSame( self::AT, $audit['current']['completed'] );
		$this->assertSame( self::AT, $audit['current']['modified'] );
		foreach ( array( 'total', 'paid', 'items_sha256', 'stock_reduced', 'refund_ids', 'transaction_id' ) as $field ) {
			$this->assertSame( $preview['before'][ $field ], $audit['current'][ $field ] ); }
		$items = $order->get_items();
		$this->assertSame( 50, wc_get_product( current( $items )->get_product_id() )->get_stock_quantity() );
		$this->assertSame( 'completed', wc_get_order( $id )->get_status() );
		$this->assert_storage( $id, 'completed' );
		$this->assertSame( $result, Lifecycle::apply( $id, 'completed', self::AT ) );
		$this->error( 'lifecycle_recovery_required', Lifecycle::apply( $id, 'completed', self::AT + DAY_IN_SECONDS ) );
	}
	/** Verify cancellation states and money stock guards. */
	public function test_cancellation_states_and_money_stock_guards() {
		foreach ( array( 'pending', 'on-hold' ) as $status ) {
			$order = $this->fixture( $status );
			$this->assertIsArray( Lifecycle::apply( $order->get_id(), 'cancelled', self::AT ) );
			$current = Lifecycle::audit( $order->get_id() )['current'];
			$this->assertSame( 'cancelled', $current['status'] );
			$this->assertNull( $current['paid'] );
			$this->assertNull( $current['completed'] );
			$this->assertFalse( $current['stock_reduced'] );
			$this->assert_storage( $order->get_id(), 'cancelled' );
		}
		foreach ( array( 'paid', 'stock', 'transaction', 'refund' ) as $guard ) {
			$order = $this->fixture( 'pending' );
			if ( 'paid' === $guard ) {
				$order->set_date_paid( self::AT - DAY_IN_SECONDS );
				$order->save(); }
			if ( 'stock' === $guard ) {
				$order->get_data_store()->set_stock_reduced( $order, true ); }
			if ( 'transaction' === $guard ) {
				$order->set_transaction_id( 'gateway-record' );
				$order->save(); }
			if ( 'refund' === $guard ) {
				$refund = new \WC_Order_Refund();
				$refund->set_parent_id( $order->get_id() );
				$refund->set_amount( 0 );
				$refund->save(); }
			$this->historical_modified( $order );
			$this->error( 'lifecycle_ineligible', Lifecycle::apply( $order->get_id(), 'cancelled', self::AT ) );
			$this->assertNull( Lifecycle::audit( $order->get_id() )['record'] );
		}
	}
	/** Verify ownership and chronology. */
	public function test_ownership_and_chronology() {
		$order = $this->fixture();
		foreach ( array( array( 'set_created_via', 'checkout' ), array( 'set_billing_email', 'real@example.com' ) ) as $change ) {
			$original = $order->{str_replace( 'set_', 'get_', $change[0] )}();
			$order->{$change[0]}( $change[1] );
			$order->save();
			$this->error( 'lifecycle_not_owned', Lifecycle::preview( $order->get_id(), 'completed', self::AT ) );
			$order->{$change[0]}( $original );
			$order->save();
		}
		$this->error( 'lifecycle_invalid_input', Lifecycle::preview( $order->get_id(), 'refunded', self::AT ) );
		$this->error( 'lifecycle_invalid_input', Lifecycle::preview( $order->get_id(), 'completed', time() + 3600 ) );
		$this->error( 'lifecycle_ineligible', Lifecycle::preview( $order->get_id(), 'completed', self::AT - 3 * DAY_IN_SECONDS + 3600 ) );
		$order->set_date_paid( self::AT + 1 );
		$order->save();
		$this->historical_modified( $order );
		$this->error( 'lifecycle_ineligible', Lifecycle::preview( $order->get_id(), 'completed', self::AT ) );
		$customer = new \WC_Customer();
		$customer->set_username( wp_generate_uuid4() );
		$customer->set_email( wp_generate_uuid4() . '@example.invalid' );
		$customer->save();
		$order = $this->fixture();
		$order->set_customer_id( $customer->get_id() );
		$order->save();
		$this->error( 'lifecycle_not_owned', Lifecycle::preview( $order->get_id(), 'completed', self::AT ) );
		$customer->update_meta_data( '_smooth_generator_created', 'yes' );
		$customer->save();
		$this->historical_modified( $order );
		$this->assertIsArray( Lifecycle::preview( $order->get_id(), 'completed', self::AT ) );
	}
	/** Verify persisted custom and refund types are never owned. */
	public function test_persisted_custom_and_refund_types_are_never_owned() {
		global $wpdb;
		wc_register_order_type( 'smooth_custom_order', array(
			'class_name'                 => '\\WC_Order',
			'exclude_from_orders_screen' => true,
		) );
		foreach ( array( 'smooth_custom_order', 'shop_order_refund' ) as $type ) {
			$order = $this->fixture();
			$id    = $order->get_id();
			$hpos  = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			if ( $hpos ) {
				$wpdb->update( $wpdb->prefix . 'wc_orders', array( 'type' => $type ), array( 'id' => $id ) ); } else {
				$wpdb->update( $wpdb->posts, array( 'post_type' => $type ), array( 'ID' => $id ) ); }
				// Keep the previously warmed ordinary-order caches: the guard must use persisted type.
				$table  = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
				$column = $hpos ? 'id' : 'ID';
				$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE %i = %d', $table, $column, $id ), ARRAY_A );
				$this->error( 'lifecycle_not_owned', Lifecycle::preview( $id, 'completed', self::AT ) );
				$this->error( 'lifecycle_not_owned', Lifecycle::apply( $id, 'completed', self::AT ) );
				$this->assertNull( Lifecycle::audit( $id )['record'] );
				$this->assertNull( Lifecycle::audit( $id )['current'] );
				$this->assertSame( $before, $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE %i = %d', $table, $column, $id ), ARRAY_A ) );
		}
	}

	/** Verify modified date guard and storage fencing. */
	public function test_modified_date_guard_and_storage_fencing() {
		$order = $this->fixture();
		$order->set_customer_note( 'Later edit' );
		$order->save();
		$this->error( 'lifecycle_ineligible', Lifecycle::apply( $order->get_id(), 'completed', self::AT ) );
		$this->assertNull( Lifecycle::audit( $order->get_id() )['record'] );
		$order  = $this->fixture();
		$result = Lifecycle::apply( $order->get_id(), 'completed', self::AT );
		$this->assertIsArray( $result );
		$result['state'] = 'claimed';
		update_option( '_smooth_lifecycle_v1_' . $order->get_id(), $result );
		$sync = get_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'yes' === $sync ? 'no' : 'yes' );
		try {
			$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $result['token'], 'adopt' ) );
			$this->error( 'lifecycle_recovery_required', Lifecycle::apply( $order->get_id(), 'completed', self::AT ) );
		} finally {
			update_option( 'woocommerce_custom_orders_table_data_sync_enabled', $sync ); }
		$authority = get_option( 'woocommerce_custom_orders_table_enabled', 'no' );
		// A test-only option filter avoids scheduling a real migration while testing the fence.
		$flip = static function () use ( $authority ) {
			return 'yes' === $authority ? 'no' : 'yes';
		};
		add_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $flip );
		try {
			$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $result['token'], 'adopt' ) ); } finally {
			remove_filter( 'pre_option_woocommerce_custom_orders_table_enabled', $flip ); }
			$this->assertSame( 'completed', Lifecycle::recover( $order->get_id(), $result['token'], 'adopt' )['state'] );
	}

	/** Verify preview and audit do not import newer compatibility post. */
	public function test_preview_and_audit_do_not_import_newer_compatibility_post() {
		global $wpdb;
		if ( ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() || 'yes' !== get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) ) {
			$this->markTestSkipped( 'Requires HPOS authoritative with compatibility sync enabled.' ); }
		$order = $this->fixture();
		$id    = $order->get_id();
		$wpdb->update( $wpdb->posts, array(
			'post_status'       => 'wc-pending',
			'post_modified_gmt' => '2025-01-03 00:00:00',
			'post_modified'     => '2025-01-03 00:00:00',
		), array( 'ID' => $id ) );
		clean_post_cache( $id );
		$before = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_orders WHERE id = %d", $id ), ARRAY_A );
		$this->assertSame( 'processing', Lifecycle::preview( $id, 'completed', self::AT )['before']['status'] );
		$this->assertSame( 'processing', Lifecycle::audit( $id )['current']['status'] );
		$this->assertSame( $before, $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_orders WHERE id = %d", $id ), ARRAY_A ) );
		$this->assertSame( 'wc-pending', $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) ) );
		$this->assertIsArray( Lifecycle::apply( $id, 'completed', self::AT ) );
		$this->assert_storage( $id, 'completed' );
	}

	/** Verify refund read failure does not create a claim. */
	public function test_refund_read_failure_does_not_create_a_claim() {
		global $wpdb;
		$order    = $this->fixture();
		$fail     = static function ( $query ) {
			if ( false !== strpos( $query, 'shop_order_refund' ) && 0 === stripos( ltrim( $query ), 'SELECT' ) ) {
				return 'SELECT * FROM smooth_lifecycle_intentional_missing_table'; }
			return $query;
		};
		$previous = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$result = Lifecycle::apply( $order->get_id(), 'completed', self::AT ); } finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $previous ); }
			$this->error( 'lifecycle_read_failed', $result );
			$this->assertNull( Lifecycle::audit( $order->get_id() )['record'] );
			$this->assertSame( 'processing', Lifecycle::audit( $order->get_id() )['current']['status'] );
	}

	/** Verify compatibility backup failure cannot be reported completed. */
	public function test_compatibility_backup_failure_cannot_be_reported_completed() {
		global $wpdb;
		if ( ! \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() || 'yes' !== get_option( 'woocommerce_custom_orders_table_data_sync_enabled' ) ) {
			$this->markTestSkipped( 'Requires HPOS authoritative with compatibility sync enabled.' ); }
		$order = $this->fixture();
		$id    = $order->get_id();
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			( new \PDO( 'sqlite:' . DB_PATH ) )->exec( "CREATE TRIGGER smooth_lifecycle_backup_fault BEFORE INSERT ON {$wpdb->postmeta} WHEN NEW.meta_key = '_smooth_generator_lifecycle_attempt' BEGIN SELECT RAISE(ABORT, 'Intentional backup metadata fault'); END" ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- Native SQLite RAISE triggers cannot use the MySQL translation layer.
		} else {
			$wpdb->query( "CREATE TRIGGER smooth_lifecycle_backup_fault BEFORE INSERT ON {$wpdb->postmeta} FOR EACH ROW BEGIN IF NEW.meta_key = '_smooth_generator_lifecycle_attempt' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Intentional backup metadata fault'; END IF; END" ); }
		$previous = $wpdb->suppress_errors( true );
		$hooks    = $GLOBALS['wp_filter'];
		try {
			$result = Lifecycle::apply( $id, 'completed', self::AT ); } finally {
			$wpdb->query( 'DROP TRIGGER smooth_lifecycle_backup_fault' );
			$wpdb->suppress_errors( $previous ); }
			$this->assertSame( $hooks, $GLOBALS['wp_filter'] );
			$this->error( 'lifecycle_failed', $result );
			$audit = Lifecycle::audit( $id );
			$this->assertSame( 'completed', $audit['current']['status'] );
			$this->assertSame( 'failed', $audit['record']['state'] );
			$this->assertSame( '', get_post_meta( $id, '_smooth_generator_lifecycle_attempt', true ) );
			$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $id, $audit['record']['token'], 'adopt' ) );
			$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $id, $audit['record']['token'], 'retry' ) );
			$this->error( 'lifecycle_recovery_required', Lifecycle::apply( $id, 'completed', self::AT ) );
	}

	/** Verify partial save failure and abandonment. */
	public function test_partial_save_failure_and_abandonment() {
		global $wpdb;
		$order      = $this->fixture();
		$meta_table = \Automattic\WooCommerce\Utilities\OrderUtil::get_table_for_order_meta();
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			( new \PDO( 'sqlite:' . DB_PATH ) )->exec( "CREATE TRIGGER smooth_lifecycle_fault BEFORE INSERT ON {$meta_table} WHEN NEW.meta_key = '_smooth_generator_lifecycle_attempt' BEGIN SELECT RAISE(ABORT, 'Intentional partial save'); END" ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__PDO -- Native SQLite RAISE triggers cannot use the MySQL translation layer.
		} else {
			$wpdb->query( $wpdb->prepare( "CREATE TRIGGER smooth_lifecycle_fault BEFORE INSERT ON %i FOR EACH ROW BEGIN IF NEW.meta_key = '_smooth_generator_lifecycle_attempt' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Intentional partial save'; END IF; END", $meta_table ) ); }
		$previous = $wpdb->suppress_errors( true );
		$hooks    = $GLOBALS['wp_filter'];
		try {
			$result = Lifecycle::apply( $order->get_id(), 'completed', self::AT ); } finally {
			$wpdb->query( 'DROP TRIGGER smooth_lifecycle_fault' );
			$wpdb->suppress_errors( $previous ); }
			$this->assertSame( $hooks, $GLOBALS['wp_filter'] );
			$this->error( 'lifecycle_failed', $result );
			$audit = Lifecycle::audit( $order->get_id() );
			$this->assertSame( 'failed', $audit['record']['state'] );
			$this->assertNotEmpty( $audit['record']['error'] );
			$this->assertSame( 'completed', $audit['current']['status'] );
			foreach ( array( 'retry', 'adopt' ) as $decision ) {
				$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $audit['record']['token'], $decision ) ); }
			$this->assertSame( 'abandoned', Lifecycle::recover( $order->get_id(), $audit['record']['token'], 'abandon' )['state'] );
			$this->error( 'lifecycle_recovery_required', Lifecycle::apply( $order->get_id(), 'completed', self::AT ) );
	}
	/** Verify retry history stale token and crash adoption. */
	public function test_retry_history_stale_token_and_crash_adoption() {
		$order    = $this->fixture();
		$name     = '_smooth_lifecycle_v1_' . $order->get_id();
		$proposal = Lifecycle::preview( $order->get_id(), 'completed', self::AT );
		$record   = array(
			'state'       => 'failed',
			'token'       => wp_generate_uuid4(),
			'proposal'    => $proposal,
			'history'     => array(),
			'error'       => 'Interrupted before save',
			'updated_gmt' => '2020-01-01 00:00:00',
		);
		add_option( $name, $record, '', false );
		$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), 'stale', 'retry' ) );
		$result = Lifecycle::recover( $order->get_id(), $record['token'], 'retry' );
		$this->assertSame( 'completed', $result['state'] );
		$this->assertCount( 1, $result['history'] );
		$this->assertSame( $record['error'], $result['history'][0]['error'] );
		$this->assertNotSame( $record['token'], $result['token'] );
		$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $record['token'], 'abandon' ) );
		$result['state'] = 'claimed';
		update_option( $name, $result );
		$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $result['token'], 'abandon' ) );
		$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $result['token'], 'retry' ) );
		$this->assertSame( 'completed', Lifecycle::recover( $order->get_id(), $result['token'], 'adopt' )['state'] );
	}
	/** Verify concurrent claim has one winner. */
	public function test_concurrent_claim_has_one_winner() {
		$order   = $this->fixture();
		$barrier = sys_get_temp_dir() . '/smooth-lifecycle-barrier-' . wp_generate_uuid4();
		wp_mkdir_p( $barrier );
		$command = array( PHP_BINARY, '-d', 'error_reporting=22527', '-d', 'sendmail_path=/usr/bin/false', __DIR__ . '/lifecycle-worker.php', (string) $order->get_id(), (string) self::AT, $barrier );
		$workers = array();
		for ( $i = 0; $i < 2; ++$i ) {
			$process   = proc_open( $command, array( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Two independent PHP workers exercise real claim races.
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			), $pipes );
			$workers[] = array( $process, $pipes ); }
		$results = array();
		foreach ( $workers as $worker ) {
			$output = stream_get_contents( $worker[1][1] );
			$error  = stream_get_contents( $worker[1][2] );
			fclose( $worker[1][1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close test-worker process streams.
			fclose( $worker[1][2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close test-worker process streams.
			$this->assertSame( 0, proc_close( $worker[0] ), $error );
			$results[] = json_decode( $output, true ); }
		wp_cache_flush();
		$this->assertCount( 1, array_filter( $results, function ( $result ) {
			return isset( $result['state'] ) && 'completed' === $result['state'];
		} ) );
		$this->assertCount( 1, array_filter( $results, function ( $result ) {
			return isset( $result['error'] ) && 'lifecycle_claimed' === $result['error'];
		} ) );
		$this->assertSame( 'completed', Lifecycle::audit( $order->get_id() )['record']['state'] );
		foreach ( glob( $barrier . '/*' ) as $file ) {
			wp_delete_file( $file );
		} rmdir( $barrier ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove the task-owned empty barrier directory.
	}
	/** Verify abandon cannot overlap a claimed writer. */
	public function test_abandon_cannot_overlap_a_claimed_writer() {
		global $wpdb;
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			$this->markTestSkipped( 'MariaDB SLEEP trigger forces an in-flight save; SQLite has no SLEEP function.' ); }
		$order = $this->fixture();
		$table = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
		$wpdb->query( $wpdb->prepare( 'CREATE TRIGGER smooth_lifecycle_pause BEFORE UPDATE ON %i FOR EACH ROW DO SLEEP(2)', $table ) );
		$command = array( PHP_BINARY, '-d', 'error_reporting=22527', '-d', 'sendmail_path=/usr/bin/false', __DIR__ . '/lifecycle-worker.php', (string) $order->get_id(), (string) self::AT );
		$process = proc_open( $command, array( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Two independent PHP workers exercise real claim races.
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		), $pipes );
		try {
			$deadline = microtime( true ) + 10;
			do {
				wp_cache_flush();
				$record = Lifecycle::audit( $order->get_id() )['record'];
				usleep( 20000 );
			} while ( ! $record && microtime( true ) < $deadline );
			$this->assertSame( 'claimed', $record['state'] );
			$this->error( 'lifecycle_recovery_conflict', Lifecycle::recover( $order->get_id(), $record['token'], 'abandon' ) );
			$output = stream_get_contents( $pipes[1] );
			$error  = stream_get_contents( $pipes[2] );
			fclose( $pipes[1] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close test-worker process streams.
			fclose( $pipes[2] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close test-worker process streams.
			$this->assertSame( 0, proc_close( $process ), $error );
			$this->assertSame( 'completed', json_decode( $output, true )['state'] );
		} finally {
			$wpdb->query( 'DROP TRIGGER smooth_lifecycle_pause' ); }
	}
}
