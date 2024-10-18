<?php
/**
 * Class LoginEventTest
 *
 * @package Sift_Decisions
 */

require_once 'EventTest.php';

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Test case.
 */
class LoginEventTest extends EventTest {
	/**
	 * Test that the $login event is triggered (with successful status).
	 *
	 * @return void
	 */
	public function test_login_success() {
		// Arrange
		// - create a user and log them in
		$user_id = $this->factory()->user->create();
		// phpcs:ignore
		$auth_func = fn() => get_user_by( 'ID', $user_id );
		add_filter( 'authenticate', $auth_func, 10, 0 );

		// Act
		wp_signon(
			[
				'user_login'    => get_userdata( $user_id )->user_login,
				'user_password' => 'password',
			]
		);

		// Assert
		static::assertLoginEvent( '$success', $user_id );

		// Clean up
		remove_filter( 'authenticate', $auth_func );
		wp_delete_user( $user_id );
	}

	/**
	 * Test that the $login event is triggered (with failure).
	 *
	 * @return void
	 */
	public function test_login_failure() {
		// Arrange
		// - create a user and log them in
		$user_id = $this->factory()->user->create();
		// phpcs:ignore
		$auth_func = fn() => false;
		add_filter( 'authenticate', $auth_func, 99, 0 );

		// Act
		wp_signon(
			[
				'user_login'    => get_userdata( $user_id )->user_login,
				'user_password' => 'password',
			]
		);

		// Assert
		static::assertLoginEvent( '$failure', $user_id );

		// Clean up
		remove_filter( 'authenticate', $auth_func, 99 );
		wp_delete_user( $user_id );
	}

	/**
	 * Assert $login event is triggered.
	 *
	 * @param string  $login_status Event type ($success|$failure).
	 * @param integer $user_id      User ID.
	 *
	 * @return void
	 */
	public static function assertLoginEvent( $login_status, $user_id ) {
		$events = static::filter_events(
			[
				'event'                    => '$login',
				'properties.$login_status' => $login_status,
				'properties.$user_id'      => $user_id,
			]
		);
		static::assertGreaterThanOrEqual( 1, count( $events ), 'No $login event found' );
	}
}
