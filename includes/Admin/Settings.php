<?php
namespace WC\SmoothGenerator\Admin;

/**
 *  Initializes and manages the settings screen.
 */
class Settings {

	const DEFAULT_NUM_PRODUCTS = 100;
	const DEFAULT_NUM_ORDERS = 100;
	const DEFAULT_ORDER_INTERVAL_MINUTES = 3;

	/**
	 *  Set up hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register the admin menu and screen.
	 */
	public function register_admin_menu() {
		$hook = add_management_page( 'WooCommerce Smooth Generator', 'WooCommerce Smooth Generator', 'install_plugins', 'smoothgenerator', array( $this, 'render_admin_page' ) );
		add_action( "load-$hook", array( $this, 'process_page_submit' ) );
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		?>
		<form method="get">
			<h2>Generate Products</h2>
			<p>
				<input type="number" name="num_products_to_generate" value="<?php echo self::DEFAULT_NUM_PRODUCTS ?>" min="1" />
				Number of products to generate.
			</p>
			<?php submit_button( 'Generate', 'primary', 'generate_products' ); ?>

			<h2>Generate Orders</h2>
			<p>
				<input type="number" name="num_orders_to_generate" value="<?php echo self::DEFAULT_NUM_ORDERS ?>" min="1" />
				Number of orders to generate over
				<input type="number" name="order_generation_interval" value="<?php echo self::DEFAULT_ORDER_INTERVAL_MINUTES ?>" min="0" />
				minutes.
			</p>
			<?php submit_button( 'Generate', 'primary', 'generate_orders' ); ?>
		</form>
		<?php
	}

	/**
	 * Process the generation.
	 */
	public function process_page_submit() {
		if ( ! empty( $_POST['generate_products'] ) && ! empty( $_POST['num_products_to_generate'] ) ) {
			$num_to_generate = absint( $_POST['num_products_to_generate'] );
			// @todo kick off generation here
			add_action( 'admin_notices', array( $this, 'product_generating_notice' ) );
		} else if ( ! empty( $_GET['generate_orders'] ) && ! empty( $_POST['num_orders_to_generate'] ) && ! empty( $_POST['order_generation_interval'] ) ) {
			$num_to_generate = absint( $_POST['num_orders_to_generate'] );
			$order_generation_interval = absint( $_POST['order_generation_interval'] );
			// @todo kick off generation here
			add_action( 'admin_notices', array( $this, 'order_generating_notice' ) );
		}
	}

	/**
	 * Render notice about products generating.
	 */
	public function product_generating_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>Generating products in the background . . . </p>
		</div>
		<?php
	}

	/**
	 * Render notice about orders generating.
	 */
	public function order_generating_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p>Generating orders in the background . . . </p>
		</div>
		<?php
	}
}
new Settings();
