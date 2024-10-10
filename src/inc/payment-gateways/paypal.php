<?php declare( strict_types=1 );

add_filter( 'wc_sift_decisions_paypal_payment_gateway_string', fn() => '$paypal' );
