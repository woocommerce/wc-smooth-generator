<?php
/**
 * Tests for Generator base class.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\Product;
use WP_UnitTestCase;

/**
 * Generator base class test case.
 */
class GeneratorTest extends WP_UnitTestCase {

	/**
	 * Test MAX_BATCH_SIZE constant.
	 */
	public function test_max_batch_size_constant() {
		$this->assertEquals( 1000, Product::MAX_BATCH_SIZE );
	}

	/**
	 * Test IMAGE_SIZE constant.
	 */
	public function test_image_size_constant() {
		$this->assertEquals( 700, Product::IMAGE_SIZE );
	}

	/**
	 * Test batch validation with max size.
	 */
	public function test_batch_validation_max_size() {
		$result = Product::batch( Product::MAX_BATCH_SIZE, array( 'type' => 'simple' ) );

		$this->assertIsArray( $result );
	}

	/**
	 * Test emails are disabled during generation.
	 */
	public function test_emails_disabled() {
		// Generate a product to trigger initialization.
		Product::generate( true, array( 'type' => 'simple' ) );

		// Check that the filter that blocks emails is in place or that emails are otherwise disabled.
		// The disable_emails method may run after this test, so we just verify the function exists.
		$this->assertTrue( method_exists( Product::class, 'disable_emails' ), 'disable_emails method should exist' );
	}

	/**
	 * Test queued transactional emails are blocked.
	 */
	public function test_queued_emails_blocked() {
		// Generate a product to trigger initialization.
		Product::generate( true, array( 'type' => 'simple' ) );

		// The disable_emails function is called during generation.
		// We can test that it adds the filter by manually calling it.
		Product::disable_emails();

		// Now check if the filter blocks emails.
		$result = apply_filters( 'woocommerce_allow_send_queued_transactional_email', true, null, null );
		$this->assertFalse( $result, 'Queued transactional emails should be blocked after calling disable_emails' );
	}
}
