<?php
namespace WC\SmoothGenerator\Generator;

/**
 * Data generator base class.
 */
abstract class Generator {

	const IMAGE_WIDTH = 700;
	const IMAGE_HEIGHT = 400;

	/**
	 * Return a new object of this object type.
	 *
	 * @return array
	 */
	abstract public function generate();

	/**
	 * Get random term ids.
	 *
	 * @param int $limit Number of term IDs to get.
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	protected function generate_term_ids( $limit, $taxonomy ) {
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
	 * @return int The attachment id of the image (0 on failure).
	 */
	protected function generate_image( int $parent = 0 ) {

		// Build the image.
		$faker = Faker\Factory::create();
		$image = @imagecreatetruecolor( self::IMAGE_WIDTH, self::IMAGE_HEIGHT );
		$background_rgb = $faker->rgbColorAsArray;
		$background_color = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
		$text_color = imagecolorallocate( $image, 0, 0, 0 );
		imagestring( $image, 5, 0, 0, $faker->emoji, $text_color );
		ob_start();
		imagepng( $image );
		$file = ob_get_clean();
		imagedestroy( $file );

		$name = 'img-' . rand() . '.png';
		$attachment_id = 0;

		// Upload the image.
		$upload = wp_upload_bits( $name, null, $image );
		if ( empty ( $upload['error'] ) ) {
			$attachment_id = (int) wp_insert_attachment(
				array(
					'post_title' => $name,
					'post_mime_type' => $filetype['type'],
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

}


