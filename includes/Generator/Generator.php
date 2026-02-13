<?php
/**
 * Abstract Generator class
 *
 * @package SmoothGenerator\Abstracts
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Data generator base class.
 */
abstract class Generator {
	/**
	 * Maximum number of objects that can be generated in one batch.
	 */
	const MAX_BATCH_SIZE = 100;

	/**
	 * Dimension, in pixels, of generated images.
	 */
	const IMAGE_SIZE = 700;

	/**
	 * Are we ready to generate objects?
	 *
	 * @var bool
	 */
	protected static $ready = false;

	/**
	 * Holds the Faker factory object.
	 *
	 * @var \Faker\Generator Factory object.
	 */
	protected static $faker;

	/**
	 * Caches term IDs.
	 *
	 * @deprecated
	 *
	 * @var array Array of IDs.
	 */
	protected static $term_ids;

	/**
	 * Holds array of generated images to assign to products.
	 *
	 * @var array Array of image attachment IDs.
	 */
	protected static $images = array();

	/**
	 * Return a new object of this object type.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return array
	 */
	abstract public static function generate( $save = true );

	/**
	 * Create multiple objects.
	 *
	 * @param int   $amount Number of objects to create.
	 * @param array $args   Additional args for object creation.
	 *
	 * @return int[]|\WP_Error An array of IDs of created objects on success.
	 */
	// TODO normalize the signature of this method in all generator classes so we can add this to the contract.
	//abstract public static function batch( $amount, array $args = array() );

	/**
	 * Get ready to generate objects.
	 *
	 * This can be run from any generator, but it applies to all generators.
	 *
	 * @return void
	 */
	protected static function maybe_initialize_generators() {
		if ( true !== self::$ready ) {
			self::init_faker();
			self::disable_emails();

			// Set this to avoid notices as when you run via WP-CLI SERVER vars are not set, order emails uses this variable.
			if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
				$_SERVER['SERVER_NAME'] = 'localhost';
			}
		}

		self::$ready = true;
	}

	/**
	 * Create and store an instance of the Faker library.
	 */
	protected static function init_faker() {
		if ( ! self::$faker ) {
			self::$faker = \Faker\Factory::create( 'en_US' );
			self::$faker->addProvider( new \Bezhanov\Faker\Provider\Commerce( self::$faker ) );
		}
	}

	/**
	 * Disable sending WooCommerce emails when generating objects.
	 *
	 * This needs to run as late in the request as possible so that the callbacks we want to remove
	 * have actually been added.
	 *
	 * @return void
	 */
	public static function disable_emails() {
		$email_actions = array(
			// Customer emails.
			'woocommerce_new_customer_note',
			'woocommerce_created_customer',
			// Order emails.
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_pending_to_failed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_failed_to_on-hold',
			'woocommerce_order_status_cancelled_to_processing',
			'woocommerce_order_status_cancelled_to_completed',
			'woocommerce_order_status_cancelled_to_on-hold',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_on-hold_to_cancelled',
			'woocommerce_order_status_on-hold_to_failed',
			'woocommerce_order_status_completed',
			'woocommerce_order_status_failed',
			'woocommerce_order_fully_refunded',
			'woocommerce_order_partially_refunded',
			// Product emails.
			'woocommerce_low_stock',
			'woocommerce_no_stock',
			'woocommerce_product_on_backorder',
		);

		foreach ( $email_actions as $action ) {
			remove_action( $action, array( 'WC_Emails', 'send_transactional_email' ) );
		}

		if ( ! has_action( 'woocommerce_allow_send_queued_transactional_email', '__return_false' ) ) {
			add_action( 'woocommerce_allow_send_queued_transactional_email', '__return_false' );
		}
	}

	/**
	 * Validate the value of the amount input for a batch command.
	 *
	 * @param int $amount The number of items to create in a batch.
	 *
	 * @return mixed|\WP_Error
	 */
	protected static function validate_batch_amount( $amount ) {
		$amount = filter_var(
			$amount,
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
					'max_range' => static::MAX_BATCH_SIZE,
				),
			)
		);

		if ( false === $amount ) {
			return new \WP_Error(
				'smoothgenerator_batch_invalid_amount',
				sprintf(
					'Amount must be a number between 1 and %d.',
					static::MAX_BATCH_SIZE
				)
			);
		}

		return $amount;
	}

	/**
	 * Get random term ids.
	 *
	 * @deprecated Use Product::get_term_ids instead.
	 *
	 * @param int    $limit Number of term IDs to get.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name Product name to extract terms from.
	 *
	 * @return array
	 */
	protected static function generate_term_ids( $limit, $taxonomy, $name = '' ) {
		_deprecated_function( __METHOD__, '1.2.2', 'Product::get_term_ids' );

		self::init_faker();

		$term_ids = array();

		if ( ! $limit ) {
			return $term_ids;
		}

		$words       = str_word_count( $name, 1 );
		$extra_terms = str_word_count( self::$faker->department( $limit ), 1 );
		$words       = array_merge( $words, $extra_terms );

		if ( 'product_cat' === $taxonomy ) {
			$terms = array_slice( $words, 1 );
		} else {
			$terms = array_merge( self::$faker->words( $limit ), array( strtolower( $words[0] ) ) );
		}

		foreach ( $terms as $term ) {
			if ( isset( self::$term_ids[ $taxonomy ], self::$term_ids[ $taxonomy ][ $term ] ) ) {
				$term_id    = self::$term_ids[ $taxonomy ][ $term ];
				$term_ids[] = $term_id;

				continue;
			}

			$term_id = 0;
			$args    = array(
				'taxonomy' => $taxonomy,
				'name'     => $term,
			);

			$existing = get_terms( $args );

			if ( $existing && count( $existing ) && ! is_wp_error( $existing ) ) {
				$term_id = $existing[0]->term_id;
			} else {
				$term_ob = wp_insert_term( $term, $taxonomy, $args );

				if ( $term_ob && ! is_wp_error( $term_ob ) ) {
					$term_id = $term_ob['term_id'];
				}
			}

			if ( $term_id ) {
				$term_ids[]                           = $term_id;
				self::$term_ids[ $taxonomy ][ $term ] = $term_id;
			}
		}

		return $term_ids;
	}

	/**
	 * Create/retrieve a set of random images to assign to products.
	 *
	 * @param integer $amount Number of images required.
	 */
	public static function seed_images( $amount = 10 ) {
		self::$images = get_posts(
			array(
				'post_type'      => 'attachment',
				'fields'         => 'ids',
				'parent'         => 0,
				'posts_per_page' => $amount,
				'exclude'        => get_option( 'woocommerce_placeholder_image', 0 ),
			)
		);

		$found_count = count( self::$images );

		for ( $i = 1; $i <= ( $amount - $found_count ); $i++ ) {
			self::$images[] = self::generate_image();
		}
	}

	/**
	 * Get an image at random from our seeded data.
	 *
	 * @return int
	 */
	protected static function get_image() {
		if ( ! self::$images ) {
			self::seed_images();
		}
		return self::$images[ array_rand( self::$images ) ];
	}

	/**
	 * Generate and upload a random image, or choose an existing attachment.
	 *
	 * @param string $seed Seed for image generation.
	 * @return int The attachment id of the image (0 on failure).
	 */
	protected static function generate_image( $seed = '' ) {
		self::init_faker();

		$attachment_id = 0;

		if ( ! $seed ) {
			$seed = self::$faker->word();
		}

		$seed = sanitize_key( $seed );
		$icon = new \Jdenticon\Identicon();
		$icon->setValue( $seed );
		$icon->setSize( self::IMAGE_SIZE );

		$image = imagecreatefromstring( @$icon->getImageData() ); // phpcs:ignore
		ob_start();
		imagepng( $image );
		$file = ob_get_clean();
		imagedestroy( $image );
		$upload = wp_upload_bits( 'img-' . $seed . '.png', null, $file );

		if ( empty( $upload['error'] ) ) {
			$attachment_id = (int) wp_insert_attachment(
				array(
					'post_title'     => 'img-' . $seed . '.png',
					'post_mime_type' => $upload['type'],
					'post_status'    => 'publish',
					'post_content'   => '',
				),
				$upload['file']
			);
		}

		if ( $attachment_id ) {
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				include_once ABSPATH . 'wp-admin/includes/image.php';
			}
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		}

		return $attachment_id;
	}

	/**
	 * Get a random value from an array based on weight.
	 * Taken from https://stackoverflow.com/questions/445235/generating-random-results-by-weight-in-php
	 *
	 * @param array $weighted_values Array of value => weight options.
	 * @return mixed
	 */
	protected static function random_weighted_element( array $weighted_values ) {
		$rand = wp_rand( 1, (int) array_sum( $weighted_values ) );

		foreach ( $weighted_values as $key => $value ) {
			$rand -= $value;
			if ( $rand <= 0 ) {
				return $key;
			}
		}
	}
}
