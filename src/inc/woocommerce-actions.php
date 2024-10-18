<?php

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace WPCOMSpecialProjects\SiftDecisions\WooCommerce_Actions;

use WC_Order_Item_Product;

/**
 * Class Events
 */
class Events {
	public static $to_send = array();

	/**
	 * Set up the integration hooks for messages we want to send to Sift.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( 'wp_logout', array( static::class, 'logout' ), 100 );
		add_action( 'wp_login', array( static::class, 'login_success' ), 100, 2 );
		add_action( 'wp_login_failed', array( static::class, 'login_failure' ), 100, 2 );
		add_action( 'user_register', array( static::class, 'create_account' ), 100 );
		add_action( 'profile_update', array( static::class, 'update_account' ), 100, 3 );
		add_action( 'wp_set_password', array( static::class, 'update_password' ), 100, 2 );
		add_action( 'woocommerce_add_to_cart', array( static::class, 'add_to_cart' ), 100 );
		add_action( 'woocommerce_remove_cart_item', array( static::class, 'remove_from_cart' ), 100, 2 );

		add_action( 'woocommerce_checkout_order_processed', array( static::class, 'create_order' ), 100, 3 );
		add_action( 'woocommerce_new_order', array( static::class, 'add_session_info' ), 100 );
		add_action( 'woocommerce_order_status_changed', array( static::class, 'change_order_status' ), 100 );
		add_action( 'post_updated', array( static::class, 'update_order' ), 100 );

		/**
		 * This action merged in to WooCommerce and shipped via 8.8.0
		 * https://github.com/woocommerce/woocommerce/pull/45146
		 */
		add_action( 'woocommerce_guest_session_to_user_id', array( static::class, 'link_session_to_user' ), 10, 2 );

		// On shutdown, send any queued events.
		add_action( 'shutdown', array( static::class, 'send' ) );
	}

	/**
	 * Adds logout event
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/logout
	 *
	 * @param string $user_id User ID.
	 *
	 * @return void
	 */
	public static function logout( string $user_id ) {
		self::add(
			'$logout',
			array(
				'$user_id' => $user_id,
				'$browser' => self::get_client_browser(), // alternately, `$app` for details of the app if not a browser.
				'$ip'      => self::get_client_ip(),
				'$time'    => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds the login success event
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/login
	 *
	 * @param string $username Name of the user.
	 * @param object $user     User object.
	 *
	 * @return void
	 */
	public static function login_success( string $username, object $user ) {
		self::add(
			'$login',
			array(
				'$user_id'       => $user->ID,
				'$login_status'  => '$success',
				'$session_id'    => WC()->session->get_customer_unique_id(),
				'$user_email'    => $user->user_email ? $user->user_email : null,
				'$browser'       => self::get_client_browser(), // alternately, `$app` for details of the app if not a browser.
				'$username'      => $username,
				'$account_types' => $user->roles,
				'$ip'            => self::get_client_ip(),
				'$time'          => intval( 1000 * microtime( true ) ),
				// Other optional data like site_country site_domain etc etc.
			)
		);
	}

	/**
	 * Adds the login failure event
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/login
	 *
	 * @param string    $username Username.
	 * @param \WP_Error $error    The error indicating why the login failed.
	 *
	 * @return void
	 */
	public static function login_failure( string $username, \WP_Error $error ) {
		$attempted_user = get_user_by( 'login', $username );

		switch ( $error->get_error_code() ) {
			case 'invalid_email':
			case 'invalid_username':
				$failure_reason = '$account_unknown';
				break;
			case 'incorrect_password':
				$failure_reason = '$wrong_password';
				break;
			case 'spammer_account':
				$failure_reason = '$account_disabled';
				break;
			default:
				// Only other accepted failure reason is $account_suspended... We shouldn't set the failure reason.
				$failure_reason = null;
		}
		$properties = array(
			'$user_id'      => $attempted_user->ID ? $attempted_user->ID : null,
			'$login_status' => '$failure',
			'$session_id'   => WC()->session->get_customer_unique_id(),
			'$browser'      => self::get_client_browser(), // alternately, `$app` for details of the app if not a browser.
			'$username'     => $username,
			'$ip'           => self::get_client_ip(),
			'$time'         => intval( 1000 * microtime( true ) ),
		);

		if ( ! empty( $failure_reason ) ) {
			$properties['$failure_reason'] = $failure_reason;
		}

		self::add( '$login', $properties );
	}

	/**
	 * Adds account creation event
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/create-account
	 *
	 * @param string $user_id User ID.
	 *
	 * @return void
	 */
	public static function create_account( string $user_id ) {
		$user = get_user_by( 'id', $user_id );

		self::add(
			'$create_account',
			array(
				'$user_id'          => $user->ID,
				'$session_id'       => WC()->session->get_customer_unique_id(),
				'$user_email'       => $user->user_email ? $user->user_email : null,
				'$name'             => $user->display_name,
				'$phone'            => $user ? get_user_meta( $user->ID, 'billing_phone', true ) : null,
				// '$referrer_user_id' => ??? -- required for detecting referral fraud, but non-standard to woocommerce.
				// '$payment_methods' => self::get_customer_payment_methods( $user->ID ),
				'$billing_address'  => self::get_customer_address( $user->ID, 'billing' ),
				'$shipping_address' => self::get_customer_address( $user->ID, 'shipping' ),
				'$browser'          => self::get_client_browser(),
				'$account_types'    => $user->roles,
				'$site_domain'      => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country'     => wc_get_base_location()['country'],
				'$ip'               => self::get_client_ip(),
				'$time'             => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds event for an account getting updated
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/update-account
	 *
	 * @param string   $user_id       User's ID.
	 * @param \WP_User $old_user_data The old user data.
	 * @param array    $new_user_data The new user data.
	 *
	 * @return void
	 */
	public static function update_account( string $user_id, ?\WP_User $old_user_data = null, ?array $new_user_data = null ) {
		$user = get_user_by( 'id', $user_id );

		// check if the password changed
		if ( ! empty( $new_user_data['user_pass'] ) && $old_user_data->user_pass !== $new_user_data['user_pass'] ) {
			self::update_password( '', $user_id );
		}

		self::add(
			'$update_account',
			array(
				'$user_id'          => $user->ID,
				'$user_email'       => $user->user_email ? $user->user_email : null,
				'$name'             => $user->display_name,
				'$phone'            => $user ? get_user_meta( $user->ID, 'billing_phone', true ) : null,
				// '$referrer_user_id' => ??? -- required for detecting referral fraud, but non-standard to woocommerce.
				// '$payment_methods' => self::get_customer_payment_methods( $user->ID ),
				'$billing_address'  => self::get_customer_address( $user->ID, 'billing' ),
				'$shipping_address' => self::get_customer_address( $user->ID, 'shipping' ),
				'$browser'          => self::get_client_browser(),
				'$account_types'    => $user->roles,
				'$site_domain'      => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country'     => wc_get_base_location()['country'],
				'$ip'               => self::get_client_ip(),
				'$time'             => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Notification of a password change.
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/update-password
	 *
	 * @param string $new_password The new password in plaintext. Do not use this.
	 * @param string $user_id      User's ID.
	 *
	 * @return void
	 */
	public static function update_password( string $new_password, string $user_id ) {
		// We are immediately setting this to null, so that it is not inadvertently shared or disclosed.
		$new_password = null;

		$user = get_user_by( 'id', $user_id );

		self::add(
			'$update_password',
			array(
				'$user_id'      => $user->ID,
				'$session_id'   => WC()->session->get_customer_unique_id(),
				'$reason'       => '$user_update', // Can alternately be `$forgot_password` or `$forced_reset` -- no real way to set those yet.
				'$status'       => '$success', // This action only fires after the change is done.
				'$browser'      => self::get_client_browser(),
				'$site_domain'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country' => wc_get_base_location()['country'],
				'$ip'           => self::get_client_ip(),
				'$time'         => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Transition from the prior session id to the user's customer id.
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/link-session-to-user
	 *
	 * @param string $session_id User's former session id.
	 * @param string $user_id    User's id.
	 *
	 * @return void
	 */
	public static function link_session_to_user( string $session_id, string $user_id ) {
		self::add(
			'$link_session_to_user',
			array(
				'$user_id'    => $user_id,
				'$session_id' => $session_id,
				'$ip'         => self::get_client_ip(),
				'$time'       => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds event for item added to cart
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/add-item-to-cart
	 *
	 * @param string $cart_item_key The Cart Key.
	 *
	 * @return void
	 */
	public static function add_to_cart( string $cart_item_key ) {
		$cart_item = \WC()->cart->get_cart_item( $cart_item_key );
		/** @var \WC_Abstract_Legacy_Product $product */
		$product = $cart_item['data'] ?? null;
		$user    = wp_get_current_user();

		if ( ! $product ) {
			return;
		}

		self::add(
			'$add_item_to_cart',
			array(
				'$user_id'      => $user->ID ?? null,
				'$user_email'   => $user->user_email ?? null,
				'$session_id'   => \WC()->session->get_customer_unique_id(),
				'$item'         => array(
					'$item_id'       => $cart_item_key,
					'$sku'           => $product->get_sku(),
					'$product_title' => $product->get_title(),
					'$price'         => $product->get_price() * 1000000, // $39.99
					'$currency_code' => get_woocommerce_currency(),
					'$quantity'      => $cart_item['quantity'],
					'$category'      => wc_get_product_category_list( $product->get_id() ),
					'$tags'          => wp_list_pluck( get_the_terms( $product->get_id(), 'product_tag' ), 'name' ),
				),
				'$browser'      => self::get_client_browser(),
				'$site_domain'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country' => wc_get_base_location()['country'],
				'$ip'           => self::get_client_ip(),
				'$time'         => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds event for item removed from cart
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/remove-item-from-cart
	 *
	 * @param string   $cart_item_key The key of the cart item.
	 * @param \WC_Cart $cart          The WC_Cart object.
	 *
	 * @return void
	 */
	public static function remove_from_cart( string $cart_item_key, \WC_Cart $cart ) {
		$cart_item = $cart->get_cart_item( $cart_item_key );
		$product   = $cart_item['data'];
		$user      = wp_get_current_user();

		self::add(
			'$remove_item_from_cart',
			array(
				'$user_id'      => $user->ID ? $user->ID : null,
				'$user_email'   => $user->user_email ? $user->user_email : null,
				'$session_id'   => \WC()->session->get_customer_unique_id(),
				'$item'         => array(
					'$item_id'       => $product->get_id(),
					'$sku'           => $product->get_sku(),
					'$product_title' => $product->get_title(),
					'$price'         => $product->get_price() * 1000000, // $39.99
					'$currency_code' => get_woocommerce_currency(),
					'$quantity'      => $cart_item['quantity'],
					'$category'      => wc_get_product_category_list( $product->get_id() ),
					'$tags'          => wp_list_pluck( get_the_terms( $product->get_id(), 'product_tag' ), 'name' ),
				),
				'$browser'      => self::get_client_browser(),
				'$site_domain'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country' => wc_get_base_location()['country'],
				'$ip'           => self::get_client_ip(),
				'$time'         => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds event for order creation
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/create-order
	 *
	 * @param string    $order_id    Order id.
	 * @param array     $posted_data The data posted from the checkout form.
	 * @param \WC_Order $order       The Order object.
	 *
	 * @return void
	 */
	public static function create_order( string $order_id, array $posted_data, \WC_Order $order ) {
		$data = $order->get_data();
		$user = wp_get_current_user();

		$physical_or_electronic = '$electronic';
		$items                  = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				// log an error...
				wc_get_logger()->error( sprintf( 'Item not Item Product (order: %d).', $order->get_id() ) );
				continue;
			}
			// Most of this we're basing off return value from `WC_Order_Item_Product::get_product()` as it will return the correct variation.
			$product = $item->get_product();
			if ( empty( $product ) ) {
				// log an error...
				wc_get_logger()->error( sprintf( 'Product not found for order %d.', $order->get_id() ) );
				continue;
			}

			$items[] = array(
				'$item_id'       => $product->get_id(),
				'$sku'           => $product->get_sku(),
				'$product_title' => $product->get_name(),
				'$price'         => $product->get_price() * 1000000, // $39.99
				'$currency_code' => $order->get_currency(), // For the order specifically, not the whole store.
				'$quantity'      => $item->get_quantity(),
				'$category'      => wc_get_product_category_list( $product->get_id() ),
				'$tags'          => wp_list_pluck( get_the_terms( $product->get_id(), 'product_tag' ), 'name' ),
			);

			if ( ! $product->is_virtual() ) {
				$physical_or_electronic = '$physical';
			}
		}

		self::add(
			'$create_order',
			array(
				'$user_id'          => $user->ID ? $user->ID : null,
				'$user_email'       => $order->get_billing_email() ? $order->get_billing_email() : null, // pulling the billing email for the order, NOT customer email
				'$session_id'       => \WC()->session->get_customer_unique_id(),
				'$order_id'         => $order_id,
				'$verification_phone_number'
									=> $order->get_billing_phone(),
				'$amount'           => intval( $order->get_total() * 1000000 ), // Gotta multiply it up to give an integer.
				'$currency_code'    => get_woocommerce_currency(),
				'$billing_address'  => self::get_order_address( $user->ID, 'billing' ),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// '$payment_methods' => array(),
				'$shipping_address' => self::get_order_address( $user->ID, 'shipping' ),
				'$items'            => $items,
				'$shipping_method'  => $physical_or_electronic,
				'$browser'          => self::get_client_browser(),
				'$site_domain'      => wp_parse_url( site_url(), PHP_URL_HOST ),
				'$site_country'     => wc_get_base_location()['country'],
				'$ip'               => self::get_client_ip(),
				'$time'             => intval( 1000 * microtime( true ) ),
			)
		);
	}

	/**
	 * Adds session info to the order.
	 *
	 * Unsure if necessary?  Was in prior plugin. -- George
	 *
	 * @param string $order_id ID of the order.
	 *
	 * @return void
	 */
	public static function add_session_info( string $order_id ) {}

	/**
	 * Adds the event for the order status update
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/order-status
	 *
	 * @param string $order_id Order ID.
	 *
	 * @return void
	 */
	public static function change_order_status( string $order_id ) {}

	/**
	 * Adds event for order update
	 *
	 * @link https://developers.sift.com/docs/curl/events-api/reserved-events/update-order
	 *
	 * @param string $order_id Order ID.
	 *
	 * @return void
	 */
	public static function update_order( string $order_id ) {}

	/**
	 * Enqueue an event to send.  This will enable sending them all at shutdown.
	 *
	 * @param string $event      The event we're recording -- generally will start with a $.
	 * @param array  $properties An array of the data we're passing along to Sift.  Keys will generally start with a $.
	 *
	 * @return void
	 */
	public static function add( string $event, array $properties ) {
		if ( 'localhost' === ( $properties['$site_domain'] ?? '' ) ) {
			$properties['$site_domain'] = 'george-test-local.woocommerce.com';
		}

		array_push(
			self::$to_send,
			array(
				'event'      => $event,
				'properties' => $properties,
			)
		);
	}

	/**
	 * Return how many events have been registered thus far and are queued up to send.
	 *
	 * @return integer
	 */
	public static function count() {
		return count( self::$to_send );
	}

	/**
	 * Send off the events, if any.
	 *
	 * @return boolean
	 */
	public static function send() {
		if ( self::count() > 0 ) {
			$client = \WPCOMSpecialProjects\SiftDecisions\SiftDecisions::get_api_client();
			if ( empty( $client ) ) {
				return false;
			}

			foreach ( self::$to_send as $entry ) {
				// Add in API calls / batching here.

				$response = $client->track( $entry['event'], $entry['properties'] );

				$log_type  = 'debug';
				$log_title = sprintf( 'Sent `%s`', $entry['event'] );

				if ( 200 !== $response->httpStatusCode ) {
					$log_type   = 'error';
					$log_title .= sprintf( ', Error %d: %s', $response->apiStatus, $response->apiErrorMessage );
				}

				wc_get_logger()->log(
					$log_type,
					$log_title,
					array(
						'source'     => 'sift-decisions',
						'properties' => $entry['properties'],
						'response'   => $response,
					)
				);
			}

			// Now that it's sent, clear the $to_send static in case it was run manually.
			self::$to_send = array();

			return true;
		}
		return false;
	}

	/**
	 * Taken from core `get_unsafe_client_ip()` method.
	 *
	 * @return string The detected IP address of the user.
	 */
	public static function get_client_ip() {
		$client_ip = false;

		// In order of preference, with the best ones for this purpose first.
		$address_headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $address_headers as $header ) {
			if ( array_key_exists( $header, $_SERVER ) ) {
				/*
				 * HTTP_X_FORWARDED_FOR can contain a chain of comma-separated
				 * addresses. The first one is the original client. It can't be
				 * trusted for authenticity, but we don't need to for this purpose.
				 */
				$address_chain = explode( ',', $_SERVER[ $header ] );
				$client_ip     = trim( $address_chain[0] );

				break;
			}
		}

		return $client_ip;
	}

	/**
	 * Output the browser details as specified in Sift API docs.
	 *
	 * @return array The user agent, languages accepted, and current store language.
	 */
	public static function get_client_browser() {
		$browser = array(
			'$user_agent'       => $_SERVER['HTTP_USER_AGENT'],
			'$accept_language'  => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
			'$content_language' => get_locale(),
		);

		return $browser;
	}

	/**
	 * Get the address details in the format that Sift expects.
	 *
	 * @param integer $user_id The User / Customer ID.
	 * @param string  $type    Either `billing` or `shipping`.
	 * @param string  $context Either `view` or `edit`.
	 *
	 * @return array|null
	 */
	public static function get_customer_address( int $user_id, string $type = 'billing', string $context = 'view' ) {
		$customer = new \WC_Customer( $user_id );

		switch ( strtolower( $type ) ) {
			case 'billing':
				$address = $customer->get_billing( $context );
				break;
			case 'shipping':
				$address = $customer->get_shipping( $context );
				break;
			default:
				return null;
		}

		// Missing parameters -- company, email (For billing, not shipping)
		return array(
			'$name'      => $address['first_name'] . ' ' . $address['last_name'],
			'company'    => $address['company'],
			'$phone'     => $address['phone'],
			'$address_1' => $address['address_1'],
			'$address_2' => $address['address_2'],
			'$city'      => $address['city'],
			'$region'    => $address['state'],
			'$country'   => $address['country'],
			'$zipcode'   => $address['postcode'],
		);
	}

	/**
	 * Get the address details in the format that Sift expects.
	 *
	 * @param integer $order_id The User / Customer ID.
	 * @param string  $type     Either `billing` or `shipping`.
	 *
	 * @return array|null
	 */
	public static function get_order_address( int $order_id, string $type = 'billing' ) {
		$order = wc_get_order( $order_id );

		if ( empty( $order ) ) {
			return null;
		}

		switch ( strtolower( $type ) ) {
			// WC_Order doesn't have the same `->get_billing` and `->get_shipping()` that the Customer object
			// has, so we call this way instead.  It also assumes `view` context.
			case 'billing':
				$address = $order->get_address( 'billing' );
				break;
			case 'shipping':
				$address = $order->get_address( 'shipping' );
				break;
			default:
				return null;
		}

		// Missing parameters -- company, email (For billing, not shipping)
		return array(
			'$name'      => $address['first_name'] . ' ' . $address['last_name'],
			'company'    => $address['company'],
			'$phone'     => $address['phone'],
			'$address_1' => $address['address_1'],
			'$address_2' => $address['address_2'],
			'$city'      => $address['city'],
			'$region'    => $address['state'],
			'$country'   => $address['country'],
			'$zipcode'   => $address['postcode'],
		);
	}

	/**
	 * Get an array of the customer's payment methods.
	 *
	 * Return data should conform to the expected format described here:
	 * https://developers.sift.com/docs/curl/events-api/complex-field-types/payment-method
	 *
	 * @param integer $user_id The User / Customer ID.
	 *
	 * @return array|null
	 */
	public static function get_customer_payment_methods( int $user_id ) {
		$payment_methods = array();

		/**
		 * Include a filter here for unexpected payment providers to be able to add their results in as well.
		 *
		 * @param array   $payment_methods An array of payment methods.
		 * @param integer $user_id         The User / Customer ID.
		 */
		$payment_methods = apply_filters( 'sift_decisions_get_customer_payment_methods', $payment_methods, $user_id );

		return $payment_methods ?? null;
	}
}
