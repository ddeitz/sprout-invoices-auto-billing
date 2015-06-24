<?php

/**
 * Auto Billing Controller
 *
 *
 * @package SI_Auto_Billing
 */
class SI_Auto_Billing extends SI_Controller {
	const AUTOBILL_OPTION = 'sc_allow_auto_bill';
	const CHARGE_OPTION = 'sc_allow_charging';
	const RECORD = 'auto_payments';

	public static function init() {
		add_action( 'sa_new_invoice', array( __CLASS__, 'maybe_auto_bill_new_invoice' ) );
		add_action( 'si_recurring_invoice_created', array( __CLASS__, 'maybe_charge_new_recurring_invoice' ) );
	}

	/**
	 * Attempt to charge the client's payment store
	 * @param  int $invoice_id
	 * @param  int $client_id
	 * @return string/int             error message or payment id.
	 */
	public static function attempt_charge_invoice_balance( $invoice_id, $client_id ) {
		$payment_profile_id = self::get_option_to_charge_client( $client_id );
		if ( ! is_numeric( $payment_profile_id ) ) {
			return self::__( 'Client not setup for automatic payments.' );
		}
		$response = SI_AuthorizeNet_CIM::cim_payment( $invoice_id, $payment_profile_id ); // TODO be independent for other processors
		return $response;
	}

	//////////////////
	// Auto billing //
	//////////////////

	public static function maybe_charge_new_recurring_invoice( $invoice_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		self::maybe_auto_bill_new_invoice( $invoice );
	}

	public static function maybe_auto_bill_new_invoice( $invoice ) {
		$invoice_id = $invoice->get_id();
		$client_id = self::get_client_id( $invoice_id );
		if ( ! self::can_auto_bill_client( $client_id ) ) {
			return;
		}
		$response = self::attempt_charge_invoice_balance( $invoice_id, $client_id );
		$record_message = ( ! is_numeric( $response ) ) ?  sc__( 'Auto Payment Failed on Invoice: %s.' ) : sc__( 'Auto Payment Succeeded on Invoice: %s.' );
		do_action( 'si_new_record',
			$response,
			self::RECORD,
			$client_id,
			sprintf( $record_message, (int) $invoice_id ),
			0,
			false
		);
	}

	//////////
	// Meta //
	//////////

	/**
	 * Can the client be auto billed on invoice creation
	 * @param  int $client_id
	 * @return bool
	 */
	public static function can_auto_bill_client( $client_id ) {
		$option = get_post_meta( $client_id, self::AUTOBILL_OPTION, true );
		return (bool) $option;
	}

	public static function set_to_auto_bill_client( $client_id ) {
		update_post_meta( $client_id, self::AUTOBILL_OPTION, true );
	}

	public static function clear_option_to_auto_bill_client( $client_id ) {
		delete_post_meta( $client_id, self::AUTOBILL_OPTION );
	}

	/**
	 * Can the client be charged with a saved payment profile
	 * @param  int $client_id
	 * @return bool
	 */
	public static function can_charge_client( $client_id ) {
		$option = get_post_meta( $client_id, self::CHARGE_OPTION, true );
		return is_numeric( $option );
	}

	public static function get_option_to_charge_client( $client_id ) {
		return get_post_meta( $client_id, self::CHARGE_OPTION, true );
	}

	public static function save_option_to_charge_client( $client_id, $payment_profile_id ) {
		update_post_meta( $client_id, self::CHARGE_OPTION, $payment_profile_id );
	}

	public static function clear_option_to_charge_client( $client_id ) {
		delete_post_meta( $client_id, self::CHARGE_OPTION );
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

	public static function load_addon_view( $view, $args, $allow_theme_override = true ) {
		add_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		$view = self::load_view( $view, $args, $allow_theme_override );
		remove_filter( 'si_views_path', array( __CLASS__, 'addons_view_path' ) );
		return $view;
	}

	public static function load_addon_view_to_string( $view, $args, $allow_theme_override = true ) {
		ob_start();
		self::load_addon_view( $view, $args, $allow_theme_override );
		return ob_get_clean();
	}

	public static function addons_view_path() {
		return SA_ADDON_AUTO_BILLING_PATH . '/views/';
	}
}
