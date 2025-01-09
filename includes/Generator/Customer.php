<?php
/**
 * Customer data generation.
 *
 * @package SmoothGenerator\Classes
 */

namespace WC\SmoothGenerator\Generator;

/**
 * Customer data generator.
 */
class Customer extends Generator {
	/**
	 * Return a new customer.
	 *
	 * @param bool  $save       Save the object before returning or not.
	 * @param array $assoc_args Arguments passed via the CLI for additional customization.
	 *
	 * @return \WC_Customer|\WP_Error Customer object with data populated.
	 */
	public static function generate( $save = true, array $assoc_args = array() ) {
		parent::maybe_initialize_generators();

		$args = filter_var_array(
			$assoc_args,
			array(
				'country' => array(
					'filter'  => FILTER_VALIDATE_REGEXP,
					'options' => array(
						'regexp'  => '/^[A-Za-z]{2}$/',
						'default' => '',
					),
				),
				'type'    => array(
					'filter'  => FILTER_VALIDATE_REGEXP,
					'options' => array(
						'regexp' => '/^(company|person)$/',
					),
				),
			)
		);

		list( 'country' => $country, 'type' => $type ) = $args;

		$country = CustomerInfo::get_valid_country_code( $country );
		if ( is_wp_error( $country ) ) {
			return $country;
		}

		if ( ! $type ) {
			$type = self::$faker->randomDigit() < 7 ? 'person' : 'company'; // 70% person, 30% company.
		}

		$keys_for_address = array( 'email' );

		$customer_data = array(
			'role' => 'customer',
		);
		switch ( $type ) {
			case 'person':
			default:
				$customer_data       = array_merge( $customer_data, CustomerInfo::generate_person( $country ) );
				$other_customer_data = CustomerInfo::generate_person( $country );
				$keys_for_address[]  = 'first_name';
				$keys_for_address[]  = 'last_name';
				break;

			case 'company':
				$customer_data       = array_merge( $customer_data, CustomerInfo::generate_company( $country ) );
				$other_customer_data = CustomerInfo::generate_company( $country );
				$keys_for_address[]  = 'company';
				break;
		}

		$customer_data['billing'] = array_merge(
			CustomerInfo::generate_address( $country ),
			array_intersect_key( $customer_data, array_fill_keys( $keys_for_address, '' ) )
		);

		$has_shipping = self::$faker->randomDigit() < 5;
		if ( $has_shipping ) {
			$same_shipping = self::$faker->randomDigit() < 5;
			if ( $same_shipping ) {
				$customer_data['shipping'] = $customer_data['billing'];
			} else {
				$customer_data['shipping'] = array_merge(
					CustomerInfo::generate_address( $country ),
					array_intersect_key( $other_customer_data, array_fill_keys( $keys_for_address, '' ) )
				);
			}
		}

		unset( $customer_data['company'], $customer_data['shipping']['email'] );

		foreach ( array( 'billing', 'shipping' ) as $address_type ) {
			if ( isset( $customer_data[ $address_type ] ) ) {
				$address_data = array_combine(
					array_map(
						fn( $line ) => $address_type . '_' . $line,
						array_keys( $customer_data[ $address_type ] )
					),
					array_values( $customer_data[ $address_type ] )
				);

				$customer_data = array_merge( $customer_data, $address_data );
				unset( $customer_data[ $address_type ] );
			}
		}

		$customer = new \WC_Customer();
		$customer->set_props( $customer_data );

		if ( $save ) {
			$customer->save();
		}

		/**
		 * Action: Customer generator returned a new customer.
		 *
		 * @since 1.2.0
		 *
		 * @param \WC_Customer $customer
		 */
		do_action( 'smoothgenerator_customer_generated', $customer );

		return $customer;
	}

	/**
	 * Create multiple customers.
	 *
	 * @param int   $amount The number of customers to create.
	 * @param array $args   Additional args for customer creation.
	 *
	 * @return int[]|\WP_Error
	 */
	public static function batch( $amount, array $args = array() ) {
		$amount = self::validate_batch_amount( $amount );
		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$customer_ids = array();

		for ( $i = 1; $i <= $amount; $i++ ) {
			$customer       = self::generate( true, $args );
			if ( is_wp_error( $customer ) ) {
				return $customer;
			}
			$customer_ids[] = $customer->get_id();
		}

		return $customer_ids;
	}
}
