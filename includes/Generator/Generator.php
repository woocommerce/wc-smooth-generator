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

	const IMAGE_SIZE = 700;

	/**
	 * Holds the faker factory object.
	 *
	 * @var \Faker\Factory Factory object.
	 */
	protected static $faker;

	/**
	 * Caches term IDs.
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
	 * Init faker library.
	 */
	protected static function init_faker() {
		if ( ! self::$faker ) {
			self::$faker = \Faker\Factory::create( 'en_US' );
		}
	}

	/**
	 * Get random term ids.
	 *
	 * @param int    $limit Number of term IDs to get.
	 * @param string $taxonomy Taxonomy name.
	 * @param string $name Product name to extract terms from.
	 * @return array
	 */
	protected static function generate_term_ids( $limit, $taxonomy, $name = '' ) {
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
