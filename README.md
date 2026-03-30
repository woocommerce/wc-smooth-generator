# WooCommerce Smooth Generator

Generate realistic WooCommerce products, orders, customers, coupons, taxonomy terms, and bookings for development, testing, and demos.

WP-CLI is the primary interface. A limited WP Admin UI is also available at Dashboard > Tools > WooCommerce Smooth Generator.

## Installation

**From GitHub releases (recommended):**

1. Download the latest zip from [GitHub Releases](https://github.com/woocommerce/wc-smooth-generator/releases/).
2. Install via WP Admin > Plugins > Add New > Upload Plugin.

**From source:**

```bash
git clone https://github.com/woocommerce/wc-smooth-generator.git
cd wc-smooth-generator
composer install --no-dev
```

## Requirements

- PHP 7.4+
- WordPress (tested up to 6.9)
- WooCommerce 5.0+
- [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/) (optional, required for booking generation)

## WP-CLI commands

All commands use the `wp wc generate` prefix. Run `wp help wc generate` for a summary, or `wp help wc generate <command>` for detailed usage.

### Products

```bash
# Generate 10 products (default, mix of simple and variable)
wp wc generate products

# Generate 25 simple products
wp wc generate products 25 --type=simple

# Generate variable products using only existing categories and tags
wp wc generate products 10 --type=variable --use-existing-terms

# Generate bookable products (requires WooCommerce Bookings)
wp wc generate products 5 --type=booking

# Generate bookable service products (virtual, short-duration)
wp wc generate products 5 --type=booking-service
```

| Option | Description |
|---|---|
| `<amount>` | Number of products to generate. Default: `10` |
| `--type=<type>` | Product type: `simple`, `variable`, `booking`, or `booking-service`. Default: random mix of simple/variable. `booking` and `booking-service` require [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/) |
| `--use-existing-terms` | Only use existing categories and tags instead of generating new ones |

### Orders

```bash
# Generate 10 orders for today's date
wp wc generate orders

# Generate orders with random dates in a range
wp wc generate orders 50 --date-start=2024-01-01 --date-end=2024-12-31

# Generate completed orders with a specific status
wp wc generate orders 20 --status=completed

# Apply coupons to half the orders
wp wc generate orders 100 --coupon-ratio=0.5

# Refund 30% of completed orders
wp wc generate orders 50 --status=completed --refund-ratio=0.3
```

| Option | Description |
|---|---|
| `<amount>` | Number of orders to generate. Default: `10` |
| `--date-start=<date>` | Earliest order date (YYYY-MM-DD). Dates are randomized between this and today or `--date-end` |
| `--date-end=<date>` | Latest order date (YYYY-MM-DD). Requires `--date-start` |
| `--status=<status>` | Order status: `completed`, `processing`, `on-hold`, or `failed`. Default: random mix |
| `--coupons` | Apply a coupon to every order. Equivalent to `--coupon-ratio=1.0` |
| `--coupon-ratio=<ratio>` | Fraction of orders that get coupons (0.0-1.0). Creates 6 coupons if none exist (3 fixed cart, 3 percentage) |
| `--refund-ratio=<ratio>` | Fraction of completed orders to refund (0.0-1.0). In batch mode: 50% full, 25% partial, 25% multi-partial. Single-order mode uses probabilistic distribution |
| `--skip-order-attribution` | Skip generating order attribution metadata |

**Batch distribution:** When generating multiple orders, coupon and refund counts are deterministic (selection without replacement). For odd totals, `round()` distributes coupons and remainders go to multi-partial refunds. Single-order generation uses probabilistic distribution.

**Order attribution:** Random attribution metadata (device type, UTM parameters, referrer, session data) is added by default. Orders dated before 2024-01-09 skip attribution, since the feature didn't exist in WooCommerce yet.

### Bookings

Requires the [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/) extension.

```bash
# Generate 10 bookings (creates bookable products automatically if none exist)
wp wc generate bookings

# Generate bookings with a specific date range
wp wc generate bookings 50 --date-start=2026-04-01 --date-end=2026-06-30

# Generate confirmed bookings for a specific bookable product
wp wc generate bookings 20 --status=confirmed --product-id=42

# Generate bookings without associated WooCommerce orders
wp wc generate bookings 30 --no-orders
```

| Option | Description |
|---|---|
| `<amount>` | Number of bookings to generate. Default: `10` |
| `--date-start=<date>` | Earliest booking date (YYYY-MM-DD). Default: 14 days ago |
| `--date-end=<date>` | Latest booking date (YYYY-MM-DD). Default: 42 days from now |
| `--status=<status>` | Booking status: `unpaid`, `pending-confirmation`, `confirmed`, `paid`, `cancelled`, or `complete`. Default: weighted random mix |
| `--product-id=<id>` | Use a specific bookable product for all bookings |
| `--no-orders` | Skip creating associated WooCommerce orders (orders are created by default) |

### Customers

```bash
# Generate 10 customers (70% people, 30% companies)
wp wc generate customers

# Generate Spanish company customers
wp wc generate customers 20 --country=ES --type=company
```

| Option | Description |
|---|---|
| `<amount>` | Number of customers to generate. Default: `10` |
| `--country=<code>` | ISO 3166-1 alpha-2 country code (e.g., `US`, `ES`, `CN`). Localizes names and addresses. Default: random from store selling locations |
| `--type=<type>` | Customer type: `person` or `company`. Default: 70/30 mix |

### Coupons

```bash
# Generate 10 coupons with default discount range (5-100)
wp wc generate coupons

# Generate percentage coupons between 5% and 25%
wp wc generate coupons 20 --discount_type=percent --min=5 --max=25
```

| Option | Description |
|---|---|
| `<amount>` | Number of coupons to generate. Default: `10` |
| `--min=<amount>` | Minimum discount amount. Default: `5` |
| `--max=<amount>` | Maximum discount amount. Default: `100` |
| `--discount_type=<type>` | Discount type: `fixed_cart` or `percent`. Default: `fixed_cart` |

### Terms

```bash
# Generate 10 product tags
wp wc generate terms product_tag 10

# Generate hierarchical product categories
wp wc generate terms product_cat 50 --max-depth=3

# Generate child categories under an existing category
wp wc generate terms product_cat 10 --parent=123
```

| Option | Description |
|---|---|
| `<taxonomy>` | Required. Taxonomy to generate terms for: `product_cat` or `product_tag` |
| `<amount>` | Number of terms to generate. Default: `10` |
| `--max-depth=<levels>` | Maximum hierarchy depth (1-5). Only applies to `product_cat`. Default: `1` (flat) |
| `--parent=<term_id>` | Create all terms as children of this existing term ID. Only applies to `product_cat` |

## Programmatic usage

All generators live in the `WC\SmoothGenerator\Generator` namespace and expose `generate()` and `batch()` static methods.

### Single objects

```php
use WC\SmoothGenerator\Generator;

// Generate and save a product (returns WC_Product or WP_Error).
$product = Generator\Product::generate( true, [ 'type' => 'simple' ] );

// Generate and save an order (returns WC_Order or false).
$order = Generator\Order::generate( true, [ 'status' => 'completed' ] );

// Generate and save a customer (returns WC_Customer or WP_Error).
$customer = Generator\Customer::generate( true, [ 'country' => 'US', 'type' => 'person' ] );

// Generate and save a booking (returns booking ID or WP_Error). Requires WooCommerce Bookings.
$booking_id = Generator\Booking::generate( true, [ 'status' => 'confirmed' ] );

// Generate and save a bookable product (returns WC_Product_Booking or WP_Error). Requires WooCommerce Bookings.
$booking_product = Generator\Product::generate( true, [ 'type' => 'booking' ] );

// Generate and save a bookable service product (returns WC_Product_Booking or WP_Error). Requires WooCommerce Bookings.
$service_product = Generator\Product::generate( true, [ 'type' => 'booking-service' ] );

// Generate and save a coupon (returns WC_Coupon or WP_Error).
$coupon = Generator\Coupon::generate( true, [ 'min' => 5, 'max' => 25, 'discount_type' => 'percent' ] );

// Generate and save a term (returns WP_Term or WP_Error).
$term = Generator\Term::generate( true, 'product_cat', 0 );
```

### Batch generation

```php
use WC\SmoothGenerator\Generator;

// Generate 50 products (returns array of product IDs or WP_Error).
// Max batch size: 100.
$product_ids = Generator\Product::batch( 50, [ 'type' => 'variable', 'use-existing-terms' => true ] );

// Generate 10 bookable products. Requires WooCommerce Bookings.
$booking_product_ids = Generator\Product::batch( 10, [ 'type' => 'booking' ] );

// Generate 100 orders with date range and coupons.
$order_ids = Generator\Order::batch( 100, [
    'date-start'   => '2024-01-01',
    'date-end'     => '2024-06-30',
    'status'       => 'completed',
    'coupon-ratio' => '0.3',
    'refund-ratio' => '0.2',
] );

// Generate 20 bookings with associated orders.
$booking_ids = Generator\Booking::batch( 20, [
    'date-start'  => '2026-04-01',
    'date-end'    => '2026-06-30',
    'with-orders' => true,
] );

// Generate 25 customers.
$customer_ids = Generator\Customer::batch( 25, [ 'country' => 'ES' ] );

// Generate 10 coupons.
$coupon_ids = Generator\Coupon::batch( 10, [ 'min' => 1, 'max' => 50 ] );

// Generate 20 hierarchical product categories.
$term_ids = Generator\Term::batch( 20, 'product_cat', [ 'max-depth' => 3 ] );
```

### Action hooks

Each generator fires an action after creating an object:

- `smoothgenerator_product_generated` -- after a product is saved
- `smoothgenerator_order_generated` -- after an order is saved
- `smoothgenerator_booking_generated` -- after a booking is saved (requires WooCommerce Bookings)
- `smoothgenerator_customer_generated` -- after a customer is saved
- `smoothgenerator_coupon_generated` -- after a coupon is saved
- `smoothgenerator_term_generated` -- after a term is saved

## Available generators

### Product generator

Creates simple, variable, booking, or booking-service products with:

- Name, SKU, global unique ID, featured status
- Price, sale price, sale date scheduling
- Tax status and class, stock management
- Product image and gallery images (auto-generated)
- Categories, tags, and brands (if the `product_brand` taxonomy exists)
- Upsells and cross-sells from existing products
- Attributes and variations (for variable products)
- Virtual/downloadable flags, dimensions, weight
- Cost of Goods Sold (if WooCommerce COGS is enabled)
- Reviews allowed toggle, purchase notes, menu order

**Booking products** (requires [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/)):

- Hourly or daily duration with realistic names (consultations, rentals, venues)
- Person support with min/max counts and cost multiplier (~40% of products)
- Resource assignment with named resources like rooms and stations (~30% of products)
- Configurable availability windows (30-180 days)
- Cancellation settings

**Booking-service products** (requires [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/)):

- Virtual, short-duration services (haircuts, repairs, grooming)
- Minute-based (15-60 min) or short hour-based (1-2 hrs) durations
- No persons or resources (simple appointment-style bookings)
- Short availability windows (14-60 days)

### Order generator

Creates orders with realistic data:

- Billing and shipping addresses from the customer
- Line items from existing products
- Random status distribution (or a specific status)
- Date randomization within a given range
- Coupon application with configurable ratio
- Refunds: full, partial, and multi-partial with realistic timing
- Order attribution: device type, UTM parameters, referrer, session data
- Extra fees (~20% chance per order)
- Paid and completed dates based on status

### Booking generator

Requires the [WooCommerce Bookings](https://woocommerce.com/products/woocommerce-bookings/) extension. Creates bookings with:

- Checks for WooCommerce Bookings dependency before proceeding
- Auto-creates varied bookable products (hourly services, daily rentals, group workshops) if none exist
- Random booking dates within a configurable range
- Weighted status distribution: paid (35%), confirmed (25%), complete (20%), unpaid (10%), pending-confirmation (5%), cancelled (5%)
- Person counts based on product configuration
- Resource assignment from product's available resources
- Associated WooCommerce orders with mapped statuses (optional)
- Customer assignment from existing customers or auto-created ones

### Customer generator

Creates customer accounts with localized data:

- Person (first/last name) or company profiles
- Localized names, emails, and phone numbers based on country
- Billing address with street, city, state, postcode
- Shipping address (50% chance; half copy billing, half are unique)
- Username and password

### Coupon generator

Creates discount coupons:

- Auto-generated coupon codes
- Configurable discount range (min/max)
- Fixed cart or percentage discount type

### Term generator

Creates taxonomy terms for products:

- Product categories (`product_cat`) with optional hierarchy (up to 5 levels deep)
- Product tags (`product_tag`)
- Auto-generated descriptions
- Child terms under a specified parent

## Contributing

Found a bug or want a feature? [Open an issue](https://github.com/woocommerce/wc-smooth-generator/issues) or submit a pull request.

### Development setup

Requires Node.js v16 and Composer v2+.

```bash
npm run setup
```

This installs dependencies and sets up a pre-commit hook that lints PHP changes using the WooCommerce Core phpcs ruleset.

## License

[GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0.html)
