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
	public $locales = array(
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
	 * @return WC_Customer Unsaved customer object with data populated.
	 */
	public function generate() {
		$faker       = Faker\Factory::create( array_rand( $this->locales ) );
		$email       = $faker->safeEmail();
		$firstname   = $faker->firstName( array_rand( array( 'male', 'female' ) ) );
		$lastname    = $faker->lastName();
		$company     = $faker->company();
		$address1    = $faker->buildingNumber() . ' ' . $faker->streetAddress();
		$address2    = $faker->streetAddress();
		$city        = $faker->city();
		$state       = $faker->stateAbbr();
		$postcode    = $faker->postcode();
		$countryCode = $faker->countryCode();
		$phone       = $faker->e164PhoneNumber();
		$customer    = new WC_Customer();

		$customer->set_props( array(
			'date_created'       => null,
			'date_modified'      => null,
			'email'              => $email,
			'first_name'         => $firstname,
			'last_name'          => $lastname,
			'display_name'       => $firstname,
			'role'               => 'customer',
			'username'           => '',
			'billing'            => array(
				'first_name' => $firstname,
				'last_name'  => $lastname,
				'company'    => $company,
				'address_1'  => $address1,
				'address_2'  => $address2,
				'city'       => $city,
				'state'      => $state,
				'postcode'   => $postcode,
				'country'    => $countrycode,
				'email'      => $email,
				'phone'      => $phone,
			),
			'shipping'           => array(
				'first_name' => $firstname,
				'last_name'  => $lastname,
				'company'    => $company,
				'address_1'  => $address1,
				'address_2'  => $address2,
				'city'       => $city,
				'state'      => $state,
				'postcode'   => $postcode,
				'country'    => $countrycode,
			),
			'is_paying_customer' => false,
		) );
		return $customer;
	}

}
