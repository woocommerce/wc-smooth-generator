<?php
namespace WC\SmoothGenerator\Generator;

/**
 * Customer data generator.
 */
class Customer extends Generator {

	/**
	 * Return a new array of data.
	 *
	 * @return array
	 */
	public function generate() {
		$faker     = Faker\Factory::create();
		$email     = $faker->safeEmail();
		$firstname = $faker->firstName( rand( 'male', 'female' ) );
		$lastname  = $faker->lastName();
		return array(
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
				'company'        => '',
				'address_1'      => '',
				'address_2'      => '',
				'city'           => '',
				'state'          => '',
				'postcode'       => '',
				'country'        => '',
				'email'          => $email,
				'phone'          => '',
			),
			'shipping'           => array(
				'first_name' => $firstname,
				'last_name'  => $lastname,
				'company'        => '',
				'address_1'      => '',
				'address_2'      => '',
				'city'           => '',
				'state'          => '',
				'postcode'       => '',
				'country'        => '',
			),
			'is_paying_customer' => false,
		);
	}

}
