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
	 * Available Locales.
	 *
	 * @var array
	 */
	public static $locales = array(
		'en_AU',
		'en_CA',
		'en_GB',
		'en_HK',
		'en_IN',
		'en_NG',
		'en_NZ',
		'en_PH',
		'en_SG',
		'en_UG',
		'en_US',
		'en_ZA',
	);

	/**
	 * Return a new customer.
	 *
	 * @param bool $save Save the object before returning or not.
	 * @return \WC_Customer Customer object with data populated.
	 */
	public static function generate( $save = true ) {
		$faker       = \Faker\Factory::create();
		$email       = $faker->safeEmail();
		$firstname   = $faker->firstName( $faker->randomElement( array( 'male', 'female' ) ) );
		$lastname    = $faker->lastName();
		$company     = $faker->company();
		$address1    = $faker->buildingNumber() . ' ' . $faker->streetName();
		$address2    = $faker->streetAddress();
		$city        = $faker->city();
		$state       = $faker->stateAbbr();
		$postcode    = $faker->postcode();
		$countrycode = $faker->countryCode();
		$phone       = $faker->e164PhoneNumber();
		$customer    = new \WC_Customer();

		$customer->set_props( array(
			'date_created'        => null,
			'date_modified'       => null,
			'email'               => $email,
			'first_name'          => $firstname,
			'last_name'           => $lastname,
			'display_name'        => $firstname,
			'role'                => 'customer',
			'username'            => $faker->userName(),
			'password'            => $faker->password(),
			'billing_first_name'  => $firstname,
			'billing_last_name'   => $lastname,
			'billing_company'     => $company,
			'billing_address_1'   => $address1,
			'billing_address_2'   => $address2,
			'billing_city'        => $city,
			'billing_state'       => $state,
			'billing_postcode'    => $postcode,
			'billing_country'     => $countrycode,
			'billing_email'       => $email,
			'billing_phone'       => $phone,
			'shipping_first_name' => $firstname,
			'shipping_last_name'  => $lastname,
			'shipping_company'    => $company,
			'shipping_address_1'  => $address1,
			'shipping_address_2'  => $address2,
			'shipping_city'       => $city,
			'shipping_state'      => $state,
			'shipping_postcode'   => $postcode,
			'shipping_country'    => $countrycode,
			'is_paying_customer'  => false,
		) );
		if ( $save ) {
			return $customer->save();
		}
		return $customer;
	}

}
