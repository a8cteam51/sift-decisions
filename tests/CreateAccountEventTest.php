<?php
/**
 * Class CreateAccountEventTest
 *
 * @package Sift_Decisions
 */

require_once 'EventTest.php';

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Test case.
 */
class CreateAccountEventTest extends EventTest {
	/**
	 * Test that the $create_account event is triggered.
	 *
	 * @return void
	 */
	public function test_create_account() {
		$user = wp_create_user( 'testuser', 'password' );

		$this->assertCreateAccountEventTriggered();

		wp_delete_user( $user );
	}

	/**
	 * Assert $create_account event is triggered.
	 *
	 * @return void
	 */
	public static function assertCreateAccountEventTriggered() {
		$events = static::filter_events( [ 'event' => '$create_account' ] );
		static::assertGreaterThanOrEqual( 1, count( $events ) );
	}
}
