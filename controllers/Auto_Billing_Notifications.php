<?php

/**
 * Auto Billing Client Controller
 *
 *
 * @package SI_Auto_Billing_Notifications
 */
class SI_Auto_Billing_Notifications extends SI_Notifications {

	public static function init() {
		
		// register notifications
		add_filter( 'sprout_notifications', array( __CLASS__, 'register_notifications' ) );

		//Shortcodes
		add_filter( 'sprout_notification_shortcodes', array( __CLASS__, 'add_notification_shortcode' ), 100 );


		// Hook actions that would send a notification
		self::notification_hooks();

	}


	public static function register_notifications( $notifications = array() ) {
		$default_notifications = array(
				// Lead Generation
				'auto_billing_profile_not_setup' => array(
					'name' => self::__( 'Auto Payments Not Setup' ),
					'description' => self::__( 'Customize the email that is sent to the client when an automatic payment is attempted but their profile is not setup.' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'line_item_table', 'line_item_list', 'line_item_plain_list', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_tax_total', 'invoice_tax', 'invoice_tax2', 'invoice_total', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_deposit_amount', 'invoice_total_due', 'invoice_total_payments', 'client_name' ),
					'default_title' => sprintf( self::__( 'Automatic Payments Not Setup at %s' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_auto_billing_profile_not_setup(),
					'default_disabled' => true,
				),
				'auto_billing_payment_failed' => array(
					'name' => self::__( 'Auto Payments Failed' ),
					'description' => self::__( 'Customize the email that is sent to the client when an automatic payment fails.' ),
					'shortcodes' => array( 'date', 'name', 'username', 'admin_note', 'line_item_table', 'line_item_list', 'line_item_plain_list', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'invoice_issue_date', 'invoice_due_date', 'invoice_past_due_date', 'invoice_po_number', 'invoice_tax_total', 'invoice_tax', 'invoice_tax2', 'invoice_total', 'invoice_subtotal', 'invoice_calculated_total', 'invoice_deposit_amount', 'invoice_total_due', 'invoice_total_payments', 'client_name', 'auto_bill_error' ),
					'default_title' => sprintf( self::__( 'Automatic Payment Recently Failed at %s' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_auto_billing_payment_failed(),
				),
				// Admin Notification
				'admin_auto_billed_payment_failed' => array(
					'name' => self::__( 'Admin Notification: Auto Billing Failed' ),
					'description' => self::__( 'Customize the email that is sent to you when an automatic payment fails.' ),
					'shortcodes' => array( 'date', 'name', 'username', 'invoice_subject', 'invoice_id', 'invoice_edit_url', 'invoice_url', 'client_name', 'client_edit_url', 'auto_bill_error' ),
					'default_title' => sprintf( self::__( '%s: Auto Billing Failed' ), get_bloginfo( 'name' ) ),
					'default_content' => self::default_automatic_billing_failed_to_admin(),
				),
			);
		return array_merge( $notifications, $default_notifications );
	}

	public static function add_notification_shortcode( $default_shortcodes = array() ) {
		$new_shortcodes = array( 
			'auto_bill_error' => array(
				'description' => self::__( 'Used to show the error from the auto billing API response.' ),
				'callback' => array( __CLASS__, 'shortcode_auto_bill_error' )
			)
		);
		return array_merge( $new_shortcodes, $default_shortcodes );
	}

	////////////////////
	// Notifications //
	////////////////////


	/**
	 * Hooks for all notifications
	 * @return
	 */
	private static function notification_hooks() {
		// Notifications can be suppressed
		if ( apply_filters( 'suppress_notifications', false ) ) {
			return;
		}

		// Admin
		add_action( 'sc_ab_attempt_charge_failed', array( __CLASS__, 'failed_payment_notification' ), 10, 4 );
	}

	public static function failed_payment_notification( $payment_profile_id, $payment_id, $invoice_id, $client_id ) {
		if ( ! is_numeric( $payment_profile_id ) ) {
			self::send_client_notification_about_auto_bill_not_setup( $invoice_id, $client_id );
		}
		if ( ! is_numeric( $payment_id ) ) {
			$error = $payment_id;
			self::send_client_notification_about_auto_bill_failed( $error, $invoice_id, $client_id );
			self::send_admin_notification_about_auto_bill_failed( $error, $invoice_id, $client_id );
		}
		return; // notifications sent
	}

	public static function send_client_notification_about_auto_bill_not_setup( $invoice_id, $client_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		// Send notification to all client users
		$client_users = SI_Notifications_Control::get_document_recipients( $invoice );
		foreach ( $client_users as $user_id ) {
			$to = self::get_user_email( $user_id );
			$data = array(
				'user_id' => $user_id,
				'client' => $client,
				'invoice' => $invoice,
				'to' => $to,
			);
			self::send_notification( 'auto_billing_profile_not_setup', $data, $to );
		}
	}

	public static function send_client_notification_about_auto_bill_failed( $error, $invoice_id, $client_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		// Send notification to all client users
		$client_users = SI_Notifications_Control::get_document_recipients( $invoice );
		foreach ( $client_users as $user_id ) {
			$to = self::get_user_email( $user_id );
			$data = array(
				'user_id' => $user_id,
				'client' => $client,
				'invoice' => $invoice,
				'error' => $error,
				'to' => $to,
			);
			self::send_notification( 'auto_billing_payment_failed', $data, $to );
		}
	}

	public static function send_admin_notification_about_auto_bill_failed( $error, $invoice_id, $client_id ) {
		$invoice = SI_Invoice::get_instance( $invoice_id );
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}
		$data = array(
			'user_id' => 1,
			'client' => $client,
			'invoice' => $invoice,
			'error' => $error,
		);
		$admin_to = self::admin_email( $data );
		self::send_notification( 'admin_auto_billed_payment_failed', $data, $admin_to );
	}

	///////////////////
	// Notifications //
	///////////////////

	public static function default_auto_billing_profile_not_setup() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			// $path = 'notifications/html/';
		}
		return SI_Auto_Billing::load_addon_view_to_string( $path . 'auto-billed-not-setup', array(
				), true );
	}

	public static function default_auto_billing_payment_failed() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			// $path = 'notifications/html/';
		}
		return SI_Auto_Billing::load_addon_view_to_string( $path . 'auto-billed-failed', array(
				), true );
	}

	public static function default_automatic_billing_failed_to_admin() {
		$path = 'notifications/';
		if ( class_exists( 'SI_HTML_Notifications' ) ) {
			// $path = 'notifications/html/';
		}
		return SI_Auto_Billing::load_addon_view_to_string( $path . 'admin-auto-billing-failed', array(
				), true );
	}

	////////////////
	// Shortcodes //
	////////////////

	public static function shortcode_auto_bill_error( $atts, $content, $code, $data ) {
		if ( ! isset( $data['error'] ) ) {
			return self::__( 'N/A' );
		}
		return stripslashes( $data['error'] );
	}
}