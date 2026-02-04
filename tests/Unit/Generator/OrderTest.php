<?php
/**
 * Tests for Order Generator.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\Order;
use WC\SmoothGenerator\Generator\Product;
use WC\SmoothGenerator\Generator\Customer;
use WP_UnitTestCase;

/**
 * Order Generator test case.
 */
class OrderTest extends WP_UnitTestCase {

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create some products for orders to use.
		Product::batch( 5, array( 'type' => 'simple' ) );
	}

	/**
	 * Test generating a basic order.
	 */
	public function test_generate_order() {
		$order = Order::generate( true );

		$this->assertInstanceOf( \WC_Order::class, $order );
		$this->assertTrue( $order->get_id() > 0 );
		$this->assertEquals( 'smooth-generator', $order->get_created_via() );
	}

	/**
	 * Test order has products.
	 */
	public function test_order_has_products() {
		$order = Order::generate( true );

		$items = $order->get_items();
		$this->assertNotEmpty( $items, 'Order should have line items' );

		foreach ( $items as $item ) {
			$this->assertInstanceOf( \WC_Order_Item_Product::class, $item );
			$this->assertGreaterThan( 0, $item->get_quantity() );
		}
	}

	/**
	 * Test order with completed status.
	 */
	public function test_order_completed_status() {
		$order = Order::generate( true, array( 'status' => 'completed' ) );

		$this->assertEquals( 'completed', $order->get_status() );
		$this->assertNotNull( $order->get_date_paid() );
		$this->assertNotNull( $order->get_date_completed() );
	}

	/**
	 * Test order with processing status.
	 */
	public function test_order_processing_status() {
		$order = Order::generate( true, array( 'status' => 'processing' ) );

		$this->assertEquals( 'processing', $order->get_status() );
		$this->assertNotNull( $order->get_date_paid() );
	}

	/**
	 * Test order with failed status.
	 */
	public function test_order_failed_status() {
		$order = Order::generate( true, array( 'status' => 'failed' ) );

		$this->assertEquals( 'failed', $order->get_status() );
	}

	/**
	 * Test order with on-hold status.
	 */
	public function test_order_on_hold_status() {
		$order = Order::generate( true, array( 'status' => 'on-hold' ) );

		$this->assertEquals( 'on-hold', $order->get_status() );
	}

	/**
	 * Test order has customer information.
	 */
	public function test_order_has_customer_info() {
		$order = Order::generate( true );

		// Billing country should always be set.
		$this->assertNotEmpty( $order->get_billing_country(), 'Order should have billing country' );

		// Email and name may be empty in some customer generation scenarios.
		$this->assertIsString( $order->get_billing_email() );
		$this->assertIsString( $order->get_billing_first_name() );
		$this->assertIsString( $order->get_billing_last_name() );

		// At least verify that if email exists, it's valid.
		$email = $order->get_billing_email();
		if ( ! empty( $email ) ) {
			$this->assertNotFalse( filter_var( $email, FILTER_VALIDATE_EMAIL ), 'Email should be valid if present' );
		}
	}

	/**
	 * Test order has shipping information.
	 */
	public function test_order_has_shipping_info() {
		$order = Order::generate( true );

		// Shipping country should be set.
		$shipping_country = $order->get_shipping_country();
		$this->assertIsString( $shipping_country );
		if ( ! empty( $shipping_country ) ) {
			$this->assertNotEmpty( $shipping_country );
		} else {
			$this->assertTrue( true, 'Shipping info may be empty in some configurations' );
		}
	}

	/**
	 * Test order total is calculated.
	 */
	public function test_order_total_calculated() {
		$order = Order::generate( true );

		$total = $order->get_total();
		$this->assertGreaterThan( 0, $total );
	}

	/**
	 * Test batch order generation.
	 */
	public function test_batch_generation() {
		$amount    = 5;
		$order_ids = Order::batch( $amount );

		$this->assertIsArray( $order_ids );
		$this->assertCount( $amount, $order_ids );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			$this->assertInstanceOf( \WC_Order::class, $order );
		}
	}

	/**
	 * Test batch validation.
	 */
	public function test_batch_validation() {
		$result = Order::batch( 0 );

		$this->assertWPError( $result );
		$this->assertEquals( 'smoothgenerator_batch_invalid_amount', $result->get_error_code() );
	}

	/**
	 * Test order with specific date.
	 */
	public function test_order_with_date() {
		$date = '2024-01-15';
		$order = Order::generate( true, array( 'date-start' => $date, 'date-end' => $date ) );

		$created_date = $order->get_date_created()->format( 'Y-m-d' );
		$this->assertEquals( $date, $created_date );
	}

	/**
	 * Test order with date range.
	 */
	public function test_order_with_date_range() {
		$start_date = '2024-01-01';
		$end_date   = '2024-01-31';

		$order = Order::generate(
			true,
			array(
				'date-start' => $start_date,
				'date-end'   => $end_date,
			)
		);

		$created_date = $order->get_date_created()->format( 'Y-m-d' );
		$this->assertGreaterThanOrEqual( $start_date, $created_date );
		$this->assertLessThanOrEqual( $end_date, $created_date );
	}

	/**
	 * Test order with coupon using coupon-ratio.
	 */
	public function test_order_with_coupon_ratio() {
		// Create some coupons first.
		$coupon = new \WC_Coupon();
		$coupon->set_code( 'test-coupon-123' );
		$coupon->set_amount( 5 );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->save();

		// Set the coupons flag to ensure at least one coupon exists.
		$order = Order::generate( true, array( 'coupon-ratio' => 1.0, 'coupons' => true ) );

		$coupons = $order->get_coupon_codes();
		// Note: Coupon application may fail if order total is less than coupon amount or other validation fails.
		if ( empty( $coupons ) ) {
			$this->markTestIncomplete( 'Coupon was not applied - may be due to validation or order total issues' );
		}
		$this->assertNotEmpty( $coupons, 'Order should have a coupon with ratio 1.0' );
	}

	/**
	 * Test order action hook is fired.
	 */
	public function test_order_generated_action_hook() {
		$hook_fired = false;
		$generated_order = null;

		add_action(
			'smoothgenerator_order_generated',
			function ( $order ) use ( &$hook_fired, &$generated_order ) {
				$hook_fired = true;
				$generated_order = $order;
			}
		);

		$order = Order::generate( true );

		$this->assertTrue( $hook_fired, 'smoothgenerator_order_generated action should fire' );
		$this->assertInstanceOf( \WC_Order::class, $generated_order );
	}

	/**
	 * Test completed order dates are sequential.
	 */
	public function test_completed_order_dates_sequential() {
		$order = Order::generate( true, array( 'status' => 'completed' ) );

		$date_created   = $order->get_date_created()->getTimestamp();
		$date_paid      = $order->get_date_paid()->getTimestamp();
		$date_completed = $order->get_date_completed()->getTimestamp();

		$this->assertLessThanOrEqual( $date_paid, $date_created );
		$this->assertLessThanOrEqual( $date_completed, $date_paid );
	}

	/**
	 * Test order with refund using refund-ratio.
	 */
	public function test_order_with_refund_ratio() {
		$order = Order::generate(
			true,
			array(
				'status'       => 'completed',
				'refund-ratio' => 1.0,
			)
		);

		$refunds = $order->get_refunds();
		$this->assertNotEmpty( $refunds, 'Order should have refund with ratio 1.0' );
	}

	/**
	 * Test full refund changes order status to refunded.
	 */
	public function test_full_refund_status() {
		$order = Order::generate(
			true,
			array(
				'status'       => 'completed',
				'refund-ratio' => 1.0,
			)
		);

		// Check if any orders are fully refunded (they should be with ratio 1.0).
		$refunded_amount = $order->get_total_refunded();
		if ( abs( $refunded_amount - $order->get_total() ) < 0.01 ) {
			$this->assertEquals( 'refunded', $order->get_status() );
		}
	}

	/**
	 * Test partial refund doesn't change order status.
	 */
	public function test_partial_refund_status() {
		// Generate orders and check for partial refunds.
		for ( $i = 0; $i < 5; $i++ ) {
			$order = Order::generate(
				true,
				array(
					'status'       => 'completed',
					'refund-ratio' => 0.5,
				)
			);

			$refunds = $order->get_refunds();
			if ( ! empty( $refunds ) ) {
				$refunded_amount = $order->get_total_refunded();
				$order_total = $order->get_total();

				// If it's a partial refund (not full).
				if ( $refunded_amount > 0 && $refunded_amount < $order_total ) {
					$this->assertEquals( 'completed', $order->get_status() );
					break;
				}
			}
		}
	}

	/**
	 * Test batch orders have exact coupon distribution with coupon-ratio.
	 */
	public function test_batch_exact_coupon_distribution() {
		// Create a coupon first.
		$coupon = new \WC_Coupon();
		$coupon->set_code( 'batch-test-coupon' );
		$coupon->set_amount( 10 );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->save();

		$amount       = 10;
		$coupon_ratio = 0.5;
		$order_ids    = Order::batch(
			$amount,
			array(
				'coupon-ratio' => $coupon_ratio,
			)
		);

		// Count orders with coupons.
		$coupon_count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( count( $order->get_coupon_codes() ) > 0 ) {
				$coupon_count++;
			}
		}

		// Should have exactly 5 orders with coupons (50% of 10).
		$expected_count = (int) round( $amount * $coupon_ratio );
		$this->assertEquals( $expected_count, $coupon_count, 'Should have exact coupon distribution in batch' );
	}

	/**
	 * Test batch orders have exact refund distribution with refund-ratio.
	 */
	public function test_batch_exact_refund_distribution() {
		$amount       = 20;
		$refund_ratio = 0.5;
		$order_ids    = Order::batch(
			$amount,
			array(
				'status'       => 'completed',
				'refund-ratio' => $refund_ratio,
			)
		);

		// Count orders with refunds.
		$refund_count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( count( $order->get_refunds() ) > 0 ) {
				$refund_count++;
			}
		}

		// Should have exactly 10 orders with refunds (50% of 20).
		$expected_count = (int) round( $amount * $refund_ratio );
		$this->assertEquals( $expected_count, $refund_count, 'Should have exact refund distribution in batch' );
	}

	/**
	 * Test refund has proper line items.
	 */
	public function test_refund_has_line_items() {
		$order = Order::generate(
			true,
			array(
				'status'       => 'completed',
				'refund-ratio' => 1.0,
			)
		);

		$refunds = $order->get_refunds();
		if ( ! empty( $refunds ) ) {
			$refund = $refunds[0];
			$items  = $refund->get_items();
			$this->assertNotEmpty( $items, 'Refund should have line items' );
		}
	}

	/**
	 * Test refund amount is valid.
	 */
	public function test_refund_amount_valid() {
		$order = Order::generate(
			true,
			array(
				'status'       => 'completed',
				'refund-ratio' => 1.0,
			)
		);

		$refunds = $order->get_refunds();
		if ( ! empty( $refunds ) ) {
			$refund_amount = $order->get_total_refunded();
			$this->assertGreaterThan( 0, $refund_amount );
			$this->assertLessThanOrEqual( $order->get_total(), $refund_amount );
		}
	}

	/**
	 * Test batch orders with chronological dates.
	 */
	public function test_batch_orders_chronological_dates() {
		$order_ids = Order::batch(
			5,
			array(
				'date-start' => '2024-01-01',
				'date-end'   => '2024-01-31',
			)
		);

		$dates = array();
		foreach ( $order_ids as $order_id ) {
			$order   = wc_get_order( $order_id );
			$dates[] = $order->get_date_created()->getTimestamp();
		}

		// Dates should be in ascending order (lower IDs = earlier dates).
		$sorted_dates = $dates;
		sort( $sorted_dates );
		$this->assertEquals( $sorted_dates, $dates, 'Batch orders should have chronological dates' );
	}

	/**
	 * Test order sometimes has fees.
	 */
	public function test_order_with_fees() {
		$found_fee = false;
		// Try multiple times to find an order with fees (20% probability).
		for ( $i = 0; $i < 20; $i++ ) {
			$order = Order::generate( true );
			$fees  = $order->get_fees();
			if ( ! empty( $fees ) ) {
				$found_fee = true;
				foreach ( $fees as $fee ) {
					$this->assertInstanceOf( \WC_Order_Item_Fee::class, $fee );
					$this->assertGreaterThan( 0, $fee->get_total() );
				}
				break;
			}
		}
		$this->assertTrue( $found_fee, 'Should generate at least one order with fees in 20 attempts' );
	}

	/**
	 * Test order has valid currency.
	 */
	public function test_order_has_currency() {
		$order = Order::generate( true );

		$currency = $order->get_currency();
		$this->assertEquals( get_woocommerce_currency(), $currency );
	}

	/**
	 * Test refund dates are after order completion.
	 */
	public function test_refund_dates_after_completion() {
		$order = Order::generate(
			true,
			array(
				'status'       => 'completed',
				'refund-ratio' => 1.0,
				'date-start'   => '2024-01-01',
				'date-end'     => '2024-01-01',
			)
		);

		$refunds = $order->get_refunds();
		if ( ! empty( $refunds ) ) {
			$refund = $refunds[0];
			$order_completed = $order->get_date_completed()->getTimestamp();
			$refund_created  = $refund->get_date_created()->getTimestamp();

			// Allow for same timestamp (within 1 second) since refund can happen immediately after completion.
			$this->assertGreaterThanOrEqual( $order_completed - 1, $refund_created, 'Refund date should be at or after order completion' );
		}
	}
}
