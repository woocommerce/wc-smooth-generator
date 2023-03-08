# WooCommerce Smooth Generator
A smooth products, customer and order generator using WP-CLI. Future versions will include scheduled auto generation functionality.

## Installation
WooCommerce Smooth Generator requires Composer and WP-CLI to function.

1. Clone this repository into your site's plugins folder
2. From command line CD into the cloned repository
3. From command run `composer install` and wait for the installation to complete
4. Run `wp plugin activate wc-smooth-generator` to activate the plugin
5. You now have access to a couple of new WP-CLI commands under the main `wp wc generate` command.

## Commands

### Products

Generate products based on the number of products paramater.
- `wp wc generate products <nr of products>`

Generate products of the specified type. `simple` or `variable`.
- `wp wc generate products <nr of products> --type=simple`

### Orders

Generate orders from existing products based on the number of orders paramater, customers will also be generated to mimic guest checkout.

Generate orders for the current date
- `wp wc generate orders <nr of orders>`

Generate orders with random dates between `--date-start` and the current date.
- `wp wc generate orders <nr of orders> --date-start=2018-04-01`

Generate orders with random dates between `--date-start` and `--date-end`.
- `wp wc generate orders <nr of orders> --date-start=2018-04-01 --date-end=2018-04-24`

Generate orders with a specific status.
- `wp wc generate orders <nr of orders> --status=completed`

You may wish to disable emails if creating a large number of orders as this will trigger emails. To block all emails from your site, install a plugin like [Disable Emails](https://wordpress.org/plugins/disable-emails/).

### Customers

Generate customers based on the number of customers paramater.
- `wp wc generate customers <nr of customers>`
