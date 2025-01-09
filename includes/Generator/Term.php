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

		parent::maybe_initialize_generators();

		if ( $taxonomy_obj->hierarchical ) {
			$term_name = ucwords( self::$faker->department( 3 ) );
		} else {
			$term_name = self::random_weighted_element( array(
				self::$faker->lastName()       => 45,
				self::$faker->colorName()      => 35,
				self::$faker->words( 3, true ) => 20,
			) );
			$term_name = strtolower( $term_name );
		}

		$description_size = wp_rand( 20, 260 );

		$term_args = array(
			'description' => self::$faker->realTextBetween( $description_size, $description_size + 40, 4 ),
		);
		if ( 0 !== $parent ) {
			$term_args['parent'] = $parent;
		}

		$result = wp_insert_term( $term_name, $taxonomy, $term_args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term = get_term( $result['term_id'] );

		/**
		 * Action: Term generator returned a new term.
		 *
		 * @since 1.1.0
		 *
		 * @param \WP_Term $term
		 */
		do_action( 'smoothgenerator_term_generated', $term );

		return $term;
	}

	/**
	 * Create multiple terms for a taxonomy.
	 *
	 * @param int    $amount   The number of terms to create.
	 * @param string $taxonomy The taxonomy to assign the terms to.
	 * @param array  $args     Additional args for term creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, $taxonomy, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_obj ) {
			return new \WP_Error(
				'smoothgenerator_term_batch_invalid_taxonomy',
				'The specified taxonomy is invalid.'
			);
		}

		if ( true === $taxonomy_obj->hierarchical ) {
			return self::batch_hierarchical( $amount, $taxonomy, $args );
		}

		$term_ids = array();

		for ( $i = 1; $i <= $amount; $i ++ ) {
			$term = self::generate( true, $taxonomy );
			if ( is_wp_error( $term ) ) {
				if ( 'term_exists' === $term->get_error_code() ) {
					$i --; // Try again.
					continue;
				}

				return $term;
			}
			$term_ids[] = $term->term_id;
		}

		return $term_ids;
	}

	/**
	 * Create multiple terms for a hierarchical taxonomy.
	 *
	 * @param int    $amount   The number of terms to create.
	 * @param string $taxonomy The taxonomy to assign the terms to.
	 * @param array  $args     Additional args for term creation.
	 *   @type int $max_depth The maximum level of hierarchy.
	 *   @type int $parent    ID of a term to be the parent of the generated terms.
	 *
	 * @return int[]|\WP_Error
	 */
	protected static function batch_hierarchical( int $amount, string $taxonomy, array $args = array() ) {
		$defaults = array(
			'max-depth' => 1,
			'parent'    => 0,
		);

		list( 'max-depth' => $max_depth, 'parent' => $parent ) = filter_var_array(
			wp_parse_args( $args, $defaults ),
			array(
				'max-depth' => array(
					'filter'  => FILTER_VALIDATE_INT,
					'options' => array(
						'min_range' => 1,
						'max_range' => 5,
					),
				),
				'parent'    => FILTER_VALIDATE_INT,
			)
		);

		if ( false === $max_depth ) {
			return new \WP_Error(
				'smoothgenerator_term_batch_invalid_max_depth',
				'Max depth must be a number between 1 and 5.'
			);
		}
		if ( false === $parent ) {
			return new \WP_Error(
				'smoothgenerator_term_batch_invalid_parent',
				'Parent must be the ID number of an existing term.'
			);
		}

		$term_ids = array();

		self::init_faker();

		if ( $parent || 1 === $max_depth ) {
			// All terms will be in the same hierarchy level.
			for ( $i = 1; $i <= $amount; $i ++ ) {
				$term = self::generate( true, $taxonomy, $parent );
				if ( is_wp_error( $term ) ) {
					if ( 'term_exists' === $term->get_error_code() ) {
						$i --; // Try again.
						continue;
					}

					return $term;
				}
				$term_ids[] = $term->term_id;
			}
		} else {
			$remaining = $amount;
			$term_max  = 1;
			if ( $amount > 2 ) {
				$term_max = floor( log( $amount ) );
			}
			$levels = array_fill( 1, $max_depth, array() );

			for ( $i = 1; $i <= $max_depth; $i ++ ) {
				if ( 1 === $i ) {
					// Always use the full term max for the top level of the hierarchy.
					for ( $j = 1; $j <= $term_max && $remaining > 0; $j ++ ) {
						$term = self::generate( true, $taxonomy );
						if ( is_wp_error( $term ) ) {
							if ( 'term_exists' === $term->get_error_code() ) {
								$j --; // Try again.
								continue;
							}

							return $term;
						}
						$term_ids[] = $term->term_id;
						$levels[ $i ][] = $term->term_id;
						$remaining --;
					}
				} else {
					// Subsequent hierarchy levels.
					foreach ( $levels[ $i - 1 ] as $term_id ) {
						$tcount = wp_rand( 0, $term_max );

						for ( $j = 1; $j <= $tcount && $remaining > 0; $j ++ ) {
							$term = self::generate( true, $taxonomy, $term_id );
							if ( is_wp_error( $term ) ) {
								if ( 'term_exists' === $term->get_error_code() ) {
									$j --; // Try again.
									continue;
								}

								return $term;
							}
							$term_ids[] = $term->term_id;
							$levels[ $i ][] = $term->term_id;
							$remaining --;
						}
					}
				}
				if ( $i === $max_depth && $remaining > 0 ) {
					// If we haven't generated enough yet, start back at the top level of the hierarchy.
					$i = 0;
				}
			}
		}

		return $term_ids;
	}
}
