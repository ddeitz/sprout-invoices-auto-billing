<?php

/**
 * Auto Billing Checkout Controller
 *
 *
 * @package SI_Auto_Billing_Checkout
 */
class SI_Auto_Billing_Checkout extends SI_Auto_Billing {

	public static function init() {
		add_action( 'si_credit_card_payment_controls', array( __CLASS__, 'ask_to_auto_bill_on_checkout' ) );
		add_action( 'payment_complete', array( __CLASS__, 'process_payment_maybe_save_option' ), 20, 1 );
	}


	public static function ask_to_auto_bill_on_checkout() {
		$selection = array();
		$selection['allow_to_autobill'] = array(
			'type' => 'checkbox',
			'weight' => 10,
			'label' => sprintf( self::__( 'I authorize %s to automatically charge the payment method listed above after each billing cycle.' ), get_option( 'blogname' ) ),
			'default' => true
		);
		sa_form_fields( $selection, 'billing' );
	}

	public static function process_payment_maybe_save_option( SI_Payment $payment ) {
		if ( isset( $_POST['sa_billing_allow_to_autobill'] ) ) {
			$payment_data = $payment->get_data();
			$invoice_id = $payment_data['invoice_id'];
			$payment_profile_id = $payment_data['payment_profile_id'];
			$client_id = self::get_client_id( $invoice_id );
			self::save_option_to_charge_client( $client_id, $payment_profile_id );
		}
	}
}
