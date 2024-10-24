<?php declare( strict_types = 1 );

// phpcs:disable

namespace SiftApi;

use WPCOMSpecialProjects\SiftDecisions\Sift\SiftObjectValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'SiftObjectValidatorTest.php';

class Validate_AddItemToCart_Test extends SiftObjectValidatorTest {
	protected static ?string $fixture_name = 'add-item-to-cart.json';

	protected static function validator( $data ) {
		return SiftObjectValidator::validate_add_item_to_cart( $data );
	}

	public function test_app_browser_set() {
		$data = static::load_json();
		static::assert_invalid_argument_exception(
			$data,
			'Cannot have both $app and $browser'
		);
	}

	public function test_site_country() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$site_country' => 'US1' ] ),
			'$site_country: must be an ISO 3166 country code'
		);
	}

	public function test_verification_phone_number() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$verification_phone_number' => '+1 A' ] ),
			'$verification_phone_number: invalid phone number'
		);
	}

	public function test_ip() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$ip' => 'blah' ] ),
			'$ip: must be a valid IPv4 or IPv6 address'
		);
	}

	public function test_item_tags() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$item' => [ '$tags' => [ 1000 ] ] ] ),
			'$tags: invalid array item'
		);
	}

	public function test_item_currency() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$item' => [ '$currency_code' => 'USD1' ] ] ),
			'invalid ISO-4217 currency code'
		);
	}

	public function test_app_client_language() {
		static::assert_invalid_argument_exception(
			static::modify_data( [ '$app' => [ '$client_language' => 'en_US' ] ] ),
			'$client_language: must be valid ISO-3166 format'
		);
	}
}
