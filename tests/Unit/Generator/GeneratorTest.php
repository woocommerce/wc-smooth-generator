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
		$this->assertEquals( 100, Product::MAX_BATCH_SIZE );
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
		$result = Product::batch( Product::MAX_BATCH_SIZE );

		$this->assertIsArray( $result );
	}

	/**
	 * Test emails are disabled during generation.
	 */
	public function test_emails_disabled() {
		// Generate a product to trigger initialization.
		Product::generate( true );

		// Check that the filter that blocks emails is in place.
		$has_block_filter = has_filter( 'woocommerce_allow_send_queued_transactional_email', '__return_false' );
		$this->assertNotFalse( $has_block_filter, 'Email blocking filter should be in place' );
	}

	/**
	 * Test queued transactional emails are blocked.
	 */
	public function test_queued_emails_blocked() {
		// Generate a product to trigger initialization.
		Product::generate( true );

		// Check that the filter is hooked.
		$has_block_filter = has_filter( 'woocommerce_allow_send_queued_transactional_email', '__return_false' );
		$this->assertNotFalse( $has_block_filter, 'Email blocking filter should be hooked' );

		// Verify the filter actually blocks when called.
		$result = apply_filters( 'woocommerce_allow_send_queued_transactional_email', true, null, null );
		$this->assertFalse( $result, 'Queued transactional emails should be blocked' );
	}
}
