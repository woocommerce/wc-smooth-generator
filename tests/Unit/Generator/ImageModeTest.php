<?php
/**
 * Tests for image mode functionality.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use ReflectionClass;
use WC\SmoothGenerator\Generator\Product;
use WP_UnitTestCase;

/**
 * Image mode test case.
 */
class ImageModeTest extends WP_UnitTestCase {

	/**
	 * Reset image state before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_images();
	}

	/**
	 * Clean up image state after each test.
	 */
	public function tear_down() {
		$this->reset_images();
		parent::tear_down();
	}

	/**
	 * Reset the static images array.
	 */
	protected function reset_images() {
		$reflection = new ReflectionClass( Product::class );
		$property   = $reflection->getProperty( 'images' );
		$property->setAccessible( true );
		$property->setValue( null, array() );
	}

	/**
	 * Get the static images array for assertions.
	 *
	 * @return array
	 */
	protected function get_images() {
		$reflection = new ReflectionClass( Product::class );
		$property   = $reflection->getProperty( 'images' );
		$property->setAccessible( true );
		return $property->getValue();
	}

	/**
	 * Call protected get_image() method.
	 *
	 * @return int
	 */
	protected function call_get_image() {
		$reflection = new ReflectionClass( Product::class );
		$method     = $reflection->getMethod( 'get_image' );
		$method->setAccessible( true );
		return $method->invoke( null );
	}

	/**
	 * Test seed_images with none mode returns empty array.
	 */
	public function test_seed_images_none_returns_empty() {
		Product::seed_images( 10, 'none' );
		$this->assertEmpty( $this->get_images() );
	}

	/**
	 * Test get_image returns 0 when no images available.
	 */
	public function test_get_image_returns_zero_when_empty() {
		$this->reset_images();
		$this->assertEquals( 0, $this->call_get_image() );
	}

	/**
	 * Test seed_images with abstract mode generates Jdenticon images.
	 */
	public function test_seed_images_abstract_generates_images() {
		Product::seed_images( 5, 'abstract' );
		$images = $this->get_images();

		$this->assertCount( 5, $images );

		foreach ( $images as $id ) {
			$this->assertGreaterThan( 0, $id, 'Each image should have a valid attachment ID' );
			$this->assertEquals( 'attachment', get_post_type( $id ) );
		}
	}

	/**
	 * Test seed_images with existing mode queries media library.
	 */
	public function test_seed_images_existing_queries_media_library() {
		// Create 3 test attachments.
		$attachment_ids = $this->factory->attachment->create_many( 3, array(
			'post_parent' => 0,
		) );

		Product::seed_images( 5, 'existing' );
		$images = $this->get_images();

		// Should have 3 existing + 2 generated = 5.
		$this->assertCount( 5, $images );

		// Verify the 3 existing attachments are included.
		foreach ( $attachment_ids as $id ) {
			$this->assertContains( $id, $images, 'Existing attachment should be in the image pool' );
		}
	}

	/**
	 * Test seed_images with existing mode fills gaps when not enough media library images.
	 */
	public function test_seed_images_existing_fills_gaps() {
		// Create only 2 test attachments.
		$this->factory->attachment->create_many( 2, array(
			'post_parent' => 0,
		) );

		Product::seed_images( 5, 'existing' );
		$images = $this->get_images();

		// Should have 2 existing + 3 generated = 5.
		$this->assertCount( 5, $images );
	}

	/**
	 * Test get_image_count returns correct count.
	 */
	public function test_get_image_count_returns_correct_count() {
		Product::seed_images( 7, 'abstract' );
		$this->assertEquals( 7, Product::get_image_count() );
	}

	/**
	 * Test get_image_count returns 0 for none mode.
	 */
	public function test_get_image_count_zero_for_none() {
		Product::seed_images( 10, 'none' );
		$this->assertEquals( 0, Product::get_image_count() );
	}

	/**
	 * Test seed_images default mode is existing.
	 */
	public function test_seed_images_default_mode_is_existing() {
		// Create 2 test attachments.
		$this->factory->attachment->create_many( 2, array(
			'post_parent' => 0,
		) );

		// Call without mode parameter.
		Product::seed_images( 4 );
		$images = $this->get_images();

		// Should have 2 existing + 2 generated = 4.
		$this->assertCount( 4, $images );
	}

	/**
	 * Test get_image returns valid attachment ID when images are seeded.
	 */
	public function test_get_image_returns_valid_id_when_seeded() {
		Product::seed_images( 3, 'abstract' );

		$image_id = $this->call_get_image();
		$this->assertGreaterThan( 0, $image_id );
		$this->assertEquals( 'attachment', get_post_type( $image_id ) );
	}

	/**
	 * Test realistic mode caps at 100 images.
	 */
	public function test_realistic_mode_caps_at_100() {
		// Stub HTTP layer to avoid live network calls.
		add_filter( 'pre_http_request', '__return_true', 99 );
		add_filter( 'pre_media_handle_sideload', function () {
			return $this->factory->attachment->create( array( 'post_parent' => 0 ) );
		} );

		// Request 150, should cap at 100.
		Product::seed_images( 150, 'realistic' );
		$this->assertLessThanOrEqual( 100, Product::get_image_count() );

		// Clean up.
		remove_filter( 'pre_http_request', '__return_true', 99 );
	}
}
