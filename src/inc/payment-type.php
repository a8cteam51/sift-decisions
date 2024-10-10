<?php declare( strict_types=1 );

class Payment_Type {

	public static function normalize_payment_type_string( string $gateway_id ): string {
		$payment_type = apply_filters( sprintf( 'wc_sift_decisions_%s_payment_type_string', $gateway_id ), '' );
		if ( self::is_valid_payment_type( $payment_type ) ) {
			return $payment_type;
		}
	}

	public static function is_valid_sift_slug( $payment_type ): bool {
		return in_array(
			$payment_type, 
			array(
				'$cash',
				'$check',
				'$credit_card',
				'$crypto_currency',
				'$debit_card',
				'$digital_wallet',
				'$electronic_fund_transfer',
				'$financing',
				'$gift_card',
				'$invoice',
				'$in_app_purchase',
				'$money_order',
				'$points',
				'$prepaid_card',
				'$store_credit',
				'$third_party_processor',
				'$voucher',
				'$sepa_credit',
				'$sepa_instant_credit',
				'$sepa_direct_debit',
				'$ach_credit',
				'$ach_debit',
				'$wire_credit',
				'$wire_debit',
			)
		);
	}

}