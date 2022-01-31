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
	 * @param  bool $save Save the object before returning or not.
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function generate( $save = true ) {
		self::init_faker();

		// Make sure a unique username and e-mail are used.
		do {
			$username = self::$faker->userName();
		} while ( username_exists( $username ) );

		do {
			$email = self::$faker->safeEmail();
		} while ( email_exists( $email ) );

		/*PERSON*/
		$person['billing']['firstname'] = self::$faker->firstName( self::$faker->randomElement( array( 'male', 'female' ) ) );
		$person['billing']['lastname']  = self::$faker->lastName();

		if ( self::$faker->randomFloat( 0, 0, 1 ) == 1 ) {
			$person['shipping']['firstname'] = self::$faker->firstName( self::$faker->randomElement( array( 'male', 'female' ) ) );
			$person['shipping']['lastname']  = self::$faker->lastName();
		} else {
			$person['shipping']['firstname'] = $person['billing']['firstname'];
			$person['shipping']['lastname']  = $person['billing']['lastname'];
		}

		/*COMPANY*/
		$company_variations = array( 'B2B', 'C2C', 'C2B', 'B2C' );
		$relationType = self::$faker->randomElements( $company_variations, $count = 1 );

		switch ( $relationType[0] ) {
			case 'B2B':
				$company['billing']['company_name'] = self::$faker->company();
				if ( self::$faker->randomFloat( 0, 0, 1 ) == 1 ) {
					$company['shipping']['company_name'] = self::$faker->company();
				} else {
					$company['shipping']['company_name'] = $company['billing']['company_name'];
				}

				break;
			case 'C2C':
				$company['billing']['company_name']  = '';
				$company['shipping']['company_name'] = '';
				break;
			case 'B2C':
				$company['billing']['company_name']  = self::$faker->company();
				$company['shipping']['company_name'] = '';
				break;
			case 'C2B':
				$company['billing']['company_name']  = '';
				$company['shipping']['company_name'] = self::$faker->company();
				break;
			default:
				break;
		}
		/*ADDRESS*/
		$address['billing']['address0'] = self::$faker->buildingNumber() . ' ' . self::$faker->streetName();
		$address['billing']['address1'] = self::$faker->streetAddress();
		$address['billing']['city']     = self::$faker->city();
		$address['billing']['state']    = self::$faker->stateAbbr();
		$address['billing']['postcode'] = self::$faker->postcode();
		$address['billing']['country']  = self::$faker->countryCode();
		$address['billing']['phone']    = self::$faker->e164PhoneNumber();
		$address['billing']['email']    = $email;

		if ( self::$faker->randomFloat( 0, 0, 1 ) == 1 ) {
			$address['shipping']['address0'] = self::$faker->buildingNumber() . ' ' . self::$faker->streetName();
			$address['shipping']['address1'] = self::$faker->streetAddress();
			$address['shipping']['city']     = self::$faker->city();
			$address['shipping']['state']    = self::$faker->stateAbbr();
			$address['shipping']['postcode'] = self::$faker->postcode();
			$address['shipping']['country']  = self::$faker->countryCode();
		} else {
			$address['shipping']['address0'] = $address['billing']['address0'];
			$address['shipping']['address1'] = $address['billing']['address1'];
			$address['shipping']['city']     = $address['billing']['city'];
			$address['shipping']['state']    = $address['billing']['state'];
			$address['shipping']['postcode'] = $address['billing']['postcode'];
			$address['shipping']['country']  = $address['billing']['country'];
		}

		$customer = new \WC_Customer();

		$customer->set_props(
			array(
				'date_created'        => null,
				'date_modified'       => null,
				'email'               => $email,
				'first_name'          => $person['billing']['firstname'],
				'last_name'           => $person['billing']['lastname'],
				'display_name'        => $person['billing']['firstname'],
				'role'                => 'customer',
				'username'            => $username,
				'password'            => self::$faker->password(),
				'billing_first_name'  => $person['billing']['firstname'],
				'billing_last_name'   => $person['billing']['lastname'],
				'billing_company'     => $company['billing']['company_name'],
				'billing_address_0'   => $address['billing']['address0'],
				'billing_address_1'   => $address['billing']['address1'],
				'billing_city'        => $address['billing']['city'],
				'billing_state'       => $address['billing']['state'],
				'billing_postcode'    => $address['billing']['postcode'],
				'billing_country'     => $address['billing']['country'],
				'billing_email'       => $address['billing']['email'],
				'billing_phone'       => $address['billing']['phone'],
				'shipping_first_name' => $person['shipping']['firstname'],
				'shipping_last_name'  => $person['shipping']['lastname'],
				'shipping_company'    => $company['shipping']['company_name'],
				'shipping_address_0'  => $address['shipping']['address0'],
				'shipping_address_1'  => $address['shipping']['address1'],
				'shipping_city'       => $address['shipping']['city'],
				'shipping_state'      => $address['shipping']['state'],
				'shipping_postcode'   => $address['shipping']['postcode'],
				'shipping_country'    => $address['shipping']['country'],
				'is_paying_customer'  => false,
			)
		);

		if ( $save ) {
			$customer->save();
		}

		return $customer;
	}

}
