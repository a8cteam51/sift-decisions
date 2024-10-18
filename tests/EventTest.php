<?php
/**
 * Class EventTest
 *
 * @package Sift_Decisions
 */

// phpcs:disable Universal.Arrays.DisallowShortArraySyntax.Found

use WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions\Events;

/**
 * Events test case.
 */
abstract class EventTest extends WP_UnitTestCase {

	protected static int $product_id = 0;

	/**
	 * Set up before class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		// Create a product.
		static::$product_id              = static::create_simple_product();
		$_SERVER['HTTP_USER_AGENT']      = 'Test User Agent';
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
	}

	/**
	 * Tear down after class.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		// Delete the product.
		wc_get_product( static::$product_id )->delete( true );
		parent::tear_down_after_class();
	}

	/**
	 * Create a simple product.
	 *
	 * @return integer
	 */
	private static function create_simple_product() {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Test Product' );
		$product->set_regular_price( 10 );
		$product->set_description( 'This is a test product.' );
		$product->set_short_description( 'Short description of the test product.' );
		$product->set_sku( 'test-product' );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->save();

		return $product->get_id();
	}

	/**
	 * Flatten an array to dot notation.
	 *
	 * E.g. ['key' => ['subkey' => 'value']] => ['key.subkey' => 'value']
	 *
	 * @param mixed $multidimensional_array Arbitrary array (most likely a SIFT event).
	 *
	 * @return array
	 */
	private static function array_dot( mixed $multidimensional_array ) {
		$flat = [];
		$it   = new RecursiveIteratorIterator( new RecursiveArrayIterator( $multidimensional_array ) );
		foreach ( $it as $leaf ) {
			$keys = [];
			foreach ( range( 0, $it->getDepth() ) as $depth ) {
				$keys[] = $it->getSubIterator( $depth )->key();
			}
			$flat[ implode( '.', $keys ) ] = $leaf;
		}
		return $flat;
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Events::$to_send = [];
	}

	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	public function tear_down() {
		Events::$to_send = [];
		WC()->cart->empty_cart();
		parent::tear_down();
	}

	/**
	 * Filter events by event type.
	 *
	 * @param array $filters Associative array for filtering.
	 *
	 * @return generator
	 */
	public static function filter_events_gen( $filters = [] ) {
		foreach ( Events::$to_send as $event ) {
			$match = true;
			// flatten the keys to dot notation (e.g. 'key.subkey.subsubkey' => 'value')
			$event = self::array_dot( $event );
			foreach ( $filters as $key => $value ) {
				if ( ! isset( $event[ $key ] ) || $event[ $key ] !== $value ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				yield $event;
			}
		}
	}

	/**
	 * Filter events by event type.
	 *
	 * @param array $filters Associative array for filtering.
	 *
	 * @return array
	 */
	public static function filter_events( $filters = [] ) {
		return iterator_to_array( static::filter_events_gen( $filters ) );
	}
}
