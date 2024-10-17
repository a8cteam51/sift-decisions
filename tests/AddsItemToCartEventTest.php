<?php
/**
 * Class AddsItemToCartEventTest
 *
 * @package Sift_Decisions
 */

require_once 'EventTest.php';

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Test case.
 */
class AddsItemToCartEventTest extends EventTest {
	/**
	 * Test that the $add_item_to_cart event is triggered.
	 *
	 * @return void
	 */
	public function test_adds_item_to_cart() {
		\WC()->cart->add_to_cart( static::$product_id );
		$this->assertAddsItemToCartEventTriggered();
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
				'event'                 => '$add_item_to_cart',
				'properties.$item.$sku' => $product->get_sku(),
			]
		);
		static::assertGreaterThanOrEqual( 1, count( $events ), 'No $add_item_to_cart event found.' );
	}
}
