<?php
/**
 * Plugin admin settings
 *
 * @package SmoothGenerator\Admin\Classes
 */

namespace WC\SmoothGenerator\Admin;

/**
 *  Initializes and manages the settings screen.
 */
class Settings {

	const DEFAULT_NUM_PRODUCTS           = 100;
	const DEFAULT_NUM_ORDERS             = 100;
	const DEFAULT_ORDER_INTERVAL_MINUTES = 3;

	/**
	 *  Set up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
	}

	/**
	 * Register the admin menu and screen.
	 */
	public static function register_admin_menu() {
		$hook = add_management_page( 'WooCommerce Smooth Generator', 'WooCommerce Smooth Generator', 'install_plugins', 'smoothgenerator', array( __CLASS__, 'render_admin_page' ) );
		add_action( "load-$hook", array( __CLASS__, 'process_page_submit' ) );
	}

	/**
	 * Render the admin page.
	 */
	public static function render_admin_page() {
		?>
		<h1>WooCommerce Smooth Generator</h1>
		<form method="post">
			<?php wp_nonce_field( 'generate', 'smoothgenerator_nonce' ); ?>
			<h2>Generate Products</h2>
			<p>
				<input type="number" name="num_products_to_generate" value="<?php echo esc_attr( self::DEFAULT_NUM_PRODUCTS ); ?>" min="1" />
				Number of products to generate.
			</p>
			<?php submit_button( 'Generate', 'primary', 'generate_products' ); ?>

			<h2>Generate Orders</h2>
			<p>
				<input type="number" name="num_orders_to_generate" value="<?php echo esc_attr( self::DEFAULT_NUM_ORDERS ); ?>" min="1" />
				Number of orders to generate.
			</p>
			<?php submit_button( 'Generate', 'primary', 'generate_orders' ); ?>

			<h2>Cancel all scheduled generations</h2>
			<p>
			</p>
			<?php submit_button( 'Cancel all', 'primary', 'cancel_all_generations' ); ?>
		</form>
		<?php
	}

	/**
	 * Process the generation.
	 */
	public static function process_page_submit() {
		if ( ! empty( $_POST['generate_products'] ) && ! empty( $_POST['num_products_to_generate'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			$num_to_generate = absint( $_POST['num_products_to_generate'] );
			wc_smooth_generate_schedule( 'product', $num_to_generate );
			add_action( 'admin_notices', array( __CLASS__, 'product_generating_notice' ) );
		} else if ( ! empty( $_POST['generate_orders'] ) && ! empty( $_POST['num_orders_to_generate'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			$num_to_generate = absint( $_POST['num_orders_to_generate'] );
			wc_smooth_generate_schedule( 'order', $num_to_generate );
			add_action( 'admin_notices', array( __CLASS__, 'order_generating_notice' ) );
		} else if ( ! empty( $_POST['cancel_all_generations'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			wc_smooth_generate_cancel_all();
		}
	}

	/**
	 * Render notice about products generating.
	 */
	public static function product_generating_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>Generating products in the background . . . </p>
			<p>
				<iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0"width="427.2" height="240" type="text/html" src="https://www.youtube.com/embed/4TYv2PhG89A?autoplay=1&fs=0&iv_load_policy=3&showinfo=0&rel=0&cc_load_policy=0&start=0&end=0"></iframe>
			</p>
		</div>
		<?php
	}

	/**
	 * Render notice about orders generating.
	 */
	public static function order_generating_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>Generating orders in the background . . . </p>
			<p>
				<iframe frameborder="0" scrolling="no" marginheight="0" marginwidth="0"width="427.2" height="240" type="text/html" src="https://www.youtube.com/embed/4TYv2PhG89A?autoplay=1&fs=0&iv_load_policy=3&showinfo=0&rel=0&cc_load_policy=0&start=0&end=0"></iframe>
			</p>
		</div>
		<?php
	}
}
