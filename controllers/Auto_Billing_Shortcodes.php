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

		$payment_profiles = apply_filters( 'si_ab_payment_profiles', $client_id );
		return self::load_addon_view_to_string( 'shortcodes/payments-dashboard', array(
			'client_id' => $client_id,
			'payment_profiles' => $payment_profiles,
			'default_payment_profile_id' => self::get_option_to_charge_client( $client_id ),
			), true );
	}

	public static function blank_dashboard_view() {
		return self::load_addon_view_to_string( 'shortcodes/payments-dashboard-blank', array(), true );
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
	}

}
