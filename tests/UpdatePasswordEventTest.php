<?php
/**
 * Class UpdatePasswordEventTest
 *
 * @package Sift_Decisions
 */

require_once 'EventTest.php';

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Test case.
 */
class UpdatePasswordEventTest extends EventTest {
	/**
	 * Test that the $update_password event is triggered.
	 *
	 * @return void
	 */
	public function test_set_password() {
		// Arrange
		// - create a user
		$user_id = $this->factory()->user->create();
		$user    = get_user_by( 'ID', $user_id );

		// Act
		// - update the user
		$password = wp_generate_password();
		wp_set_password( $password, $user_id );

		// Assert
		UpdateAccountEventTest::assertNoUpdateAccountEvent( $user_id );
		static::assertUpdatePasswordEvent( $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that the $update_password event is triggered.
	 *
	 * @return void
	 */
	public function test_update_password_event() {
		// Arrange
		// - create a user
		$user_id = $this->factory()->user->create();
		$user    = get_user_by( 'ID', $user_id );

		// Act
		// - update the user
		$password = wp_generate_password();
		// wp_insert_user() will not hash the password on an update, so we'll use wp_update_user().
		wp_update_user(
			[
				'ID'         => $user->ID,
				'user_login' => $user->user_login,
				'user_pass'  => $password,
			]
		);

		// Assert
		// Might as well 😆 (currently only testing wp_insert_user() so this adds another check).
		UpdateAccountEventTest::assertUpdateAccountEvent( $user_id );
		static::assertUpdatePasswordEvent( $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that the $update_password event is NOT triggered.
	 *
	 * @return void
	 */
	public function test_no_update_password_event() {
		// Arrange
		// - create a user
		$user_id = $this->factory()->user->create();
		$user    = get_user_by( 'ID', $user_id );

		// Act
		// - update the user
		$password = wp_generate_password();
		// wp_insert_user() will not hash the password on an update, so we'll use wp_update_user().
		wp_update_user(
			[
				'ID'         => $user->ID,
				'user_login' => $user->user_login,
			]
		);

		// Assert
		// Might as well 😆 (currently only testing wp_insert_user() so this adds another check).
		UpdateAccountEventTest::assertUpdateAccountEvent( $user_id );
		static::assertNoUpdatePasswordEvent( $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Assert $update_password event is triggered.
	 *
	 * @param integer $user_id User ID.
	 *
	 * @return void
	 */
	public static function assertUpdatePasswordEvent( $user_id ) {
		$events = static::filter_events(
			[
				'event'               => '$update_password',
				'properties.$user_id' => $user_id,
			]
		);
		static::assertGreaterThanOrEqual( 1, count( $events ), 'No $update_password event found' );
	}

	/**
	 * Assert $update_password event is triggered.
	 *
	 * @param integer $user_id User ID.
	 *
	 * @return void
	 */
	public static function assertNoUpdatePasswordEvent( $user_id ) {
		$events = static::filter_events(
			[
				'event'               => '$update_password',
				'properties.$user_id' => $user_id,
			]
		);
		static::assertEquals( 0, count( $events ), '$update_password event found' );
	}
}
