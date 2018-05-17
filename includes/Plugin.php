<?php
namespace WC\SmoothGenerator;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Constructor.
	 *
	 * @param string $file Main plugin __FILE__ reference.
	 */
	public function __construct( $file ) {
		register_activation_hook( $file, array( $this, 'init_events' ) );

		$admin = new Admin\Settings();
		$cli   = new CLI();

		$this->init_hooks();
	}

	/**
	 * Init hooks.
	 */
	public function init_hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );
	}

	/**
	 * Add CRON job schedules.
	 *
	 * @param array $schedules Cron job schedules.
	 * @return array
	 */
	public function add_cron_schedule( $schedules ) {
		$schedules['fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => 'Fifteen Minutes',
		);
		return $schedules;
	}

	/**
	 * Init CRON events.
	 */
	public function init_events() {
		wp_schedule_event( time(), 'fifteen_minutes', 'wc_smooth_generator' );
	}
}
