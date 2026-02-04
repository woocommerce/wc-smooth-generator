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

		// Check that email hooks have been removed.
		$has_email_hook = has_action( 'woocommerce_order_status_pending_to_processing', array( 'WC_Emails', 'send_transactional_email' ) );
		$this->assertFalse( $has_email_hook, 'Email hooks should be removed' );
	}

	/**
	 * Test queued transactional emails are blocked.
	 */
	public function test_queued_emails_blocked() {
		// Generate a product to trigger initialization.
		Product::generate( true );

		// Check that the filter returns false.
		$result = apply_filters( 'woocommerce_allow_send_queued_transactional_email', true );
		$this->assertFalse( $result, 'Queued transactional emails should be blocked' );
	}
}
