<?php
namespace WC\SmoothGenerator\Generator;

class Attribute extends Generator {
	/**
	* Generate product attributes with terms.
	*
	* @param bool  $save       Whether to save the attribute.
	* @param array $assoc_args Additional arguments for generation.
	* @return int|WP_Error Attribute ID on success, WP_Error on failure.
	*/
	public static function generate( $save = true, $assoc_args = array() ) {
		parent::maybe_initialize_generators();

		$defaults = array(
			'terms' => 50, // Default 50 terms per attribute.
		);

		$args = wp_parse_args( $assoc_args, $defaults );

		$term_count = filter_var(
			$args['terms'],
			FILTER_VALIDATE_INT,
			array(
				'options' => array(
					'min_range' => 1,
					'max_range' => 500,
				),
			)
		);

		if ( false === $term_count ) {
			return new WP_Error( 'invalid_term_count', 'Term count must be 1-500.' );
		}

		$raw_name = ucfirst( self::$faker->words( self::$faker->numberBetween( 1, 2 ), true ) );
		$slug     = wc_sanitize_taxonomy_name( $raw_name );

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		if ( is_wp_error( $attribute_id ) ) {
			return $attribute_id;
		}

		$taxonomy = wc_attribute_taxonomy_name( $slug );

		register_taxonomy(
			$taxonomy,
			array( 'product' ),
			array(
				'hierarchical' => true,
				'show_ui'      => false,
				'query_var'    => true,
				'rewrite'      => false,
			)
		);

		delete_transient( 'wc_attribute_taxonomies' );

		// Generate terms.
		for ( $i = 0; $i < $term_count; $i++ ) {
			$term_name = ucfirst( self::$faker->words( self::$faker->numberBetween( 1, 3 ), true ) );
			wp_insert_term( $term_name, $taxonomy );
		}

		return $attribute_id;
	}

	/**
	* Generate multiple attributes in batch.
	*
	* @param int   $amount Number of attributes to generate.
	* @param array $args   Additional arguments for generation.
	* @return array|WP_Error Array of attribute IDs on success, WP_Error on failure.
	*/
	public static function batch( $amount, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );

		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$ids = array();

		for ( $i = 1; $i <= $amount; $i++ ) {
			$id = self::generate( true, $args );

			if ( ! is_wp_error( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
