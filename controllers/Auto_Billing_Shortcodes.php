<?php

/**
 * Auto Billing Client Controller
 *
 *
 * @package SI_Auto_Billing_Shortcodes
 */
class SI_Auto_Billing_Shortcodes extends SI_Auto_Billing {
	const SHORTCODE = 'sprout_invoices_payments_dashboard';

	public static function init() {
		do_action( 'sprout_shortcode', self::SHORTCODE, array( __CLASS__, 'dashboard' ) );

		if ( ! is_admin() ) {
			// Enqueue
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_enqueue' ), 20 );
		}

		add_action( 'wp_ajax_si_ap_payment_option_save', array( get_class(), 'manage_payment_options' ) );
		add_action( 'wp_ajax_nopriv_si_ap_payment_option_save', array( get_class(), 'manage_payment_options' ) );
	}

	public static function dashboard( $atts = array() ) {
		do_action( 'sprout_invoices_payments_dashboard' );

		$user_id = 0;
		if ( class_exists( 'SI_Client_Dashboard' ) ) {
			$valid_client_ids = SI_Client_Dashboard::validate_token();
			if ( isset( $_GET[SI_Client_Dashboard::USER_QUERY_ARG] ) && $valid_client_ids ) {
				$user_id = (int) $_GET[SI_Client_Dashboard::USER_QUERY_ARG];
				$client_ids = $valid_client_ids;
			}
		}
		if ( ! $user_id && is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id ) {
			if ( empty( $client_ids ) ) {
				$client_ids = SI_Client::get_clients_by_user( $user_id );
			}
			if ( ! empty( $client_ids ) ) {
				$view = '';
				// show a dashboard for each client associated.
				foreach ( $client_ids as $client_id ) {
					$view .= self::dashboard_view( $client_id );
				}
				return $view;
			}
		}
		// no client associated
		do_action( 'sprout_invoices_payments_dashboard_not_client' );
		return self::blank_dashboard_view();
	}

	public static function dashboard_view( $client_id ) {
		$client = SI_Client::get_instance( $client_id );
		if ( ! is_a( $client, 'SI_Client' ) ) {
			return;
		}

		wp_enqueue_script( 'si_payment_dashboard' );
		wp_enqueue_style( 'si_payment_dashboard' );

		$payment_profiles = SI_AuthorizeNet_CIM::client_payment_profiles( $client_id );
		return self::load_addon_view_to_string( 'shortcodes/payments-dashboard', array(
			'client_id' => $client_id,
			'payment_profiles' => $payment_profiles,
			'default_payment_profile_id' => self::get_option_to_charge_client( $client_id ),
			), true );
	}

	public static function blank_dashboard_view() {
		return self::load_addon_view_to_string( 'shortcodes/payments-dashboard-blank', array(), true );
	}

	//////////
	// AJAX //
	//////////



	public static function manage_payment_options() {

		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => self::__( 'User cannot create an item!' ) ) );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => self::__( 'Not going to fall for it!' ) ) );
		}

		if ( ! isset( $_REQUEST['submission'] ) ) {
			wp_send_json_error( array( 'message' => self::__( 'Missing critical info!' ) ) );
		}

		$submission = wp_parse_args( $_REQUEST['submission'] );

		if ( ! isset( $submission['client_id'] ) ) {
			wp_send_json_error( array( 'message' => self::__( 'Missing critical info!' ) ) );
		}
		$client_id = $submission['client_id'];
		self::clear_option_to_charge_client( $client_id );
		// Selected a preexisting method
		if ( isset( $submission['sa_credit_payment_method'] ) && is_numeric( $submission['sa_credit_payment_method'] ) ) {
			self::save_option_to_charge_client( $client_id, (int) $submission['sa_credit_payment_method'] );
		}
		elseif ( in_array( $submission['sa_credit_payment_method'], array( 'new_credit', 'new_bank' ) ) ) {
			$new_payment_profile_id = '';
			$payment_info = array();
			$profile_id = SI_AuthorizeNet_CIM::get_customer_profile_id( $client_id );
			$method = $submission['sa_credit_payment_method'];

			$payment_info['billing']['first_name'] = $submission['sa_billing_first_name'];
			$payment_info['billing']['last_name'] = $submission['sa_billing_last_name'];
			$payment_info['billing']['street'] = $submission['sa_billing_street'];
			$payment_info['billing']['city'] = $submission['sa_billing_city'];
			$payment_info['billing']['zone'] = $submission['sa_billing_zone'];
			$payment_info['billing']['postal_code'] = $submission['sa_billing_postal_code'];
			$payment_info['billing']['country'] = $submission['sa_billing_country'];

			if ( 'new_credit' === $method ) {
				$payment_info['cc_number'] = $submission['sa_credit_cc_number'];
				$payment_info['cc_expiration_year'] = $submission['sa_credit_cc_expiration_year'];
				$payment_info['cc_expiration_month'] = $submission['sa_credit_cc_expiration_month'];
				$payment_info['cc_cvv'] = $submission['sa_credit_cc_cvv'];
			}
			elseif ( 'new_bank' === $method ) {
				$payment_info['bank_routing'] = $submission['sa_credit_bank_routing'];
				$payment_info['bank_account'] = $submission['sa_credit_bank_account'];
			}

			if ( ! empty( $payment_info ) ) {
				$new_payment_profile_id = SI_AuthorizeNet_CIM::manually_payment_profile( $profile_id, $client_id, $payment_info ); // TODO detach from CIM

				if ( ! is_numeric( $new_payment_profile_id ) ) {
					wp_send_json_error( array( 'message' => $new_payment_profile_id ) );
				}

				self::save_option_to_charge_client( $client_id, $new_payment_profile_id );
				wp_send_json_success( array( 'message' => self::__( 'Payment Profile Created Refreshing Page Now...' ) ) );
			}
		}
	}


	//////////////
	// Enqueue //
	//////////////

	public static function register_resources() {
		wp_register_style( 'si_payment_dashboard', SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/css/si-payment-dashboard.css', array(), self::SI_VERSION );
		wp_register_script( 'si_payment_dashboard', SA_ADDON_AUTO_BILLING_URL . '/resources/front-end/js/si-payment-dashboard.js', array( 'jquery' ), self::SI_VERSION );
	}

	public static function frontend_enqueue() {
		wp_enqueue_style( 'si_payment_dashboard' );
		wp_enqueue_script( 'si_payment_dashboard' );

		wp_localize_script( 'si_payment_dashboard', 'si_js_object', self::get_localized_js() );
	}

}
