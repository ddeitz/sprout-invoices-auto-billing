<?php

/**
 * Auto Billing Client Controller
 *
 *
 * @package SI_Auto_Billing_Client
 */
class SI_Auto_Billing_Client extends SI_Auto_Billing {

	public static function init() {
		if ( is_admin() ) {
			// Add meta box button to auto bill
			add_action( 'admin_init', array( __CLASS__, 'register_meta_boxes' ) );
		}
	}

	public static function register_meta_boxes() {
		$args = array(
			'si_client_auto_billing' => array(
				'title' => si__( 'Auto Billing' ),
				'show_callback' => array( __CLASS__, 'show_billing_meta_box' ),
				'save_callback' => array( __CLASS__, 'save_meta_box_client_billing' ),
				'context' => 'normal',
				'priority' => 'high',
				'save_priority' => 0,
				'weight' => 10,
			)
		);
		do_action( 'sprout_meta_box', $args, SI_Client::POST_TYPE );
	}

	public static function show_billing_meta_box( $post, $metabox ) {
		$client_id = $post->ID;
		$client = SI_Client::get_instance( $post->ID );

		$fields = array();
		$fields['allow_to_autobill'] = array(
			'type' => 'checkbox',
			'weight' => 10,
			'label' => sprintf( self::__( 'Automatically Charge' ) ),
			'description' => self::__( 'Client will be charged automatically after an invoice is <em>created</em>.' ),
			'default' => self::can_auto_bill_client( $client_id ),
		);
		$payment_profiles = apply_filters( 'si_ab_payment_profiles', $client_id );
		self::load_addon_view( 'admin/meta-boxes/clients/auto-billing-meta-box', array(
				'client_id' => $client_id,
				'fields' => $fields,
				'payment_profiles' => $payment_profiles,
				'default_payment_profile_id' => self::get_option_to_charge_client( $client_id ),
			) );

	}

	public static function save_meta_box_client_billing( $post_id, $post, $callback_args ) {
		$client_id = $post_id;
		self::clear_option_to_auto_bill_client( $client_id );
		if ( isset( $_POST['sa_metabox_allow_to_autobill'] ) ) {
			self::set_to_auto_bill_client( $client_id );
		}
		self::clear_option_to_charge_client( $client_id );
		if ( isset( $_POST['sa_credit_payment_method'] ) ) {
			self::save_option_to_charge_client( $client_id, (int) $_POST['sa_credit_payment_method'] );
		}
	}

}
