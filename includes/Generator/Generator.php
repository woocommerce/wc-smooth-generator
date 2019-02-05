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

	const IMAGE_WIDTH  = 700;
	const IMAGE_HEIGHT = 400;

	/**
	 * Holds the faker factory object.
	 *
	 * @var \Faker\Factory Factory object.
	 */
	protected static $faker;

	/**
	 * Holds array of attachment IDs for reuse.
	 *
	 * @var array Array of IDs.
	 */
	protected static $attachment_ids;

	/**
	 * Holds array of term IDs for reuse.
	 *
	 * @var array Array of IDs.
	 */
	protected static $term_ids;

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
	 * @return array
	 */
	protected static function generate_term_ids( $limit, $taxonomy ) {
		self::init_faker();

		$term_ids = array();

		if ( ! $limit ) {
			return $term_ids;
		}

		$terms = self::$faker->words( $limit );

		foreach ( $terms as $term ) {
			if ( isset( self::$term_ids[ $taxonomy ], self::$term_ids[ $taxonomy ][ $term ] ) ) {
				$term_ids[] = self::$term_ids[ $taxonomy ][ $term ];
				continue;
			}

			$term_id  = 0;
			$existing = get_term_by( 'name', $term, $taxonomy );

			if ( $existing ) {
				$term_id = $existing->term_id;
			} else {
				$term_ob = wp_insert_term( $term, $taxonomy );

				if ( $term_ob && ! is_wp_error( $term_ob ) ) {
					$term_id = $term_ob['term_id'];
				}
			}

			if ( $term_id ) {
				$term_ids[] = $term_id;
				self::$term_ids[ $taxonomy ][ $term ] = $term_id;
			}
		}

		return $term_ids;
	}

	/**
	 * Generate and upload a random image, or choose an existing attachment.
	 *
	 * @param int $parent Parent ID.
	 *
	 * @return int The attachment id of the image (0 on failure).
	 */
	protected static function generate_image( int $parent = 0 ) {
		self::init_faker();

		// Get existing attachments.
		if ( empty( self::$attachment_ids ) ) {
			self::$attachment_ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'posts_per_page' => 200,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);
		}

		// 25% chance to create a new image rather than reuse existing unless there are limited number available.
		$create_new_image = 10 < count( self::$attachment_ids ) ? true : self::$faker->boolean( 25 );

		if ( ! $create_new_image ) {
			$attachment_id = array_rand( self::$attachment_ids );
		} else {
			$image            = @imagecreatetruecolor( self::IMAGE_WIDTH, self::IMAGE_HEIGHT );
			$background_rgb   = self::$faker->rgbColorAsArray;
			$background_color = imagecolorallocate( $image, $background_rgb[0], $background_rgb[1], $background_rgb[2] );
			imagefill( $image, 0, 0, $background_color );
			ob_start();
			imagepng( $image );
			$file = ob_get_clean();
			imagedestroy( $image );

			$name = 'img-' . rand() . '.png';
			$attachment_id = 0;

			// Upload the image.
			$upload = wp_upload_bits( $name, '', $file );

			if ( empty( $upload['error'] ) ) {
				$attachment_id = (int) wp_insert_attachment(
					array(
						'post_title' => $name,
						'post_mime_type' => $upload['type'],
						'post_status' => 'publish',
						'post_content' => '',
					),
					$upload['file']
				);
			}

			if ( $attachment_id ) {
				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					include_once ABSPATH . 'wp-admin/includes/image.php';
				}
				wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
				self::$attachment_ids[] = $attachment_id;
			}
		}

		if ( $parent ) {
			update_post_meta( $parent, '_thumbnail_id', $attachment_id );
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
		$rand = mt_rand( 1, (int) array_sum( $weighted_values ) );

		foreach ( $weighted_values as $key => $value ) {
			$rand -= $value;
			if ( $rand <= 0 ) {
				return $key;
			}
		}
	}
}
