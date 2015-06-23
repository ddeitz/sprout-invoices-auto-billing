<?php
/*
Plugin Name: Sprout Invoices Add-on - Auto Payments
Plugin URI: https://sproutapps.co/marketplace/auto-payments/
Description: A way to automatically charge clients via credit cards or bank accounts. Supports Authorize.net CIM.
Author: Sprout Apps
Version: 1
Author URI: https://sproutapps.co
*/

/**
 * Plugin Info for updates
 */
define( 'SA_ADDON_AUTO_BILLING_VERSION', '1' );
define( 'SA_ADDON_AUTO_BILLING_DOWNLOAD_ID', 1111 );
define( 'SA_ADDON_AUTO_BILLING_NAME', 'Sprout Invoices Auto Payments' );
define( 'SA_ADDON_AUTO_BILLING_FILE', __FILE__ );
define( 'SA_ADDON_AUTO_BILLING_PATH', dirname( __FILE__ ) );
define( 'SA_ADDON_AUTO_BILLING_URL', plugins_url( '', __FILE__ ) );

// Load up after SI is loaded.
add_action( 'sprout_invoices_loaded', 'sa_load_auto_billing_addon' );
function sa_load_auto_billing_addon() {
	if ( class_exists( 'SI_Auto_Billing' ) ) {
		return;
	}
	// Controller
	require_once( 'controllers/Auto_Billing.php' );
	require_once( 'controllers/Auto_Billing_AJAX.php' );
	require_once( 'controllers/Auto_Billing_Checkout.php' );
	require_once( 'controllers/Auto_Billing_Clients.php' );

	SI_Auto_Billing::init();
	SI_Auto_Billing_AJAX::init();
	SI_Auto_Billing_Checkout::init();
	SI_Auto_Billing_Client::init();
}

add_action( 'si_payment_processors_loaded', 'sa_load_authnetcim_processor' );
function sa_load_authnetcim_processor() {
	if ( class_exists( 'SI_AuthorizeNet_CIM' ) ) {
		return;
	}
	// Payment Processor
	require_once( 'payment-processors/authorize-net-cim/SA_AuthorizeNet_CIM.php' );
}

if ( ! apply_filters( 'is_bundle_addon', false ) ) {
	if ( SI_DEV ) { error_log( 'not bundled: sa_load_auto_billing_updates' ); }
	// Load up the updater after si is completely loaded
	add_action( 'sprout_invoices_loaded', 'sa_load_auto_billing_updates' );
	function sa_load_auto_billing_updates() {
		if ( class_exists( 'SI_Updates' ) ) {
			require_once( 'inc/sa-updates/SA_Updates.php' );
		}
	}
}
