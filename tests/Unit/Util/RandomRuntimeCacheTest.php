<?php
/**
 * Tests for RandomRuntimeCache utility class.
 *
 * @package WC\SmoothGenerator\Tests\Util
 */

namespace WC\SmoothGenerator\Tests\Util;

use WC\SmoothGenerator\Util\RandomRuntimeCache;
use WP_UnitTestCase;

/**
 * RandomRuntimeCache test case.
 */
class RandomRuntimeCacheTest extends WP_UnitTestCase {

	/**
	 * Reset the cache before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		RandomRuntimeCache::reset();
	}

	/**
	 * Reset the cache after each test.
	 */
	public function tearDown(): void {
		RandomRuntimeCache::reset();
		parent::tearDown();
	}

	/**
	 * Test that exists returns false for non-existent group.
	 */
	public function test_exists_returns_false_for_non_existent_group() {
		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test that exists returns true after setting a group.
	 */
	public function test_exists_returns_true_after_set() {
		RandomRuntimeCache::set( 'test_group', array( 1, 2, 3 ) );
		$this->assertTrue( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test setting and getting items.
	 */
	public function test_set_and_get_items() {
		$items = array( 1, 2, 3, 4, 5 );
		RandomRuntimeCache::set( 'test_group', $items );

		$result = RandomRuntimeCache::get( 'test_group' );

		$this->assertEquals( $items, $result );
	}

	/**
	 * Test getting items with limit.
	 */
	public function test_get_with_limit() {
		$items = array( 1, 2, 3, 4, 5 );
		RandomRuntimeCache::set( 'test_group', $items );

		$result = RandomRuntimeCache::get( 'test_group', 3 );

		$this->assertCount( 3, $result );
		$this->assertEquals( array( 1, 2, 3 ), $result );
	}

	/**
	 * Test getting items with limit larger than available.
	 */
	public function test_get_with_limit_larger_than_available() {
		$items = array( 1, 2, 3 );
		RandomRuntimeCache::set( 'test_group', $items );

		$result = RandomRuntimeCache::get( 'test_group', 10 );

		$this->assertCount( 3, $result );
		$this->assertEquals( $items, $result );
	}

	/**
	 * Test getting items with zero limit returns all items.
	 */
	public function test_get_with_zero_limit_returns_all() {
		$items = array( 1, 2, 3, 4, 5 );
		RandomRuntimeCache::set( 'test_group', $items );

		$result = RandomRuntimeCache::get( 'test_group', 0 );

		$this->assertEquals( $items, $result );
	}

	/**
	 * Test extracting items removes them from cache.
	 */
	public function test_extract_removes_items_from_cache() {
		$items = array( 1, 2, 3, 4, 5 );
		RandomRuntimeCache::set( 'test_group', $items );

		$extracted = RandomRuntimeCache::extract( 'test_group', 3 );

		$this->assertEquals( array( 1, 2, 3 ), $extracted );

		$remaining = RandomRuntimeCache::get( 'test_group' );
		$this->assertEquals( array( 4, 5 ), $remaining );
	}

	/**
	 * Test extracting all items deletes the group.
	 */
	public function test_extract_all_deletes_group() {
		$items = array( 1, 2, 3 );
		RandomRuntimeCache::set( 'test_group', $items );

		$extracted = RandomRuntimeCache::extract( 'test_group', 0 );

		$this->assertEquals( $items, $extracted );
		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test extracting with limit larger than available.
	 */
	public function test_extract_with_limit_larger_than_available() {
		$items = array( 1, 2, 3 );
		RandomRuntimeCache::set( 'test_group', $items );

		$extracted = RandomRuntimeCache::extract( 'test_group', 10 );

		$this->assertEquals( $items, $extracted );
		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test adding items to existing group.
	 */
	public function test_add_items_to_existing_group() {
		RandomRuntimeCache::set( 'test_group', array( 1, 2, 3 ) );
		RandomRuntimeCache::add( 'test_group', array( 4, 5 ) );

		$result = RandomRuntimeCache::get( 'test_group' );

		$this->assertEquals( array( 1, 2, 3, 4, 5 ), $result );
	}

	/**
	 * Test adding items to non-existent group creates it.
	 */
	public function test_add_items_to_non_existent_group() {
		RandomRuntimeCache::add( 'test_group', array( 1, 2, 3 ) );

		$this->assertTrue( RandomRuntimeCache::exists( 'test_group' ) );
		$this->assertEquals( array( 1, 2, 3 ), RandomRuntimeCache::get( 'test_group' ) );
	}

	/**
	 * Test counting items in group.
	 */
	public function test_count_items() {
		RandomRuntimeCache::set( 'test_group', array( 1, 2, 3, 4, 5 ) );

		$count = RandomRuntimeCache::count( 'test_group' );

		$this->assertEquals( 5, $count );
	}

	/**
	 * Test counting non-existent group returns zero.
	 */
	public function test_count_non_existent_group() {
		$count = RandomRuntimeCache::count( 'test_group' );

		$this->assertEquals( 0, $count );
	}

	/**
	 * Test shuffle randomizes items order.
	 */
	public function test_shuffle_randomizes_order() {
		$items = range( 1, 100 );
		RandomRuntimeCache::set( 'test_group', $items );

		RandomRuntimeCache::shuffle( 'test_group' );

		$result = RandomRuntimeCache::get( 'test_group' );

		// Items should be same but likely in different order.
		$this->assertEquals( 100, count( $result ) );
		$this->assertEquals( array_sum( $items ), array_sum( $result ) );
		// Very unlikely to be in same order after shuffle (probability: 1/100!).
		$is_shuffled = false;
		for ( $i = 0; $i < count( $items ); $i++ ) {
			if ( $items[ $i ] !== $result[ $i ] ) {
				$is_shuffled = true;
				break;
			}
		}
		$this->assertTrue( $is_shuffled, 'Items should be shuffled' );
	}

	/**
	 * Test shuffle on non-existent group creates it.
	 */
	public function test_shuffle_non_existent_group() {
		RandomRuntimeCache::shuffle( 'test_group' );

		$this->assertTrue( RandomRuntimeCache::exists( 'test_group' ) );
		$this->assertEquals( array(), RandomRuntimeCache::get( 'test_group' ) );
	}

	/**
	 * Test clearing a group removes it.
	 */
	public function test_clear_removes_group() {
		RandomRuntimeCache::set( 'test_group', array( 1, 2, 3 ) );
		RandomRuntimeCache::clear( 'test_group' );

		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test clearing non-existent group doesn't error.
	 */
	public function test_clear_non_existent_group() {
		RandomRuntimeCache::clear( 'test_group' );

		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}

	/**
	 * Test reset clears all groups.
	 */
	public function test_reset_clears_all_groups() {
		RandomRuntimeCache::set( 'group1', array( 1, 2, 3 ) );
		RandomRuntimeCache::set( 'group2', array( 4, 5, 6 ) );
		RandomRuntimeCache::set( 'group3', array( 7, 8, 9 ) );

		RandomRuntimeCache::reset();

		$this->assertFalse( RandomRuntimeCache::exists( 'group1' ) );
		$this->assertFalse( RandomRuntimeCache::exists( 'group2' ) );
		$this->assertFalse( RandomRuntimeCache::exists( 'group3' ) );
	}

	/**
	 * Test multiple operations in sequence.
	 */
	public function test_complex_operations_sequence() {
		// Set initial items.
		RandomRuntimeCache::set( 'test_group', array( 1, 2, 3, 4, 5 ) );
		$this->assertEquals( 5, RandomRuntimeCache::count( 'test_group' ) );

		// Extract some items.
		$extracted = RandomRuntimeCache::extract( 'test_group', 2 );
		$this->assertEquals( array( 1, 2 ), $extracted );
		$this->assertEquals( 3, RandomRuntimeCache::count( 'test_group' ) );

		// Add more items.
		RandomRuntimeCache::add( 'test_group', array( 6, 7 ) );
		$this->assertEquals( 5, RandomRuntimeCache::count( 'test_group' ) );

		// Get with limit.
		$result = RandomRuntimeCache::get( 'test_group', 3 );
		$this->assertCount( 3, $result );

		// Clear group.
		RandomRuntimeCache::clear( 'test_group' );
		$this->assertFalse( RandomRuntimeCache::exists( 'test_group' ) );
	}
}
