<?php declare( strict_types=1 );

/**
 * A class representing a Woo order which allows normalizes order-related data according to expectations in the SIFT API.
 */
class Sift_Order {

	private \WC_Order $wc_order;
	private Payment_Gateway $payment_gateway;
	private $gateway_payment_type;
	private $payment_method_details;
	private $charge_details;

	public function __construct( \WC_Order $wc_order ) {
		$this->wc_order = $wc_order;

		$this->payment_gateway = new Payment_Gateway( $this->wc_order->get_payment_method() );
		$this->payment_method_details = $this->get_payment_method_details_from_order( $this->payment_gateway->get_woo_gateway_id(), $this->wc_order );
		$this->charge_details = $this->get_charge_details_from_order( $this->payment_gateway->get_woo_gateway_id(), $this->wc_order );

	}

	private function get_payment_method_details_from_order() {
		return apply_filters( sprintf( 'wc_sift_decisions_%s_payment_method_details_from_order', $this->payment_gateway->get_woo_gateway_id() ), $this->wc_order );
	}

	private function get_charge_details_from_order() {
		return apply_filters( sprintf( 'wc_sift_decisions_%s_charge_details_from_order', $this->payment_gateway->get_woo_gateway_id() ), $this->wc_order );
	}

	public function get_payment_methods() {
		$order_payment_method = array(
			'$payment_type'    => Payment_Method::get_payment_type_string( $this->payment_gateway, $this->gateway_payment_type ),
			'$payment_gateway' => Payment_Method::get_payment_gateway_string( $this->payment_gateway ),
			'$card_bin' => Payment_Method::get_card_bin( $this->payment_gateway, $this->payment_method_details ),
			'$card_last4' => Payment_Method::get_card_last4( $this->payment_gateway, $this->payment_method_details ),
			'$avs_result_code' => Payment_Method::get_avs_result_code( $this->payment_gateway, $this->charge_details ),
			'$cvv_result_code' => Payment_Method::get_cvv_result_code( $this->payment_gateway, $this->charge_details ),
			'$verification_status' => Payment_Method::get_verification_status( $this->payment_gateway, $this->charge_details ),
			'$routing_number' => Payment_Method::get_routing_number( $this->payment_gateway, $this->charge_details ),
			'$shortened_iban_first6' => Payment_Method::get_shortened_iban_first6( $this->payment_gateway, $this->charge_details ),
			'$shortened_iban_last4' => Payment_Method::get_shortened_iban_last4( $this->payment_gateway, $this->charge_details ),
			'$sepa_direct_debit_mandate' => Payment_Method::get_sepa_direct_debit_mandate( $this->payment_gateway, $this->charge_details ),
			'$decline_reason_code' => Payment_Method::get_decline_reason_code( $this->payment_gateway, $this->charge_details ),
			'$wallet_address' => Payment_Method::get_wallet_address( $this->payment_gateway, $this->charge_details ),
			'$wallet_type' => Payment_Method::get_wallet_type( $this->payment_gateway, $this->charge_details ),
			'$paypal_payer_id' => Payment_Method::get_paypal_payer_id( $this->payment_gateway, $this->charge_details ),
			'$paypal_payer_email' => Payment_Method::get_paypal_payer_email( $this->payment_gateway, $this->charge_details ),
			'$paypal_payer_status' => Payment_Method::get_paypal_payer_status( $this->payment_gateway, $this->charge_details ),
			'$paypal_address_status' => Payment_Method::get_paypal_address_status( $this->payment_gateway, $this->charge_details ),
			'$paypal_protection_eligibility' => Payment_Method::get_paypal_protection_eligibility( $this->payment_gateway, $this->charge_details ),
			'$paypal_payment_status' => Payment_Method::get_paypal_payment_status( $this->payment_gateway, $this->charge_details ),
			'$stripe_cvc_check' => Payment_Method::get_stripe_cvc_check( $this->payment_gateway, $this->charge_details ),
			'$stripe_address_line1_check' => Payment_Method::get_stripe_address_line1_check( $this->payment_gateway, $this->charge_details ),
			'$stripe_address_line2_check' => Payment_Method::get_stripe_address_line2_check( $this->payment_gateway, $this->charge_details ),
			'$stripe_address_zip_check' => Payment_Method::get_stripe_address_zip_check( $this->payment_gateway, $this->charge_details ),
			'$stripe_funding' => Payment_Method::get_stripe_funding( $this->payment_gateway, $this->charge_details ),
			'$stripe_brand' => Payment_Method::get_stripe_brand( $this->payment_gateway, $this->charge_details ),
			'$account_holder_name' => Payment_Method::get_account_holder_name( $this->payment_gateway, $this->charge_details ),
			'$account_number_last5' => Payment_Method::get_account_number_last5( $this->payment_gateway, $this->charge_details ),
			'$bank_name' => Payment_Method::get_bank_name( $this->payment_gateway, $this->charge_details ),
			'$bank_country' => Payment_Method::get_bank_country( $this->payment_gateway, $this->charge_details ),
		);

		return array(
			array_filter( $order_payment_method, fn( $val ) => ! empty( $val ) ),
		);
	}

}