<?php declare( strict_types = 1 );

// phpcs:disable

namespace SiftApi;

use WPCOMSpecialProjects\SiftDecisions\Sift\SiftObjectValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class SiftObjectValidatorTest extends \WP_UnitTestCase {
	private static \ArrayObject $data;

	protected static ?string $fixture_name = null;

	/**
	 * Load a JSON fixture.
	 *
	 * @param string $name Fixture name.
	 *
	 * @return array
	 */
	public static function load_json( $name = null ) {
		$name = $name ?? static::$fixture_name ?? '';
		if ( empty( static::$data ) ) {
			$json = file_get_contents( __DIR__ . '/fixtures/' . $name );
			if ( false === $json ) {
				throw new \RuntimeException( 'Failed to load fixture: ' . $name );
			}
			static::$data = new \ArrayObject( json_decode( $json, true ) );
		}
		// Return a copy of static::$data to prevent modification
		return static::$data->getArrayCopy();
	}

	public static function modify_data( $change_data ) {
		$data = static::load_json();
		unset( $data['$browser'] ); // remove the $browser key (broken by default)
		$recursively_fix = function ( $data, $change_data ) use ( &$recursively_fix ) {
			foreach ( $change_data as $key => $value ) {
				if ( is_array( $value ) ) {
					$data[ $key ] = $recursively_fix( $data[ $key ], $value );
				} else {
					$data[ $key ] = $value;
				}
			}
			return $data;
		};
		$data            = $recursively_fix( $data, $change_data );
		return $data;
	}

	protected static function validator( $data ) {
		throw new \RuntimeException( 'No validator set' );
	}

	public static function assert_invalid_argument_exception( $data, $message ) {
		try {
			static::assertTrue( static::validator( $data ) );
			static::fail( 'Invalid event; should throw an exception.' );
		} catch ( \Exception $e ) {
			// compare the error message to ensure it contains the expected message
			static::assertStringContainsString( $message, $e->getMessage() );
		}
	}

	/**
	 * Validate event data.
	 *
	 * @return void
	 */
	public function test_validate_event() {
		$data = static::modify_data( [] );
		try {
			$this->assertTrue( static::validator( $data ) );
		} catch ( \Exception $e ) {
			$this->fail( $e->getMessage() );
		}
	}
}
