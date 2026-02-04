# WC Smooth Generator - Test Suite

Comprehensive unit test suite for the WooCommerce Smooth Generator plugin, targeting 90% code coverage.

## Setup

### Prerequisites

1. Install PHP 7.4 or higher
2. Install Composer
3. Install WordPress test library
4. Install WooCommerce

### Installing WordPress Test Library

Run the WordPress test installation script:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Replace the arguments as needed:
- `wordpress_test` - Database name for tests
- `root` - Database username
- Empty string - Database password (or your password)
- `localhost` - Database host
- `latest` - WordPress version to install (or specific version like `6.4`)

### Environment Variables

Set these environment variables before running tests:

```bash
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
export WP_CORE_DIR=/path/to/wordpress
export WC_DIR=/path/to/woocommerce
```

Or create them temporarily:

```bash
WP_TESTS_DIR=/tmp/wordpress-tests-lib \
WP_CORE_DIR=/tmp/wordpress \
WC_DIR=/path/to/woocommerce \
vendor/bin/phpunit
```

## Running Tests

### Run All Tests

```bash
composer test-unit
```

Or directly:

```bash
vendor/bin/phpunit
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/Generator/ProductTest.php
```

### Run Specific Test

```bash
vendor/bin/phpunit --filter test_generate_simple_product
```

### Run with Coverage Report

```bash
vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

### Run Tests by Group

Tests can be organized with `@group` annotations:

```bash
vendor/bin/phpunit --group generator
vendor/bin/phpunit --group slow --exclude-group slow
```

## Test Structure

```
tests/
├── bootstrap.php              # Test environment setup
├── README.md                  # This file
└── Unit/
    ├── Generator/             # Generator class tests
    │   ├── GeneratorTest.php  # Base generator tests
    │   ├── ProductTest.php    # Product generation tests
    │   ├── OrderTest.php      # Order generation tests
    │   ├── CustomerTest.php   # Customer generation tests
    │   └── CouponTest.php     # Coupon generation tests
    ├── Util/                  # Utility class tests
    │   └── RandomRuntimeCacheTest.php
    └── PluginTest.php         # Main plugin tests
```

## Test Coverage Goals

- **Overall**: ~90% code coverage
- **Generator Classes**: 85-95% coverage (core functionality)
- **Utility Classes**: 95%+ coverage (simpler logic)
- **Admin Classes**: 70-80% coverage (UI-heavy)

## Writing Tests

### Test File Template

```php
<?php
namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\YourClass;
use WP_UnitTestCase;

class YourClassTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Setup code
    }

    public function tearDown(): void {
        // Cleanup code
        parent::tearDown();
    }

    public function test_your_functionality() {
        // Arrange
        $expected = 'value';

        // Act
        $result = YourClass::method();

        // Assert
        $this->assertEquals( $expected, $result );
    }
}
```

### Best Practices

1. **Use descriptive test names**: `test_generate_simple_product_with_sale_price()`
2. **Follow AAA pattern**: Arrange, Act, Assert
3. **One assertion per test** (when practical)
4. **Clean up after tests**: Use `tearDown()` to reset state
5. **Use data providers** for parameterized tests
6. **Mock external dependencies** when appropriate

### Available Assertions

PHPUnit provides many assertions. Common ones:

- `$this->assertEquals( $expected, $actual )`
- `$this->assertTrue( $condition )`
- `$this->assertInstanceOf( ClassName::class, $object )`
- `$this->assertNotEmpty( $value )`
- `$this->assertWPError( $result )`
- `$this->assertGreaterThan( $threshold, $value )`

See: https://docs.phpunit.de/en/9.6/assertions.html

## Continuous Integration

Tests should be run on:
- Every pull request
- Before merging to main branch
- Nightly builds

## Troubleshooting

### "Class not found" errors

Regenerate the autoloader:

```bash
composer dump-autoload
```

### Database errors

Ensure your test database is created and accessible:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
```

### "Call to undefined function" for WC functions

Ensure WooCommerce path is set correctly:

```bash
export WC_DIR=/path/to/woocommerce
```

### Permission errors on temp directories

```bash
chmod -R 777 /tmp/wordpress-tests-lib
```

## Resources

- [PHPUnit Documentation](https://docs.phpunit.de/)
- [WordPress Plugin Handbook - Unit Tests](https://developer.wordpress.org/plugins/testing/automated-testing/)
- [WooCommerce Testing Guide](https://github.com/woocommerce/woocommerce/wiki/How-to-set-up-WooCommerce-development-environment)
