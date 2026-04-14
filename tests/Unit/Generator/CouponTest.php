<?php
/**
 * Tests for Coupon Generator.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\Coupon;
use WP_UnitTestCase;

/**
 * Coupon Generator test case.
 */
class CouponTest extends WP_UnitTestCase {

	/**
	 * Reset the coupon ID cache before each test so DB roll-backs don't leave stale IDs.
	 */
	public function setUp(): void {
		parent::setUp();
		Coupon::invalidate_cache();
	}

	/**
	 * Test generating a coupon.
	 */
	public function test_generate_coupon() {
		$coupon = Coupon::generate( true );

		$this->assertInstanceOf( \WC_Coupon::class, $coupon );
		$this->assertTrue( $coupon->get_id() > 0 );
		$this->assertNotEmpty( $coupon->get_code() );
	}

	/**
	 * Test coupon has amount.
	 */
	public function test_coupon_has_amount() {
		$coupon = Coupon::generate( true );

		$amount = $coupon->get_amount();
		$this->assertGreaterThan( 0, $amount );
	}

	/**
	 * Test coupon with custom min and max.
	 */
	public function test_coupon_custom_min_max() {
		$coupon = Coupon::generate(
			true,
			array(
				'min' => 10,
				'max' => 20,
			)
		);

		$amount = $coupon->get_amount();
		$this->assertGreaterThanOrEqual( 10, $amount );
		$this->assertLessThanOrEqual( 20, $amount );
	}

	/**
	 * Test coupon with fixed_cart discount type.
	 */
	public function test_coupon_fixed_cart_type() {
		$coupon = Coupon::generate(
			true,
			array(
				'discount_type' => 'fixed_cart',
			)
		);

		$this->assertEquals( 'fixed_cart', $coupon->get_discount_type() );
	}

	/**
	 * Test coupon with percent discount type.
	 */
	public function test_coupon_percent_type() {
		$coupon = Coupon::generate(
			true,
			array(
				'discount_type' => 'percent',
			)
		);

		$this->assertEquals( 'percent', $coupon->get_discount_type() );
	}

	/**
	 * Test batch coupon generation.
	 */
	public function test_batch_generation() {
		$amount     = 5;
		$coupon_ids = Coupon::batch( $amount );

		$this->assertIsArray( $coupon_ids );
		$this->assertCount( $amount, $coupon_ids );

		foreach ( $coupon_ids as $coupon_id ) {
			$coupon = new \WC_Coupon( $coupon_id );
			$this->assertTrue( $coupon->get_id() > 0 );
		}
	}

	/**
	 * Test batch validation.
	 */
	public function test_batch_validation() {
		$result = Coupon::batch( 0 );

		$this->assertWPError( $result );
	}

	/**
	 * Test invalid min value returns error.
	 */
	public function test_invalid_min_value() {
		$coupon = Coupon::generate(
			true,
			array(
				'min' => -5,
			)
		);

		$this->assertWPError( $coupon );
	}

	/**
	 * Test invalid max value returns error.
	 */
	public function test_invalid_max_value() {
		$coupon = Coupon::generate(
			true,
			array(
				'max' => 0,
			)
		);

		$this->assertWPError( $coupon );
	}

	/**
	 * Test min greater than max returns error.
	 */
	public function test_min_greater_than_max() {
		$coupon = Coupon::generate(
			true,
			array(
				'min' => 50,
				'max' => 10,
			)
		);

		$this->assertWPError( $coupon );
	}

	/**
	 * Test invalid discount type returns error.
	 */
	public function test_invalid_discount_type() {
		$coupon = Coupon::generate(
			true,
			array(
				'discount_type' => 'invalid_type',
			)
		);

		$this->assertWPError( $coupon );
	}

	/**
	 * Test get_random returns false when no coupons exist.
	 */
	public function test_get_random_no_coupons() {
		$coupon = Coupon::get_random();

		$this->assertFalse( $coupon );
	}

	/**
	 * Test get_random returns coupon when coupons exist.
	 */
	public function test_get_random_with_coupons() {
		// Create some coupons.
		Coupon::batch( 3 );

		$coupon = Coupon::get_random();

		$this->assertInstanceOf( \WC_Coupon::class, $coupon );
		$this->assertTrue( $coupon->get_id() > 0 );
	}

	/**
	 * Test coupon action hook is fired.
	 */
	public function test_coupon_generated_action_hook() {
		$hook_fired = false;
		$generated_coupon = null;

		add_action(
			'smoothgenerator_coupon_generated',
			function ( $coupon ) use ( &$hook_fired, &$generated_coupon ) {
				$hook_fired = true;
				$generated_coupon = $coupon;
			}
		);

		$coupon = Coupon::generate( true );

		$this->assertTrue( $hook_fired, 'smoothgenerator_coupon_generated action should fire' );
		$this->assertInstanceOf( \WC_Coupon::class, $generated_coupon );
	}

	/**
	 * Test coupon code format.
	 */
	public function test_coupon_code_format() {
		$coupon = Coupon::generate( true );

		$code = $coupon->get_code();
		$this->assertNotEmpty( $code );
		// Code should end with numbers (the amount).
		$this->assertMatchesRegularExpression( '/\d+$/', $code );
	}
}
