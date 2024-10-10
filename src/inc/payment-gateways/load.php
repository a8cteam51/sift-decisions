<?php declare( strict_types=1 );

add_action(
	'init',
	function() {
		$gateways = \WC()->payment_gateways->get_available_payment_gateways();

		foreach ( $gateways as $gateway ) {
			switch ( $gateway ) {
				case 'stripe':
					require_once __DIR__ . '/lib/stripe.php';
					require_once __DIR__ . '/stripe.php';
					break;
				case 'woocommerce_payments':
					require_once __DIR__ . '/lib/stripe.php';
					require_once __DIR__ . '/woopay.php';
					break;
			}
		}
	}
);