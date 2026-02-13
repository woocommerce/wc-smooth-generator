<?php
/**
 * Tests for Plugin main class.
 *
 * @package WC\SmoothGenerator\Tests
 */

namespace WC\SmoothGenerator\Tests;

use WC\SmoothGenerator\Plugin;
use WP_UnitTestCase;

/**
 * Plugin test case.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * Test plugin can be instantiated.
	 */
	public function test_plugin_instantiation() {
		$plugin = new Plugin( __FILE__ );

		$this->assertInstanceOf( Plugin::class, $plugin );
	}
}
