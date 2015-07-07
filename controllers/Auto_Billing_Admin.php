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

		// Invoice information option
		add_action( 'doc_information_meta_box_date_row_last', array( __CLASS__, 'add_invoicing_auto_billing_option' ) );
		add_action( 'si_save_line_items_meta_box', array( __CLASS__, 'save_auto_billing_selection' ) );

		// Invoice admin table
		add_action( 'restrict_manage_posts', array( __CLASS__, 'show_invoices_due' ) );
		add_action( 'query_vars', array( __CLASS__, 'filter_admin_table_register_qvs' ) );

		add_action( 'pre_get_posts', array( __CLASS__, 'filter_admin_table_results' ), 100 );
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
					self::_e( 'Paid' );
				}
				else {
					$client_id = $invoice->get_client_id();
					if ( $client_id ) {
						if ( self::can_charge_client( $client_id ) ) {
							printf( '<button class="payment_capture button button-small" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', sprintf( self::__( 'Attempt %s Payment' ), sa_get_formatted_money( $invoice->get_balance() ) ), $id, $client_id );
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
		if ( $screen->id === 'edit-sa_invoice' || $screen->id === 'sa_invoice' ) {
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



	////////////////////
	// Invoices Table //
	////////////////////

	public static function show_invoices_due() {
		global $typenow;
		if ( SI_Invoice::POST_TYPE !== $typenow ) {
			return;
		}
		$checked = ( isset( $_GET['only_auto_payments'] ) && '' !== $_GET['only_auto_payments'] ) ? true : false ;
		printf( '<span class="table_filter_checkout"><label><input %s type="checkbox" name="only_auto_payments" value="1" id="auto_billing_option" />%s</label></span>', checked( $checked, true, false ), self::__( 'Only Auto Payments' ) );

	}

	public static function filter_admin_table_register_qvs( $query_vars ){
		$query_vars[] = 'only_auto_payments';
		return $query_vars;
	}

	public static function filter_admin_table_results( $query ) {
		if ( is_admin() && SI_Invoice::POST_TYPE !== $query->query['post_type'] ) {
			return;
		}
		$only_auto = $query->get( 'only_auto_payments' );
		// Only if this query is set to filter out only auto payments
		if ( ! empty( $only_auto ) ) {

			global $wpdb;
			// Make sure to accommodate the other post__in queries along with.
			$posts_in = $query->get( 'post__in' );
			$and_posts_in = '';
			if ( ! empty( $posts_in ) ) {
				$and_posts_in = sprintf( "AND $wpdb->posts.ID IN ( %s )", implode( ',', array_map( 'absint', $posts_in ) ) );
			}
			// get all the post ids that are auto billable.
			$ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT ID
				FROM $wpdb->posts
				INNER JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id ) WHERE 1=1 
				AND ( ( $wpdb->postmeta.meta_key = '%s' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = true ) )
				AND $wpdb->posts.post_type = %s
				$and_posts_in
				ORDER BY $wpdb->postmeta.meta_value DESC
			", self::INVOICE_AUTOBILL_INVOICE, SI_Invoice::POST_TYPE ) );
			
			// If there are no results don't pass an empty array, otherwise WP will return all.
			if ( empty( $ids ) ) {
				$ids = array( 0 ); 
			}
			// Set to certain posts
			$query->set( 'post__in', $ids );
		}
	}

	/////////////////
	// Meta boxes //
	/////////////////

	public static function add_invoicing_auto_billing_option( $invoice ) {
		if ( ! is_a( $invoice, 'SI_Invoice' ) ) {
			return;
		}
		$option = self::can_auto_bill_invoice( $invoice->get_id() );
		self::load_addon_view( 'admin/meta-boxes/invoices/auto-billing-option.php', array(
				'auto_bill' => $option,
				'invoice_id' => $invoice->get_id(),
				'client_id' => $invoice->get_client_id(),
				'balance' => $invoice->get_balance(),
			), false );
	}

	public static function save_auto_billing_selection( $post_id = 0 ) {
		if ( get_post_type( $post_id ) !== SI_Invoice::POST_TYPE ) {
			return;
		}
		self::clear_option_to_auto_bill_invoice( $post_id );
		if ( ! isset( $_POST['attempt_auto_bill'] ) ) {
			return;
		}
		self::set_to_auto_bill_invoice( $post_id );
	}
}
