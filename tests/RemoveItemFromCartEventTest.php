<?php
/**
 * Class RemoveItemFromCartEventTest
 *
 * @package Sift_Decisions
 */

require_once 'EventTest.php';

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Test case.
 */
class RemoveItemFromCartEventTest extends EventTest {
	/**
	 * Test that the $remove_item_from_cart event is triggered.
	 *
	 * @return void
	 */
	public function test_remove_item_from_cart() {
		// Arrange
		$cart_item_key      = '';
		$grab_cart_key_func = function ( $_cart_item_key ) use ( &$cart_item_key ) {
			$cart_item_key = $_cart_item_key;
		};
		add_action( 'woocommerce_add_to_cart', $grab_cart_key_func, 10, 1 );

		// Act
		\WC()->cart->add_to_cart( static::$product_id );
		\WC()->cart->remove_cart_item( $cart_item_key );

		// Assert
		$this->assertAddsItemToCartEventTriggered();

		// Clean up
		remove_action( 'woocommerce_add_to_cart', $grab_cart_key_func );
	}

	/**
	 * Assert $add_item_to_cart event is triggered.
	 *
	 * @param integer $product_id Product ID.
	 *
	 * @return void
	 */
	public static function assertAddsItemToCartEventTriggered( $product_id = null ) {
		$product = wc_get_product( $product_id ?? static::$product_id );
		$events  = static::filter_events(
			[
				'event'                 => '$remove_item_from_cart',
				'properties.$item.$sku' => $product->get_sku(),
			]
		);
		static::assertGreaterThanOrEqual( 1, count( $events ), 'No $remove_item_from_cart event found.' );
	}
}
