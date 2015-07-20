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
		$start_due_date = current_time( 'timestamp' ) - apply_filters( 'si_get_overdue_payment_attempt_start', 60 * 60 * 24 * 15 ); // last 15 days
		$recently_overdue = SI_Invoice::get_overdue_invoices( $start_due_date );
		if ( ! empty( $recently_overdue ) ) { // no overdue invoices.
			foreach ( $recently_overdue as $invoice_id ) {
				$attempt_option = get_post_meta( $invoice_id, SI_Auto_Billing_Admin::INVOICE_AUTOBILL_INVOICE, true );
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
	}

}
