# Using Smooth Generator in PHPUnit tests

The generators can be called directly from PHP, not just from WP-CLI. This
makes it easy to seed realistic WooCommerce data (products, orders,
customers, coupons, terms, bookings) inside your own PHPUnit test suite, for
example in `setUp()` before each test.

The bundled test suite at `tests/Unit/Generator/` already uses this approach,
so everything below is exercised by the project's own tests.

## Prerequisites

The generators rely on WooCommerce classes and data stores, so your test
environment must bootstrap both WordPress and WooCommerce first:

- A working WP test environment (see `bin/install-wp-tests.sh`).
- WooCommerce installed and active in that environment.
- The Smooth Generator plugin loaded (as a Composer dependency, a symlinked
  plugin, or an mu-plugin include).

This project's `tests/bootstrap.php` is a good template: it loads the Composer
autoloader, requires `woocommerce.php`, then requires
`wc-smooth-generator.php` on the `muplugins_loaded` hook and runs
`WC_Install::install()` on `init`.

```php
// Mirrors tests/bootstrap.php: WooCommerce + Smooth Generator available.
require_once $wc_dir . '/woocommerce.php';
require $plugin_dir . '/wc-smooth-generator.php';
```

Once the plugin is loaded, no further setup is needed. The first call to a
generator automatically initializes Faker (random data), disables WooCommerce
emails, and sets `$_SERVER['SERVER_NAME']` so order generation works in a
headless context.

## Quick start

```php
use WC\SmoothGenerator\Generator\Product;
use WP_UnitTestCase;

class MyExtensionTest extends WP_UnitTestCase {

    public function test_something_with_a_product() {
        $product = Product::generate( true, array( 'type' => 'simple' ) );

        $this->assertInstanceOf( \WC_Product::class, $product );
        $this->assertTrue( $product->get_id() > 0 );
        $this->assertEquals( 'simple', $product->get_type() );
    }
}
```

## Generator API

Every generator lives in the `WC\SmoothGenerator\Generator` namespace and
exposes two static methods:

- `generate( bool $save, array $args )` returns the created object (for
  example a `\WC_Product`) or a `\WP_Error` on failure.
- `batch( int $amount, array $args )` returns an `int[]` of created object IDs,
  or a `\WP_Error`.

`Router::generate_batch( string $slug, int $amount, array $args )` is a single
dispatch point that maps a slug (`'products'`, `'orders'`, `'customers'`,
`'coupons'`, `'bookings'`) to the right generator and calls its `batch()`
method. It is the easiest entry point when you just want N objects of a type.

### Products

```php
use WC\SmoothGenerator\Generator\Product;

// One product. $args['type'] is 'simple' (default), 'variable',
// 'booking', 'bookable-service', or 'bookable-event'.
$product = Product::generate( true, array( 'type' => 'simple' ) );

// A batch of 10 simple products. Returns int[] of product IDs.
$product_ids = Product::batch( 10, array( 'type' => 'simple' ) );

// Skip automatic term (category/tag/brand) creation and reuse your own.
$product_ids = Product::batch( 10, array(
    'type'               => 'simple',
    'use-existing-terms' => true,
) );
```

### Orders

Orders attach to random published products and customers, so generate those
first.

```php
use WC\SmoothGenerator\Generator\Order;
use WC\SmoothGenerator\Generator\Product;

Product::batch( 5 );

// One order. Returns \WC_Order, or false if no products/customers exist.
$order = Order::generate( true );

// A batch of 8 completed orders, 30% refunded at the exact ratio.
$order_ids = Order::batch( 8, array(
    'status'        => 'completed',
    'refund-ratio'  => 0.3,
) );
```

Common `$args` for orders: `status`, `coupons` (bool), `coupon-ratio`,
`refund-ratio` (0 to 1, applied only to completed orders), and date windows
(`date-start` / `date-end` / `date`).

### Customers

```php
use WC\SmoothGenerator\Generator\Customer;

// One customer. $args['type'] is 'person' (default) or 'company'.
// $args['country'] is a 2-letter ISO code, e.g. 'ES'.
$customer = Customer::generate( true, array( 'type' => 'company' ) );

$customer_ids = Customer::batch( 3, array( 'country' => 'US' ) );
```

### Coupons

```php
use WC\SmoothGenerator\Generator\Coupon;

// $args: min (default 5), max (default 100), discount_type ('fixed_cart'|'percent').
$coupon = Coupon::generate( true, array(
    'min'           => 10,
    'max'           => 50,
    'discount_type' => 'percent',
) );

$coupon_ids = Coupon::batch( 2 );
```

### Bookings

Requires the WooCommerce Bookings extension (and
`WC_BOOKINGS_EXPERIMENTAL_ENABLED` for the `bookable-service` / `bookable-event`
product types). At least one published bookable product must exist.

```php
use WC\SmoothGenerator\Generator\Booking;

// Returns int (the booking ID) when saved, or \WP_Error.
$booking_id = Booking::generate( true, array(
    'product-id'  => 123,   // a bookable product, or omit to pick one at random
    'with-orders' => true,  // also create a linked order
) );

$booking_ids = Booking::batch( 4 );
```

### Terms

`Term` has a different signature from the other generators: the taxonomy is a
positional argument, not part of `$args`. Call it directly rather than through
`Router::generate_batch( 'terms', ... )`.

```php
use WC\SmoothGenerator\Generator\Term;

// One term.
$term = Term::generate( true, 'product_tag' );

// A batch of terms for a taxonomy (positional 2nd argument).
$term_ids = Term::batch( 5, 'product_tag' );
$cat_ids  = Term::batch( 5, 'product_cat' );
```

### Using the Router

```php
use WC\SmoothGenerator\Router;

$product_ids  = Router::generate_batch( 'products', 10, array( 'type' => 'simple' ) );
$order_ids    = Router::generate_batch( 'orders', 5 );
$customer_ids = Router::generate_batch( 'customers', 3 );
$coupon_ids   = Router::generate_batch( 'coupons', 2 );
$booking_ids  = Router::generate_batch( 'bookings', 4 );
```

Each call returns `int[]` of IDs, or a `\WP_Error` (for example if the amount
is out of range). Note `terms` is not routed this way because of its positional
taxonomy argument; use `Term::batch()` directly.

## Handling return values

`generate()` gives you the object, so assert on it directly:

```php
$product = Product::generate( true, array( 'type' => 'simple' ) );
$this->assertInstanceOf( \WC_Product::class, $product );
$this->assertGreaterThan( 0, $product->get_id() );
```

`batch()` / `Router::generate_batch()` give you IDs. Load each one to assert:

```php
$product_ids = Product::batch( 10 );
$this->assertIsArray( $product_ids );

foreach ( $product_ids as $id ) {
    $this->assertInstanceOf( \WC_Product::class, wc_get_product( $id ) );
}
```

You can also hook the `smoothgenerator_*_generated` actions to collect objects
as they are created:

| Action                              | Argument            |
|-------------------------------------|---------------------|
| `smoothgenerator_product_generated`  | `\WC_Product`       |
| `smoothgenerator_order_generated`    | `\WC_Order`         |
| `smoothgenerator_customer_generated` | `\WC_Customer`      |
| `smoothgenerator_coupon_generated`   | `\WC_Coupon`        |
| `smoothgenerator_booking_generated`  | `int` booking ID    |
| `smoothgenerator_term_generated`     | `\WP_Term`          |

```php
$created = array();

add_action(
    'smoothgenerator_product_generated',
    static function ( $product ) use ( &$created ) {
        $created[] = $product->get_id();
    }
);

Product::batch( 10 );

$this->assertCount( 10, $created );
```

Always check the return type before asserting. `generate()` can return a
`\WP_Error`, `Order::generate()` can return `false`, and `Booking::generate()`
returns an `int` rather than an object.

## Gotchas

- **WooCommerce must be bootstrapped.** The plugin declares WooCommerce as a
  required dependency and every generator instantiates WC classes. Without WC
  loaded you will get fatal "class not found" errors.
- **Batch return counts can vary.** `Product::batch()` and `Order::batch()`
  silently skip individual objects that fail, so the returned array may be
  shorter than the requested amount. `Customer`, `Coupon`, `Booking`, and
  `Term` batches instead abort and return the `\WP_Error` on the first
  failure. Don't assume `count( $ids ) === $amount` for products or orders.
- **Orders need products.** `Order::generate()` returns `false` if there are
  no published products to attach. Seed products first.
- **Bookings need WC Bookings.** `Booking::generate()` returns a
  `\WP_Error` if the extension is not active, and needs at least one published
  bookable product.
- **Term signature differs.** `Term::batch( $amount, $taxonomy, $args )` takes
  the taxonomy as a positional argument. Routing it through
  `Router::generate_batch()` would pass your array as the taxonomy and fail.
- **Images are real attachments.** By default products get generated Jdenticon
  PNG images uploaded to the media library. There is no flag to disable this;
  pre-seed attachments if you want to avoid on-disk image generation.
- **Batch size is capped.** `Generator::MAX_BATCH_SIZE` is 100. `batch( 200 )`
  returns a `\WP_Error`.
- **Emails are disabled automatically**, so generation will not send
  transactional WooCommerce emails during tests.

## Best practices

- Generate in `setUp()` or at the start of a test, and rely on
  `WP_UnitTestCase` rolling back the database between tests to keep data
  isolated. You generally do not need to delete generated objects manually.
- Seed dependencies in the right order: products (and optionally customers)
  before orders; bookable products before bookings.
- Prefer `Router::generate_batch()` for simple "give me N of type X" setups,
  and call generators directly when you need type-specific `$args` or the
  returned object for immediate assertion.
- Use deterministic assertions on IDs and object types rather than on the
  random content (names, prices), which changes every run.
