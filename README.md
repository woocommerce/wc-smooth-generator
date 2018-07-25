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
- `wp wc generate products <nr of products>` Generate products based on the number of products paramater.
- `wp wc generate orders <nr of orders>` Generate orders from existing products based on the number of orders paramater, customers will also be generated to mimic guest checkout.
- `wp wc generate customers <nr of customers>` Generate customers based on the number of customers paramater.
