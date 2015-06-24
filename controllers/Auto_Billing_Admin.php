<?php

/**
 * Auto Billing Admin Controller
 *
 *
 * @package SI_Auto_Billing_Admin
 */
class SI_Auto_Billing_Admin extends SI_Auto_Billing {
	const AJAX_ACTION = 'si_attempt_auto_charge';

	public static function init() {

		// Admin columns
		add_filter( 'manage_edit-'.SI_Invoice::POST_TYPE.'_columns', array( __CLASS__, 'register_columns' ) );
		add_filter( 'manage_'.SI_Invoice::POST_TYPE.'_posts_custom_column', array( __CLASS__, 'column_display' ), 10, 2 );

		// admin ajax
		add_action( 'admin_head', array( __CLASS__, 'print_admin_js' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( get_class(), 'attempt_charge' ) );
	}

	public static function attempt_charge() {

		if ( ! current_user_can( 'publish_sprout_invoices' ) ) {
			wp_send_json_error( array( 'message' => self::__( 'User cannot create an item!' ) ) );
		}

		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => self::__( 'Not going to fall for it!' ) ) );
		}

		if ( ! isset( $_REQUEST['invoice_id'] ) || ! isset( $_REQUEST['client_id'] ) ) {
			wp_send_json_error( array( 'message' => self::__( 'Missing critical info!' ) ) );
		}

		// attempt the charge
		$invoice_id = $_REQUEST['invoice_id'];
		$client_id = $_REQUEST['client_id'];
		$response = self::attempt_charge_invoice_balance( $invoice_id, $client_id );
		if ( ! is_numeric( $response ) ) {
			wp_send_json_error( array( 'message' => $response ) );
		}

		wp_send_json_success( array( 'message' => sprintf( self::__( 'Payment Successful: #%s' ), $response ) ) );
	}

	public static function register_columns( $columns ) {
		$columns['payment_collections'] = '<span class="dashicons dashicons-money"></span>';
		return $columns;
	}

	public static function column_display( $column_name, $id ) {
		$invoice = SI_Invoice::get_instance( $id );

		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		switch ( $column_name ) {
			case 'payment_collections':
				if ( $invoice->get_balance() < 0.01 ) {
					// self::_e( 'Paid' );
				}
				else {
					$client_id = $invoice->get_client_id();
					if ( $client_id ) {
						if ( self::can_charge_client( $client_id ) ) {
							printf( '<button class="payment_capture button button-small" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', self::__( 'Attempt Payment' ), $id, $client_id );
						}
						else {
							self::_e( 'Not setup or accepted' );
						}
					}
				}
			break;

			default:
			break;
		}

	}

	public static function print_admin_js() {
		$screen = get_current_screen();
		if ( $screen->id == 'edit-sa_invoice' ) {
			?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery('.payment_capture').on( 'click', function(event){
							event.preventDefault();
							var $payment_button = jQuery( this ),
								invoice_id = $payment_button.data( 'invoice_id' ),
								client_id = $payment_button.data( 'client_id' ),
								nonce = si_js_object.security;

							$payment_button.after(si_js_object.inline_spinner);
							$payment_button.attr('disabled', true);
							jQuery.post( si_js_object.ajax_url, { action: '<?php echo self::AJAX_ACTION ?>', client_id: client_id, invoice_id: invoice_id, nonce: nonce },
								function( response ) {
									$payment_button.hide();
									jQuery('.spinner').hide();
									console.log( response );
									$payment_button.after( response.data.message );
								}
							);
						});
					});
				</script>
			<?php
		}
	}

}
