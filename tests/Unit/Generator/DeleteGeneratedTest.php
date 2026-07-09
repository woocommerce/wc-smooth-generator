<?php
/**
 * Tests for bulk-deleting generated objects.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\Booking;
use WC\SmoothGenerator\Generator\Coupon;
use WC\SmoothGenerator\Generator\Customer;
use WC\SmoothGenerator\Generator\Generator;
use WC\SmoothGenerator\Generator\Order;
use WC\SmoothGenerator\Generator\Product;
use WC\SmoothGenerator\Generator\Term;
use WC\SmoothGenerator\Router;
use WP_UnitTestCase;

/**
 * Delete-generated-objects test case.
 *
 * Counts are asserted relative to a baseline taken at the start of each test, since
 * WooCommerce's custom tables (e.g. HPOS orders) aren't guaranteed to roll back between
 * test methods the same way core wp_posts/wp_users do.
 */
class DeleteGeneratedTest extends WP_UnitTestCase {

	/**
	 * Test the generated marker is set on a product.
	 */
	public function test_product_has_generated_marker() {
		$product = Product::generate( true, array( 'type' => 'simple' ) );

		$this->assertEquals( '1', $product->get_meta( Generator::GENERATED_META_KEY ) );
	}

	/**
	 * Test delete_batch removes only generated products, leaving a real product untouched.
	 */
	public function test_product_delete_batch_only_removes_generated() {
		$baseline = Product::count_generated();

		Product::batch( 3, array( 'type' => 'simple' ) );

		$real_product = new \WC_Product_Simple();
		$real_product->set_name( 'Real product' );
		$real_product->save();

		$deleted = Product::delete_batch( $baseline + 10 );

		$this->assertSame( $baseline + 3, $deleted );
		$this->assertNotFalse( wc_get_product( $real_product->get_id() ), 'Real product should survive delete_batch()' );
		$this->assertSame( 0, Product::count_generated() );
	}

	/**
	 * Test the generated marker is set on an order.
	 */
	public function test_order_has_generated_marker() {
		Product::batch( 2, array( 'type' => 'simple' ) );
		$order = Order::generate( true );

		$this->assertNotFalse( $order );
		$this->assertEquals( '1', $order->get_meta( Generator::GENERATED_META_KEY ) );
	}

	/**
	 * Test delete_batch removes only generated orders, leaving a real order untouched.
	 */
	public function test_order_delete_batch_only_removes_generated() {
		$baseline = Order::count_generated();

		Product::batch( 2, array( 'type' => 'simple' ) );
		Order::batch( 2 );

		$real_order = new \WC_Order();
		$real_order->save();

		$deleted = Order::delete_batch( $baseline + 10 );

		$this->assertSame( $baseline + 2, $deleted );
		$this->assertNotFalse( wc_get_order( $real_order->get_id() ), 'Real order should survive delete_batch()' );
		$this->assertSame( 0, Order::count_generated() );
	}

	/**
	 * Test the generated marker is set on a customer.
	 */
	public function test_customer_has_generated_marker() {
		$customer = Customer::generate( true );

		$this->assertEquals( '1', $customer->get_meta( Generator::GENERATED_META_KEY ) );
	}

	/**
	 * Test delete_batch removes only generated customers, leaving a real customer untouched.
	 */
	public function test_customer_delete_batch_only_removes_generated() {
		$baseline = Customer::count_generated();

		Customer::batch( 2 );

		$real_customer_id = wp_insert_user( array(
			'user_login' => 'real-customer',
			'user_email' => 'real-customer@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => 'customer',
		) );
		$this->assertIsInt( $real_customer_id );

		$deleted = Customer::delete_batch( $baseline + 10 );

		$this->assertSame( $baseline + 2, $deleted );
		$this->assertNotFalse( get_user_by( 'id', $real_customer_id ), 'Real customer should survive delete_batch()' );
		$this->assertSame( 0, Customer::count_generated() );
	}

	/**
	 * Test the generated marker is set on a coupon.
	 */
	public function test_coupon_has_generated_marker() {
		$coupon = Coupon::generate( true );

		$this->assertEquals( '1', $coupon->get_meta( Generator::GENERATED_META_KEY ) );
	}

	/**
	 * Test delete_batch removes only generated coupons, leaving a real coupon untouched.
	 */
	public function test_coupon_delete_batch_only_removes_generated() {
		$baseline = Coupon::count_generated();

		Coupon::batch( 2 );

		$real_coupon = new \WC_Coupon( 'real-coupon' );
		$real_coupon->set_amount( 5 );
		$real_coupon->save();

		$deleted = Coupon::delete_batch( $baseline + 10 );

		$this->assertSame( $baseline + 2, $deleted );
		$this->assertTrue( ( new \WC_Coupon( $real_coupon->get_id() ) )->get_id() > 0, 'Real coupon should survive delete_batch()' );
		$this->assertSame( 0, Coupon::count_generated() );
	}

	/**
	 * Test the generated marker is set on a term.
	 */
	public function test_term_has_generated_marker() {
		$term = Term::generate( true, 'product_cat' );

		$this->assertEquals( '1', get_term_meta( $term->term_id, Generator::GENERATED_META_KEY, true ) );
	}

	/**
	 * Test delete_batch removes only generated terms, leaving a real term untouched.
	 */
	public function test_term_delete_batch_only_removes_generated() {
		$baseline = Term::count_generated( 'product_cat' );

		Term::batch( 2, 'product_cat' );

		$real_term = wp_insert_term( 'Real Category', 'product_cat' );
		$this->assertIsArray( $real_term );

		$deleted = Term::delete_batch( $baseline + 10, 'product_cat' );

		$this->assertSame( $baseline + 2, $deleted );
		$this->assertNotFalse( get_term( $real_term['term_id'], 'product_cat' ), 'Real term should survive delete_batch()' );
		$this->assertSame( 0, Term::count_generated( 'product_cat' ) );
	}

	/**
	 * Test delete_batch returns WP_Error for an invalid taxonomy.
	 */
	public function test_term_delete_batch_invalid_taxonomy() {
		$result = Term::delete_batch( 10, 'not_a_real_taxonomy' );

		$this->assertWPError( $result );
	}

	/**
	 * Test delete_batch drains across multiple chunked calls.
	 */
	public function test_product_delete_batch_across_multiple_calls() {
		$baseline = Product::count_generated();

		Product::batch( 5, array( 'type' => 'simple' ) );

		$deleted  = 0;
		$deleted += Product::delete_batch( 2 );
		$deleted += Product::delete_batch( 2 );
		$deleted += Product::delete_batch( $baseline + 2 );

		$this->assertSame( $baseline + 5, $deleted );
		$this->assertSame( 0, Product::delete_batch( 2 ), 'A further call once drained should delete nothing' );
	}

	/**
	 * Test bookings, if the WooCommerce Bookings extension is active, get a generated marker
	 * and their delete_batch() also removes their linked order.
	 */
	public function test_booking_delete_batch_cascades_to_order() {
		if ( ! Booking::is_bookings_active() ) {
			$this->markTestSkipped( 'WooCommerce Bookings is not active in this test environment.' );
		}

		Product::batch( 1, array( 'type' => 'booking' ) );
		$booking_id = Booking::generate( true, array( 'with-orders' => true ) );

		$this->assertIsInt( $booking_id );

		$order_id = get_post_meta( $booking_id, '_smoothgenerator_booking_order_id', true );
		$this->assertNotEmpty( $order_id, 'Booking should be linked to a generated order' );

		$deleted = Booking::delete_batch( 10 );

		$this->assertGreaterThanOrEqual( 1, $deleted );
		$this->assertFalse( wc_get_order( $order_id ), 'Linked order should be deleted along with the booking' );
	}

	/**
	 * Test Router::delete_batch() dispatches to the correct generator.
	 */
	public function test_router_delete_batch_dispatches_to_generator() {
		$baseline = Coupon::count_generated();

		Coupon::batch( 2 );

		$deleted = Router::delete_batch( 'coupons', $baseline + 10 );

		$this->assertSame( $baseline + 2, $deleted );
	}

	/**
	 * Test Router::delete_batch() returns WP_Error for an unknown generator slug.
	 */
	public function test_router_delete_batch_invalid_slug() {
		$result = Router::delete_batch( 'not-a-real-type', 10 );

		$this->assertWPError( $result );
	}
}
