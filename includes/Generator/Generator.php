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
	 * Return a new object of this object type.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return array
	 */
	abstract public static function generate( $save = true );

	/**
	 * Get random term ids.
	 *
	 * @param int    $limit Number of term IDs to get.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	protected static function generate_term_ids( $limit, $taxonomy ) {
		$faker    = \Faker\Factory::create();
		$terms    = $faker->words( $limit );
		$term_ids = array();

		foreach ( $terms as $term ) {
			$existing = get_term_by( 'name', $term, $taxonomy );

			if ( $existing ) {
				$term_ids[] = $existing->term_id;
			} else {
				$term = wp_insert_term( $term, $taxonomy );

				if ( $term && ! is_wp_error( $term ) ) {
					$term_ids[] = $term['term_id'];
				}
			}
		}

		return $term_ids;
	}

	/**
	 * Generate and upload a random image.
	 *
	 * @param int $parent Parent ID.
	 *
	 * @return int The attachment id of the image (0 on failure).
	 */
	protected static function generate_image( int $parent = 0 ) {

		// Build the image.
		$faker            = \Faker\Factory::create();
		$image            = @imagecreatetruecolor( self::IMAGE_WIDTH, self::IMAGE_HEIGHT );
		$background_rgb   = $faker->rgbColorAsArray;
		$background_color = imagecolorallocate( $image, $background_rgb[0], $background_rgb[1], $background_rgb[2] );
		imagefill( $image, 0, 0, $background_color );
		$text_color = imagecolorallocate( $image, 0, 0, 0 );
		imagestring( $image, 5, 0, 0, $faker->emoji, $text_color );
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

			$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			if ( $parent ) {
				update_post_meta( $parent, '_thumbnail_id', $attachment_id );
			}
		}

		return $attachment_id;
	}

	/**
	 * Get a random value from an array based on weight.
	 * Taken from https://stackoverflow.com/questions/445235/generating-random-results-by-weight-in-php
	 *
	 * @param array $weighted_values Array of value => weight options.
	 * @return array
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
