<?php
/**
 * Tests for Customer Generator.
 *
 * @package WC\SmoothGenerator\Tests\Generator
 */

namespace WC\SmoothGenerator\Tests\Generator;

use WC\SmoothGenerator\Generator\Customer;
use WP_UnitTestCase;

/**
 * Customer Generator test case.
 */
class CustomerTest extends WP_UnitTestCase {

	/**
	 * Test generating a customer.
	 */
	public function test_generate_customer() {
		$customer = Customer::generate( true );

		$this->assertInstanceOf( \WC_Customer::class, $customer );
		$this->assertTrue( $customer->get_id() > 0 );
	}

	/**
	 * Test customer has billing information.
	 */
	public function test_customer_has_billing_info() {
		$customer = Customer::generate( true );

		$this->assertNotEmpty( $customer->get_billing_first_name() );
		$this->assertNotEmpty( $customer->get_billing_last_name() );
		$this->assertNotEmpty( $customer->get_billing_email() );
		$this->assertNotEmpty( $customer->get_billing_address_1() );
		$this->assertNotEmpty( $customer->get_billing_city() );
		$this->assertNotEmpty( $customer->get_billing_country() );
	}

	/**
	 * Test customer email is valid.
	 */
	public function test_customer_email_valid() {
		$customer = Customer::generate( true );

		$email = $customer->get_billing_email();
		$this->assertNotFalse( filter_var( $email, FILTER_VALIDATE_EMAIL ) );
	}

	/**
	 * Test customer with specific country.
	 */
	public function test_customer_with_specific_country() {
		$customer = Customer::generate( true, array( 'country' => 'US' ) );

		$this->assertEquals( 'US', $customer->get_billing_country() );
	}

	/**
	 * Test customer type person.
	 */
	public function test_customer_type_person() {
		$customer = Customer::generate( true, array( 'type' => 'person' ) );

		$this->assertNotEmpty( $customer->get_billing_first_name() );
		$this->assertNotEmpty( $customer->get_billing_last_name() );
	}

	/**
	 * Test customer type company.
	 */
	public function test_customer_type_company() {
		$customer = Customer::generate( true, array( 'type' => 'company' ) );

		$this->assertNotEmpty( $customer->get_billing_company() );
	}

	/**
	 * Test batch customer generation.
	 */
	public function test_batch_generation() {
		$amount       = 5;
		$customer_ids = Customer::batch( $amount );

		$this->assertIsArray( $customer_ids );
		$this->assertCount( $amount, $customer_ids );

		foreach ( $customer_ids as $customer_id ) {
			$customer = new \WC_Customer( $customer_id );
			$this->assertTrue( $customer->get_id() > 0 );
		}
	}

	/**
	 * Test batch validation.
	 */
	public function test_batch_validation() {
		$result = Customer::batch( 0 );

		$this->assertWPError( $result );
	}

	/**
	 * Test customer action hook is fired.
	 */
	public function test_customer_generated_action_hook() {
		$hook_fired = false;
		$generated_customer = null;

		add_action(
			'smoothgenerator_customer_generated',
			function ( $customer ) use ( &$hook_fired, &$generated_customer ) {
				$hook_fired = true;
				$generated_customer = $customer;
			}
		);

		$customer = Customer::generate( true );

		$this->assertTrue( $hook_fired, 'smoothgenerator_customer_generated action should fire' );
		$this->assertInstanceOf( \WC_Customer::class, $generated_customer );
	}

	/**
	 * Test customer with invalid country code returns error.
	 */
	public function test_customer_with_invalid_country() {
		$customer = Customer::generate( true, array( 'country' => 'INVALID' ) );

		$this->assertWPError( $customer );
	}

	/**
	 * Test customer has phone number.
	 */
	public function test_customer_has_phone() {
		$customer = Customer::generate( true );

		$phone = $customer->get_billing_phone();
		$this->assertNotEmpty( $phone );
	}

	/**
	 * Test customer role is set to customer.
	 */
	public function test_customer_role() {
		$customer = Customer::generate( true );

		$user = get_user_by( 'id', $customer->get_id() );
		$this->assertNotFalse( $user );
		$this->assertTrue( in_array( 'customer', $user->roles, true ) );
	}
}
