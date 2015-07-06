<?php

/**
 * Auto Billing Cron Controller
 *
 *
 * @package SI_Auto_Billing_Cron
 */
class SI_Auto_Billing_Cron extends SI_Auto_Billing {

	public static function init() {
		if ( self::DEBUG ) {
			add_action( 'init', array( __CLASS__, 'maybe_process_payments_on_overdue_invoices' ) );
		} else {
			add_action( self::CRON_HOOK, array( __CLASS__, 'maybe_process_payments_on_overdue_invoices' ) );
		}
	}

	public static function maybe_process_payments_on_overdue_invoices() {
		$option_key = 'last_overdue_invoices_payment_attempt_timestamp_v1';
		$last_check = get_option( $option_key, 0 );
		$delay = current_time( 'timestamp' ) - apply_filters( 'si_get_overdue_payment_attempt_delay', 60 * 10 ); // ten minute delay
		if ( $last_check > $delay ) {
			return;
		}
		$recently_overdue = SI_Invoice::get_overdue_invoices( $last_check, $delay );
		if ( ! empty( $recently_overdue ) ) { // no overdue invoices.
			foreach ( $recently_overdue as $invoice_id ) {
				$attempt_option = get_post_meta( $invoice_id, SI_Auto_Billing_Admin::AUTOBILL_OPTION, true );
				if ( ! $attempt_option ) {
					continue;
				}
				$invoice = SI_Invoice::get_instance( $invoice_id );
				if ( $invoice->get_balance() < 0.01 ) {
					continue;
				}
				$client_id = $invoice->get_client_id();
				self::attempt_charge_invoice_balance( $invoice_id, $client_id );
			}
		}
		update_option( $option_key, current_time( 'timestamp' ) );
	}

}
