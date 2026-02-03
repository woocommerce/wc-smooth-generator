# WooCommerce Smooth Generator
A super-smooth generator for products, orders, coupons, customers, and terms. WP-CLI is the preferred interface for using the plugin. There is also a WP Admin UI at Dashboard > Tools > WooCommerce Smooth Generator with (for now) more limited functionality.

## Installation

1. Download the latest release as a zip file from https://github.com/woocommerce/wc-smooth-generator/releases/
1. Install in your WordPress site as you would any other plugin zip file.

## WP-CLI Commands

You can see a summary of all available commands by running `wp help wc generate`, and more detailed guidance for each individual command is available by running `wp help wc generate <command name>`.

### Products

Generate products based on the number of products parameter.
- `wp wc generate products <nr of products>`

Generate products of the specified type. `simple` or `variable`.
- `wp wc generate products <nr of products> --type=simple`

### Orders

Generate orders from existing products based on the number of orders parameter, customers will also be generated to mimic guest checkout.

Generate orders for the current date
- `wp wc generate orders <nr of orders>`

Generate orders with random dates between `--date-start` and the current date.
- `wp wc generate orders <nr of orders> --date-start=2018-04-01`

Generate orders with random dates between `--date-start` and `--date-end`.
- `wp wc generate orders <nr of orders> --date-start=2018-04-01 --date-end=2018-04-24`

Generate orders with a specific status.
- `wp wc generate orders <nr of orders> --status=completed`

Apply coupons to a percentage of generated orders (0.0-1.0). If no coupons exist, 6 will be created automatically (3 fixed cart, 3 percentage). Note: `--coupons` flag is equivalent to `--coupon-ratio=1.0`.

**Deterministic Distribution (Batch Mode):** When generating multiple orders, the exact number of orders with coupons is pre-calculated based on the ratio (e.g., 100 orders at 0.5 ratio = exactly 50 with coupons). For odd numbers, `round()` is used (e.g., 11 orders at 0.5 = 6 with coupons). Single order generation uses probabilistic distribution. For batches larger than 10,000 orders, probabilistic distribution is used to optimize memory.
- `wp wc generate orders <nr of orders> --coupon-ratio=0.5`

Refund a percentage of completed orders (0.0-1.0). Refunds are distributed as: 50% full refunds, 25% single partial refunds, and 25% multi-partial refunds (two partial refunds).

**Deterministic Distribution (Batch Mode):** When generating multiple orders, the exact number and type of refunds is pre-calculated (e.g., 100 orders at 0.4 ratio = exactly 20 full, 10 partial, 10 multi-partial). For odd numbers, remainders go to multi-partial refunds. Single order generation uses probabilistic distribution. For batches larger than 10,000 orders, probabilistic distribution is used to optimize memory.
- `wp wc generate orders <nr of orders> --status=completed --refund-ratio=0.3`

#### Order Attribution

Order Attribution represents the origin of data for an order. By default, random values are generated and assigned to the order. Orders with a creation date before 2024-01-09 will not have attribution metadata added, as the feature was not available in WooCommerce at that time.

Skip order attribution meta data genereation.
- `wp wc generate orders <nr of orders> --skip-order-attribution`

### Coupons

Generate coupons based on the number of coupons parameter.
- `wp wc generate coupons <nr of coupons>`

Generate coupons with a minimum discount amount.
- `wp wc generate coupons <nr of coupons> --min=5`

Generate coupons with a maximum discount amount.
- `wp wc generate coupons <nr of coupons> --max=50`

Generate coupons with a specific discount type. Options are `fixed_cart` or `percent`. If not specified, defaults to WooCommerce default (fixed_cart).
- `wp wc generate coupons <nr of coupons> --discount_type=percent --min=5 --max=25`

### Customers

Generate customers based on the number of customers parameter.
- `wp wc generate customers <nr of customers>`

### Terms

Generate terms in the Product Categories taxonomy based on the number of terms parameter.
- `wp wc generate terms product_cat <nr of terms>`

Generate hierarchical product categories with a maximum number of sub-levels.
- `wp wc generate terms product_cat <nr of terms> --max-depth=5`

Generate product categories that are all child terms of an existing product category term.
- `wp wc generate terms product_cat <nr of terms> --parent=123`

Generate terms in the Product Tags taxonomy based on the number of terms parameter.
- `wp wc generate terms product_tag <nr of terms>`

## Development

Requirements

* Node.js v16
* Composer v2+

1. If you use [Node Version Manager](https://github.com/nvm-sh/nvm) (nvm) you can run `nvm use` to ensure your current Node version is compatible.
1. Run `npm run setup` to get started. This will install a pre-commit Git hook that will lint changes to PHP files before they are committed. It uses the same phpcs ruleset that's used by WooCommerce Core.

### Releasing a new version

1. Create a new branch with a name like `release-x.x.x`.
1. Add a new entry to the **changelog.txt** file with all the changes since the last release. Follow the conventions of previous changelog entries.
1. If necessary, update the `Tested up to` and `WC tested up to` values in the plugin header in **wc-smooth-generator.php**.
1. Update the plugin version with the new value in the **wc-smooth-generator.php** and **package.json** files.
1. Run `npm run build` to generate a production-ready zip file.
1. Test the zip file by installing it in a WordPress instance and ensuring it has the expected version number and changes.
1. Commit the changes to your release branch, and push to the repository. Create a pull request from the release branch.
1. Merge the pull request.
1. In GitHub, go to the Releases screen and click "Draft a new release".
1. Set the release title as "Version x.x.x" (but with the actual version number). In the release description, add a brief summary of highlights, and then paste the new changelog entry below that. From the "Choose a tag" dropdown, type the new version number and then click "Create a new tag". Ensure the target is trunk.
1. Upload the new zip file to the release where it says "Attach binaries".
1. Publish the release!

After finishing the release, you may want to run `npm run setup` again, because the `build` script removes dev dependencies.
