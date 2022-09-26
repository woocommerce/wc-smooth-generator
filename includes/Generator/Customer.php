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
	 * @param bool $save Save the object before returning or not.
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

		$firstname   = self::$faker->firstName( self::$faker->randomElement( array( 'male', 'female' ) ) );
		$lastname    = self::$faker->lastName();
		$company     = self::$faker->company();
		$address1    = self::$faker->buildingNumber() . ' ' . self::$faker->streetName();
		$address2    = self::$faker->streetAddress();
		$city        = self::$faker->city();
		$state       = self::$faker->stateAbbr();
		$postcode    = self::$faker->postcode();
		$countrycode = self::$faker->countryCode();
		$phone       = self::$faker->e164PhoneNumber();
		$customer    = new \WC_Customer();

		$customer->set_props( array(
			'date_created'        => null,
			'date_modified'       => null,
			'email'               => $email,
			'first_name'          => $firstname,
			'last_name'           => $lastname,
			'display_name'        => $firstname,
			'role'                => 'customer',
			'username'            => $username,
			'password'            => self::$faker->password(),
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
			$customer->save();
		}

		return $customer;
	}


	/**
	 * Disable sending WooCommerce emails when generating objects.
	 */
	public static function disable_emails() {
		$email_actions = array(
			'woocommerce_new_customer_note',
			'woocommerce_created_customer',
		);

		foreach ( $email_actions as $action ) {
			remove_action( $action, array( 'WC_Emails', 'send_transactional_email' ), 10, 10 );
		}
	}
}
