<?php
/**
 * Generate taxonomy terms.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Customer data generator.
 */
class Term extends Generator {
	/**
	 * Init faker library.
	 */
	protected static function init_faker() {
		parent::init_faker();
		self::$faker->addProvider( new \Bezhanov\Faker\Provider\Commerce( self::$faker ) );
	}

	/**
	 * Create a new taxonomy term.
	 *
	 * @param bool   $save     Whether to save the new term to the database.
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $parent   ID of parent term.
	 *
	 * @return \WP_Error|\WP_Term
	 */
	public static function generate( $save = true, string $taxonomy = 'product_cat', int $parent = 0 ) {
		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_obj ) {
			return new \WP_Error(
				'smoothgenerator_invalid_taxonomy',
				'The specified taxonomy is invalid.'
			);
		}

		if ( 0 !== $parent && true !== $taxonomy_obj->hierarchical ) {
			return new \WP_Error(
				'smoothgenerator_invalid_term_hierarchy',
				'The specified taxonomy does not support parent terms.'
			);
		}

		self::init_faker();

		$term_name = self::$faker->department( 3 );
		$term_args = array(
			'description' => self::$faker->realTextBetween( 20, 300, 5 ),
		);
		if ( 0 !== $parent ) {
			$term_args['parent'] = $parent;
		}

		$result = wp_insert_term( $term_name, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return get_term( $result['term_id'] );
	}
}
