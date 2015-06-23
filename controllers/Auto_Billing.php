<?php

/**
 * Auto Billing Controller
 *
 *
 * @package SI_Auto_Billing
 */
class SI_Auto_Billing extends SI_Controller {
	const AUTOBILL_OPTION = 'sc_allow_auto_bill';

	public static function init() {
		// add_action( 'sa_new_invoice', array( __CLASS__, 'maybe_auto_bill_new_invoice' ) );
		// add_action( 'si_recurring_invoice_created', array( __CLASS__, 'maybe_charge_new_recurring_invoice' ) );
	}

	public static function maybe_auto_bill_new_invoice( $invoice ) {
		$invoice_id = $invoice->get_id();
		$client_id = self::get_client_id( $invoice_id );
		if ( ! self::can_auto_bill_client( $client_id ) ) {
			return;
		}
		$auto_bill = self::get_option_to_auto_bill_client( $client_id );
		do_action( 'si_ab_create_transaction', $invoice_id, $auto_bill );
	}

	public static function maybe_charge_new_recurring_invoice( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		self::maybe_auto_bill_new_invoice( $invoice );
	}


	//////////////
	// Utility //
	//////////////

	public static function get_client_id( $invoice_id = 0 ) {
		$client_id = 0;
		if ( ! $invoice_id && is_single() && SI_Invoice::POST_TYPE === get_post_type( get_the_ID() ) ) {
			$invoice_id = get_the_ID();
		}
		if ( $invoice_id ) {
			$invoice = SI_Invoice::get_instance( $invoice_id );
			if ( is_a( $invoice, 'SI_Invoice' ) ) {
				$client_id = $invoice->get_client_id();
			}
		}
		if ( ! $client_id ) {
			$user_id = get_current_user_id();
			$client_ids = SI_Client::get_clients_by_user( $user_id );
			if ( ! empty( $client_ids ) ) {
				$client_id = array_pop( $client_ids );
			}
		}
		return $client_id;
	}

	public static function can_auto_bill_client( $client_id ) {
		$option = get_post_meta( $client_id, self::AUTOBILL_OPTION );
		return is_numeric( $option );
	}

	public static function get_option_to_auto_bill_client( $client_id ) {
		return get_post_meta( $client_id, self::AUTOBILL_OPTION );
	}

	public static function save_option_to_auto_bill_client( $client_id, $payment_profile_id ) {
		update_post_meta( $client_id, self::AUTOBILL_OPTION, $payment_profile_id );
	}

	public static function clear_option_to_auto_bill_client( $client_id ) {
		delete_post_meta( $client_id, self::AUTOBILL_OPTION );
	}
}
