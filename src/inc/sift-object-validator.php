<?php declare( strict_types = 1 );

namespace WPCOMSpecialProjects\SiftDecisions\Sift;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sift object validator.
 */
class SiftObjectValidator {

	/**
	 * This is the main validation function.
	 *
	 * It takes an array of data and a map of validators. (The map is an array of key => callable pairs.)
	 *
	 * @param array $data          The data to validate.
	 * @param array $validator_map The map of validators (key => callable pairs).
	 *
	 * @return true
	 * @throws \Exception If the data is invalid.
	 */
	protected static function validate( $data, array $validator_map ) {
		$default_validators = array(
			'$ip'   => array( __CLASS__, 'validate_ip' ),
			'$time' => 'is_int',
		);
		$validator_map      = array_merge( $default_validators, $validator_map );
		foreach ( $data as $key => $value ) {
			if ( ! isset( $validator_map[ $key ] ) && '$' === $key[0] ) {
				throw new \Exception( esc_html( "Unknown key: $key" ) );
			}
			$validator = $validator_map[ $key ];
			if ( is_callable( $validator ) ) {
				try {
					if ( true !== $validator( $value ) ) {
						throw new \Exception( esc_html( 'validator returned non-true value' ) );
					}
				} catch ( \Exception $e ) {
					throw new \Exception( esc_html( "$key: " . $e->getMessage() ) );
				}
			}
		}
		return true;
	}

	/**
	 * Validate an IP address.
	 *
	 * @param string $value The IP address to validate.
	 *
	 * @return true
	 * @throws \Exception If the IP address is invalid.
	 */
	public static function validate_ip( $value ) {
		if ( ! empty( $value ) && ! is_string( $value ) ) {
			throw new \Exception( 'must be a string' );
		}
		if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_IP ) ) {
			throw new \Exception( 'must be a valid IPv4 or IPv6 address' );
		}
		return true;
	}

	/**
	 * Validate an ID.
	 *
	 * @param string $value The ID to validate.
	 *
	 * @return true
	 * @throws \Exception If the ID is invalid.
	 */
	public static function validate_id( $value ) {
		if ( ! empty( $value ) && ! is_string( $value ) ) {
			throw new \Exception( 'must be a string' );
		}
		// The id's are limited to a-z,A-Z,0-9,=, ., -, _, +, @, :, &, ^, %, !, $
		if ( ! empty( $value ) && ! preg_match( '/^[a-zA-Z0-9=.\-_+@:&^%!$]+$/', $value ) ) {
			throw new \Exception( 'must be limited to a-z,A-Z,0-9,=, ., -, _, +, @, :, &, ^, %, !, $' );
		}
		return true;
	}

	/**
	 * Validate a currency code.
	 *
	 * @param string $value The currency code to validate.
	 *
	 * @return true
	 * @throws \InvalidArgumentException If the currency code is invalid.
	 */
	public static function validate_currency_code( $value ) {
		if ( ! empty( $value ) && ! is_string( $value ) ) {
			throw new \Exception( 'must be a string' );
		}
		// ISO-4217 currency code.
		if ( ! empty( $value ) && ! preg_match( '/^[A-Z]{3}$/', $value ) ) {
			throw new \InvalidArgumentException( 'invalid ISO-4217 currency code' );
		}
		return true;
	}

	/**
	 * Validate an array with a function.
	 *
	 * @param callable $callable_function The function to validate the array items.
	 *
	 * @return callable The usable validation function.
	 */
	public static function validate_array_fn( $callable_function ) {
		return function ( $value ) use ( $callable_function ) {
			if ( ! is_array( $value ) ) {
				throw new \Exception( 'invalid array' );
			}
			foreach ( $value as $item ) {
				if ( true !== $callable_function( $item ) ) {
					throw new \Exception( 'invalid array item' );
				}
			}
			return true;
		};
	}

	/**
	 * Validate an item.
	 *
	 * @param array $value The item to validate.
	 *
	 * @return true
	 * @throws \Exception If the item is invalid.
	 */
	public static function validate_item( $value ) {
		$validator_map = array(
			'$item_id'       => array( __CLASS__, 'validate_id' ),
			'$product_title' => 'is_string',
			'$price'         => 'is_int',
			'$currency_code' => array( __CLASS__, 'validate_currency_code' ),
			'$quantity'      => 'is_int',
			'$upc'           => 'is_string',
			'$sku'           => 'is_string',
			'$isbn'          => 'is_string',
			'$brand'         => 'is_string',
			'$manufacturer'  => 'is_string',
			'$category'      => 'is_string',
			'$tags'          => static::validate_array_fn( 'is_string' ),
			'$color'         => 'is_string',
			'$size'          => 'is_string',
		);
		try {
			// check required fields: $item_id, $product_title, $price
			if ( empty( $value['$item_id'] ) || empty( $value['$product_title'] ) || empty( $value['$price'] ) ) {
				throw new \Exception( 'missing required fields' );
			}
			static::validate( $value, $validator_map );
		} catch ( \Exception $e ) {
			throw new \Exception( 'invalid $item: ' . esc_html( $e->getMessage() ) );
		}
		return true;
	}

	/**
	 * Validate a browser.
	 *
	 * @param array $value The browser to validate.
	 *
	 * @return true
	 * @throws \Exception If the browser is invalid.
	 */
	public static function validate_browser( $value ) {
		$validator_map = array(
			'$user_agent'       => 'is_string',
			'$accept_language'  => 'is_string',
			'$content_language' => 'is_string',
		);
		try {
			static::validate( $value, $validator_map );
		} catch ( \Exception $e ) {
			throw new \Exception( 'invalid browser: ' . esc_html( $e->getMessage() ) );
		}
		return true;
	}

	/**
	 * Validate an ISO 3166 language code.
	 *
	 * @param string $value The ISO 3166 language code to validate.
	 *
	 * @return true
	 * @throws \InvalidArgumentException If the ISO 3166 language code is invalid.
	 */
	public static function validate_ISO3166_language( $value ) { //phpcs:ignore
		// ISO 3166 language code.
		if ( ! empty( $value ) && ! preg_match( '/^[a-z]{2}-[A-Z]{2}$/', $value ) ) {
			throw new \InvalidArgumentException( 'must be valid ISO-3166 format' );
		}
		return true;
	}

	/**
	 * Validate an app.
	 *
	 * @param array $value The app to validate.
	 *
	 * @return true
	 * @throws \Exception If the app is invalid.
	 */
	public static function validate_app( $value ) {
		$validator_map = array(
			'$os'                  => 'is_string',
			'$os_version'          => 'is_string',
			'$device_manufacturer' => 'is_string',
			'$device_model'        => 'is_string',
			'$device_unique_id'    => 'is_string',
			'$app_name'            => 'is_string',
			'$app_version'         => 'is_string',
			'$client_language'     => array( __CLASS__, 'validate_ISO3166_language' ),
		);
		try {
			static::validate( $value, $validator_map );
		} catch ( \Exception $e ) {
			throw new \Exception( 'invalid app: ' . esc_html( $e->getMessage() ) );
		}
		return true;
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $value The email address to validate.
	 *
	 * @return true
	 * @throws \InvalidArgumentException If the email address is invalid.
	 */
	public static function validate_email( $value ) {
		if ( ! empty( $value ) && ! is_string( $value ) ) {
			throw new \Exception( 'must be a string' );
		}
		if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( 'invalid email address' );
		}
		return true;
	}

	/**
	 * Validate a phone number.
	 *
	 * @param string $value The phone number to validate.
	 *
	 * @return true
	 * @throws \InvalidArgumentException If the phone number is invalid.
	 */
	public static function validate_phone_number( $value ) {
		// This one is tricky so we'll just check if it's a string and doesn't contain any letters.
		if ( ! empty( $value ) && ( ! is_string( $value ) || preg_match( '/[a-zA-Z]/', $value ) ) ) {
			throw new \Exception( 'invalid phone number' );
		}
		return true;
	}

	/**
	 * Validate a country code.
	 *
	 * @param string $value The country code to validate.
	 *
	 * @return true
	 * @throws \InvalidArgumentException If the country code is invalid.
	 */
	public static function validate_country_code( $value ) {
		if ( ! empty( $value ) && ! is_string( $value ) ) {
			throw new \Exception( 'must be a string' );
		}
		// ISO 3166 country code.
		if ( ! empty( $value ) && ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
			throw new \Exception( 'must be an ISO 3166 country code' );
		}
		return true;
	}

	/**
	 * Validate an add item to cart event.
	 *
	 * @param array $data The data to validate.
	 *
	 * @return true
	 * @throws \Exception If the data is invalid.
	 */
	public static function validate_add_item_to_cart( $data ) {
		$validator_map = array(
			'$session_id'                => array( __CLASS__, 'validate_id' ),
			'$user_id'                   => array( __CLASS__, 'validate_id' ),
			'$item'                      => array( __CLASS__, 'validate_item' ),
			'$browser'                   => array( __CLASS__, 'validate_browser' ),
			'$app'                       => array( __CLASS__, 'validate_app' ),
			'$brand_name'                => 'is_string',
			'$site_country'              => array( __CLASS__, 'validate_country_code' ),
			'$site_domain'               => 'is_string',
			'$user_email'                => array( __CLASS__, 'validate_email' ),
			'$verification_phone_number' => array( __CLASS__, 'validate_phone_number' ),
		);
		try {
			static::validate( $data, $validator_map );
			// Required fields: $session_id (if $user_id is not present)
			if ( ! isset( $data['$user_id'] ) && empty( $data['$session_id'] ) ) {
				throw new \Exception( 'missing $session_id' );
			}
			if ( ! empty( $data['$app'] ) && ! empty( $data['$browser'] ) ) {
				throw new \Exception( 'Cannot have both $app and $browser' );
			}
		} catch ( \Exception $e ) {
			throw new \Exception( 'Failed to validate $add_item_to_cart event: ' . esc_html( $e->getMessage() ) );
		}
		return true;
	}
}
