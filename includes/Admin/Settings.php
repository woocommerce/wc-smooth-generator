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

	const DEFAULT_NUM_PRODUCTS = 10;
	const DEFAULT_NUM_ORDERS   = 10;

	/**
	 *  Set up hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_filter( 'heartbeat_received', array( __CLASS__, 'receive_heartbeat' ), 10, 3 );
	}

	/**
	 * Register the admin menu and screen.
	 */
	public static function register_admin_menu() {
		$hook = add_management_page(
			'WooCommerce Smooth Generator',
			'Smooth Generator',
			'install_plugins',
			'smoothgenerator',
			array( __CLASS__, 'render_admin_page' )
		);

		add_action( "load-$hook", array( __CLASS__, 'process_page_submit' ) );
	}

	/**
	 * Render the admin page.
	 */
	public static function render_admin_page() {
		$current_job    = self::get_current_job();
		$hpos_enabled   = \WC\SmoothGenerator\Generator\OrderBulkInserter::is_hpos_enabled();
		$unsynced_count = $hpos_enabled ? \WC\SmoothGenerator\Generator\OrderAnalyticsSync::get_unsynced_count() : 0;
		$total_count    = $hpos_enabled ? \WC\SmoothGenerator\Generator\OrderAnalyticsSync::get_total_order_count() : 0;

		$generate_button_atts = $current_job instanceof AsyncJob ? array( 'disabled' => true ) : array();
		$cancel_button_atts   = ! $current_job instanceof AsyncJob ? array( 'disabled' => true ) : array();

		?>
		<h1>WooCommerce Smooth Generator</h1>
		<p class="description">
			Generate randomized WooCommerce data for testing.
		</p>

		<?php echo self::while_you_wait(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( $current_job instanceof AsyncJob ) : ?>
			<div id="smoothgenerator-progress">
				<label for="smoothgenerator-progress-bar" style="display: block;">
					<?php
					if ( 'analytics-sync' === $current_job->generator_slug ) {
						printf(
							'Syncing analytics for %s orders&hellip;',
							esc_html( number_format_i18n( $current_job->amount ) )
						);
					} else {
						printf(
							'Generating %s %s&hellip;',
							esc_html( number_format_i18n( $current_job->amount ) ),
							esc_html( $current_job->generator_slug )
						);
					}
					?>
				</label>
				<progress
					id="smoothgenerator-progress-bar"
					max="<?php echo esc_attr( $current_job->amount ); ?>"
					value="<?php echo $current_job->processed ? esc_attr( $current_job->processed ) : ''; ?>"
					style="width: 560px;"
				>
					<?php
					printf(
						'%d out of %d',
						esc_html( $current_job->processed ),
						esc_html( $current_job->amount )
					);
					?>
				</progress>
			</div>
		<?php elseif ( filter_input( INPUT_POST, 'cancel_job' ) ) : ?>
			<div class="notice notice-error inline-notice is-dismissible" style="margin-left: 0;">
				<p>Current job canceled.</p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'generate', 'smoothgenerator_nonce' ); ?>
			<h2>Generate products</h2>
			<p>
				<label for="generate_products_input" class="screen-reader-text">Number of products to generate</label>
				<input
					id="generate_products_input"
					type="number"
					name="num_products_to_generate"
					value="<?php echo esc_attr( self::DEFAULT_NUM_PRODUCTS ); ?>"
					min="1"
					<?php disabled( $current_job instanceof AsyncJob ); ?>
				/>
				<?php
				submit_button(
					'Generate',
					'primary',
					'generate_products',
					false,
					$generate_button_atts
				);
				?>
			</p>

			<h2>Generate orders</h2>
			<p>
				<label for="generate_orders_input" class="screen-reader-text">Number of orders to generate</label>
				<input
					id="generate_orders_input"
					type="number"
					name="num_orders_to_generate"
					value="<?php echo esc_attr( self::DEFAULT_NUM_ORDERS ); ?>"
					min="1"
					<?php disabled( $current_job instanceof AsyncJob ); ?>
				/>
				<?php
				submit_button(
					'Generate',
					'primary',
					'generate_orders',
					false,
					$generate_button_atts
				);
				?>
			</p>

			<h2>Advanced Options</h2>
			<p>
				<label>
					<input
						type="checkbox"
						id="use_date_range"
						name="use_date_range"
						<?php disabled( $current_job instanceof AsyncJob ); ?>
					/>
					Specify date range for generation
				</label>
			</p>
			<p>
				<label>
					<input
						type="checkbox"
						id="use_bulk_insert"
						name="use_bulk_insert"
						<?php disabled( $current_job instanceof AsyncJob || ! $hpos_enabled ); ?>
					/>
					Use bulk insert for orders (HPOS required) <span class="description">&mdash; experimental</span>
				</label>
				<?php if ( ! $hpos_enabled ) : ?>
					<span class="description"> &mdash; HPOS is not enabled. <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) ); ?>">Enable it here</a>.</span>
				<?php endif; ?>
			</p>
			<div id="bulk_insert_options" style="display: none; margin-left: 1.5em;">
				<p>
					<label>
						<input
							type="checkbox"
							id="use_bulk_coupons"
							name="use_bulk_coupons"
							<?php disabled( $current_job instanceof AsyncJob || ! $hpos_enabled ); ?>
						/>
						Apply a random coupon to each order (uses existing coupons; creates 6 if none exist)
					</label>
				</p>
				<p>
					<label>
						<input
							type="checkbox"
							id="use_bulk_shipping"
							name="use_bulk_shipping"
							<?php disabled( $current_job instanceof AsyncJob || ! $hpos_enabled ); ?>
						/>
						Add a shipping line using a random enabled shipping zone method
					</label>
				</p>
				<p>
					<label>
						<input
							type="checkbox"
							id="use_bulk_taxes"
							name="use_bulk_taxes"
							<?php disabled( $current_job instanceof AsyncJob || ! $hpos_enabled ); ?>
						/>
						Add a tax line using a random defined tax rate
					</label>
				</p>
			</div>
			<div id="date_range_inputs" style="display: none;">
				<p>
					<label for="generate_start_date_input">Start date</label>
					<input
						id="generate_start_date_input"
						type="date"
						name="start_date"
						value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
						<?php disabled( $current_job instanceof AsyncJob ); ?>
					/>
					<label for="generate_end_date_input">End date</label>
					<input
						id="generate_end_date_input"
						type="date"
						name="end_date"
						value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
						<?php disabled( $current_job instanceof AsyncJob ); ?>
					/>
				</p>
			</div>

			<h2>Sync Analytics <span class="description" style="font-size: 0.8em; font-weight: normal;">&mdash; experimental</span></h2>
			<?php if ( ! $hpos_enabled ) : ?>
				<p class="description">
					Analytics sync requires HPOS to be enabled.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' ) ); ?>">Enable it here</a>.
				</p>
			<?php else : ?>
				<p>
					<?php if ( $unsynced_count > 0 ) : ?>
						<strong><?php echo esc_html( number_format_i18n( $unsynced_count ) ); ?></strong> of
						<strong><?php echo esc_html( number_format_i18n( $total_count ) ); ?></strong> orders
						are not yet reflected in Analytics reports.
					<?php else : ?>
						All <?php echo esc_html( number_format_i18n( $total_count ) ); ?> orders are reflected in Analytics reports.
					<?php endif; ?>
				</p>
				<p>
					<?php
					submit_button(
						'Sync unsynced orders',
						'secondary',
						'sync_analytics_unsynced',
						false,
						array_merge(
							$generate_button_atts,
							0 === $unsynced_count ? array( 'disabled' => true ) : array()
						)
					);
					?>
				</p>
				<p>
					<label>
						<input
							type="checkbox"
							id="confirm_resync_all"
							name="confirm_resync_all"
							<?php disabled( $current_job instanceof AsyncJob ); ?>
						/>
						Re-sync all <?php echo esc_html( number_format_i18n( $total_count ) ); ?> orders (replaces existing Analytics data)
					</label>
				</p>
				<p>
					<?php
					submit_button(
						'Re-sync all orders',
						'secondary',
						'sync_analytics_all',
						false,
						array_merge(
							$generate_button_atts,
							array(
								'disabled' => true,
								'id'       => 'sync_analytics_all_btn',
							)
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			submit_button(
				'Cancel current job',
				'secondary',
				'cancel_job',
				true,
				$cancel_button_atts
			);
			?>
		</form>
		<?php

		self::heartbeat_script();
		self::date_range_toggle_script();
	}

	/**
	 * Script to toggle date range inputs visibility.
	 *
	 * @return void
	 */
	protected static function date_range_toggle_script() {
		?>
		<script>
			( function( $ ) {
				$( '#use_date_range' ).on( 'change', function() {
					$( '#date_range_inputs' ).toggle( this.checked );
				} );

				$( '#use_bulk_insert' ).on( 'change', function() {
					$( '#bulk_insert_options' ).toggle( this.checked );
				} );

				$( '#confirm_resync_all' ).on( 'change', function() {
					$( '#sync_analytics_all_btn' ).prop( 'disabled', ! this.checked );
				} );
			} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Script to interact with heartbeat and run the progress bar.
	 *
	 * @return void
	 */
	protected static function heartbeat_script() {
		?>
		<script>
			( function( $ ) {
				const $document = $( document );
				const $progress = $( '#smoothgenerator-progress-bar' );
				const $controls = $( '[id^="generate_"], #use_date_range, #use_bulk_insert, #use_bulk_coupons, #use_bulk_shipping, #use_bulk_taxes, #confirm_resync_all, #date_range_inputs input, [name="sync_analytics_unsynced"]' );
				const $cancel   = $( '#cancel_job' );

				$document.on( 'ready', function () {
					wp.heartbeat.disableSuspend();
					wp.heartbeat.interval( 'fast' );
					wp.heartbeat.connectNow();
				} );

				$document.on( 'heartbeat-send', function ( event, data ) {
					data.smoothgenerator = 'check_async_job_progress';
				} );

				$document.on( 'heartbeat-tick', function ( event, data ) {
					// Heartbeat and other admin-ajax calls don't trigger wp-cron, so we have to do it manually.
					$.ajax( {
						url: data.smoothgenerator_ping_cron,
						method: 'get',
						timeout: 5000,
						dataType: 'html'
					} );

					if ( 'object' === typeof data.smoothgenerator_async_job_progress ) {
						const value = parseInt( data.smoothgenerator_async_job_progress.processed );
						if ( value > 0 ) {
							$progress.prop( 'value', value );
						}
					} else if ( 'complete' === data.smoothgenerator_async_job_progress && $progress.is( ':visible' ) ) {
						$progress.prop( 'value', $progress.prop( 'max' ) );
						$progress.parent().append( '✅' );
						$progress.siblings( 'label' ).first().append( ' Done!' );
						$controls.add( $cancel ).prop( 'disabled', function ( i, val ) {
							return ! val;
						} );
						$document.off( 'heartbeat-send' );
						$document.off( 'heartbeat-tick' );
					}
				} );
			} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Callback to send data for updating the progress bar.
	 *
	 * @param array  $response  The data that will be sent back to heartbeat.
	 * @param array  $data      The incoming data from heartbeat.
	 * @param string $screen_id The ID of the current WP Admin screen.
	 *
	 * @return array
	 */
	public static function receive_heartbeat( array $response, array $data, $screen_id ) {
		if ( 'tools_page_smoothgenerator' !== $screen_id || empty( $data['smoothgenerator'] ) ) {
			return $response;
		}

		$current_job = self::get_current_job();

		if ( $current_job instanceof AsyncJob ) {
			$response['smoothgenerator_async_job_progress'] = $current_job;
			$response['smoothgenerator_ping_cron']          = site_url( 'wp-cron.php' );
		} else {
			$response['smoothgenerator_async_job_progress'] = 'complete';
		}

		return $response;
	}

	/**
	 * Process the generation.
	 */
	public static function process_page_submit() {
		$args = array();

		if ( ! empty( $_POST['use_date_range'] ) ) {
			$args['date-start'] = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
			$args['date-end']   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		}

		if ( ! empty( $_POST['generate_products'] ) && ! empty( $_POST['num_products_to_generate'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			$num_to_generate = absint( $_POST['num_products_to_generate'] );
			BatchProcessor::create_new_job( 'products', $num_to_generate, $args );
		} elseif ( ! empty( $_POST['generate_orders'] ) && ! empty( $_POST['num_orders_to_generate'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			$num_to_generate = absint( $_POST['num_orders_to_generate'] );
			if ( ! empty( $_POST['use_bulk_insert'] ) && \WC\SmoothGenerator\Generator\OrderBulkInserter::is_hpos_enabled() ) {
				$args['bulk-insert'] = true;
				if ( ! empty( $_POST['use_bulk_coupons'] ) ) {
					$args['coupons'] = true;
				}
				if ( ! empty( $_POST['use_bulk_shipping'] ) ) {
					$args['shipping'] = true;
				}
				if ( ! empty( $_POST['use_bulk_taxes'] ) ) {
					$args['taxes'] = true;
				}
			}
			BatchProcessor::create_new_job( 'orders', $num_to_generate, $args );
		} elseif ( ! empty( $_POST['sync_analytics_unsynced'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			if ( \WC\SmoothGenerator\Generator\OrderAnalyticsSync::is_hpos_available() ) {
				$unsynced = \WC\SmoothGenerator\Generator\OrderAnalyticsSync::get_unsynced_count();
				if ( $unsynced > 0 ) {
					BatchProcessor::create_new_job( 'analytics-sync', $unsynced, array() );
				}
			}
		} elseif ( ! empty( $_POST['sync_analytics_all'] ) && ! empty( $_POST['confirm_resync_all'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			if ( \WC\SmoothGenerator\Generator\OrderAnalyticsSync::is_hpos_available() ) {
				$total = \WC\SmoothGenerator\Generator\OrderAnalyticsSync::get_total_order_count();
				if ( $total > 0 ) {
					delete_option( \WC\SmoothGenerator\Generator\OrderAnalyticsSync::CURSOR_OPTION );
					BatchProcessor::create_new_job( 'analytics-sync', $total, array( 'all' => true ) );
				}
			}
		} elseif ( ! empty( $_POST['cancel_job'] ) ) {
			check_admin_referer( 'generate', 'smoothgenerator_nonce' );
			BatchProcessor::delete_current_job();
		}
	}

	/**
	 * Get the state of the current background job.
	 *
	 * @return AsyncJob|null
	 */
	protected static function get_current_job() {
		return BatchProcessor::get_current_job();
	}

	/**
	 * Render some entertainment while waiting for the generator to finish.
	 *
	 * @return string
	 */
	protected static function while_you_wait() {
		$current_job = self::get_current_job();
		$content     = '';

		if ( filter_input( INPUT_POST, 'smoothgenerator_nonce' ) || $current_job instanceof AsyncJob ) {
			if ( filter_input( INPUT_POST, 'cancel_job' ) ) {
				$embed = 'NF9Y3GVuPfY';
			} else {
				$videos    = array(
					'4TYv2PhG89A',
					'6Whgn_iE5uc',
					'h_D3VFfhvs4',
					'QcjAXI4jANw',
				);
				$next_wait = filter_input( INPUT_COOKIE, 'smoothgenerator_next_wait' );
				if ( ! isset( $videos[ $next_wait ] ) ) {
					$next_wait = 0;
				}
				$embed = $videos[ $next_wait ];
				++$next_wait;
				setcookie(
					'smoothgenerator_next_wait',
					$next_wait,
					array(
						'expires'  => time() + WEEK_IN_SECONDS,
						'path'     => ADMIN_COOKIE_PATH,
						'domain'   => COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'samesite' => 'strict',
					)
				);
			}

			$content = <<<"EMBED"
<h2>While you wait...</h2>
<div class="wp-block-embed__wrapper" style="margin: 2em 0;"><iframe width="560" height="315" src="https://www.youtube.com/embed/$embed?autoplay=1&fs=0&iv_load_policy=3&showinfo=0&rel=0&cc_load_policy=0&start=0&end=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen>></iframe></div>
EMBED;
		}

		return $content;
	}
}
