<?php

/**
 * Authorize.net onsite credit card payment processor.
 *
 * @package SI
 * @subpackage Payment SI_Credit_Card_Processors
 */
class SI_AuthorizeNet_CIM extends SI_Credit_Card_Processors {
	const MODE_TEST = 'sandbox';
	const MODE_LIVE = 'live';

	const API_USERNAME_OPTION = 'si_authorize_net_username';
	const API_PASSWORD_OPTION = 'si_authorize_net_password';

	const API_MODE_OPTION = 'si_authorize_net_mode';
	const PAYMENT_METHOD = 'Credit (Authorize.Net CIM)';
	const PAYMENT_SLUG = 'authnet_cim';

	const AJAX_ACTION = 'cim_card_mngt';
	const CLIENT_CIM_HIDDEN_CARDS = 'cim_card_mngt_hidden_v92';
	const CLIENT_META_PROFILE_ID = 'si_authnet_cim_profile_id_v92';

	protected static $instance;
	protected static $cim_request;

	private static $api_mode = self::MODE_TEST;
	private static $api_username = '';
	private static $api_password = '';

	public static function get_instance() {
		if ( ! ( isset( self::$instance ) && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_api_url() {
		if ( self::$api_mode == self::MODE_LIVE ) {
			return self::API_ENDPOINT_LIVE;
		} else {
			return self::API_ENDPOINT_SANDBOX;
		}
	}

	public function get_payment_method() {
		return self::PAYMENT_METHOD;
	}

	public function get_slug() {
		return self::PAYMENT_SLUG;
	}

	public static function register() {
		self::add_payment_processor( __CLASS__, self::__( 'Authorize.net CIM' ) );
	}

	public static function public_name() {
		return self::__( 'Credit Card' );
	}

	public static function checkout_options() {
		$option = array(
			'icons' => array(
				SI_URL . '/resources/front-end/img/visa.png',
				SI_URL . '/resources/front-end/img/mastercard.png',
				SI_URL . '/resources/front-end/img/amex.png',
				SI_URL . '/resources/front-end/img/discover.png' ),
			'label' => self::__( 'Credit Card' ),
			'accepted_cards' => array(
				'visa',
				'mastercard',
				'amex',
				'diners',
				// 'discover',
				'jcb',
				// 'maestro'
				)
			);
		return $option;
	}

	protected function __construct() {
		parent::__construct();
		self::$api_username = get_option( self::API_USERNAME_OPTION, '' );
		self::$api_password = get_option( self::API_PASSWORD_OPTION, '' );
		self::$api_mode = get_option( self::API_MODE_OPTION, self::MODE_TEST );

		if ( is_admin() ) {
			add_action( 'init', array( get_class(), 'register_options') );
		}

		// Remove review pages
		add_filter( 'si_checkout_pages', array( $this, 'remove_review_checkout_page' ) );

		// Checkout template updates
		add_action( 'si_head', array( $this, 'credit_card_cim_js' ) );
		add_filter( 'sa_credit_fields', array( $this, 'add_cim_options' ), 100, 2 );
		add_action( 'si_credit_card_payment_fields', array( $this, 'add_checking_info' ) );

		// Processing checkout
		add_action( 'si_checkout_action_'.SI_Checkouts::PAYMENT_PAGE, array( $this, 'process_payment_page_for_cim' ), 20, 1 );
		add_filter( 'si_validate_credit_card_cc', array( $this, 'maybe_not_check_credit_cards' ), 10, 2 );

		// AJAX callback
		add_action( 'wp_ajax_cim_card_mngt', array( get_class(), 'ajax_cim' ) );

	}

	/**
	 * Load up the library and instantiate into a static object
	 *
	 * @return OBJECT
	 */
	public static function init_authrequest() {
		if ( ! ( isset( self::$cim_request ) && is_a( self::$cim_request, 'AuthorizeNetCIM' ) ) ) {
			if ( ! class_exists( 'AuthorizeNetCIM' ) ) {
				define( 'AUTHORIZENET_API_LOGIN_ID', self::$api_username );
				define( 'AUTHORIZENET_TRANSACTION_KEY', self::$api_password );
				if ( self::$api_mode === self::MODE_TEST ) {
					define( 'AUTHORIZENET_SANDBOX', true );
				}
				require_once 'sdk/autoload.php';
			}
			else {
				do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Authorize Net SDK Loaded from another library', time() );
			}
			self::$cim_request = new AuthorizeNetCIM;
		}
		return self::$cim_request;

	}

	/**
	 * The review page is unnecessary
	 *
	 * @param array   $pages
	 * @return array
	 */
	public function remove_review_checkout_page( $pages ) {
		unset( $pages[SI_Checkouts::REVIEW_PAGE] );
		return $pages;
	}

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_options() {
		// Settings
		$settings = array(
			'si_authorizenet_cim_settings' => array(
				'title' => self::__( 'Authorize.net CIM' ),
				'weight' => 210,
				'tab' => self::get_settings_page( false ),
				'settings' => array(
					self::API_MODE_OPTION => array(
						'label' => self::__( 'Mode' ),
						'option' => array(
							'type' => 'radios',
							'options' => array(
								self::MODE_LIVE => self::__( 'Live' ),
								self::MODE_TEST => self::__( 'Sandbox' ),
								),
							'default' => self::$api_mode
							)
						),
					self::API_USERNAME_OPTION => array(
						'label' => self::__( 'API Login ID' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_USERNAME_OPTION, '' )
							)
						),
					self::API_PASSWORD_OPTION => array(
						'label' => self::__( 'Transaction Key' ),
						'option' => array(
							'type' => 'text',
							'default' => get_option( self::API_PASSWORD_OPTION, '' )
							)
						)
					)
				)
			);
		do_action( 'sprout_settings', $settings, self::SETTINGS_PAGE );
	}

	/**
	 * Process a payment
	 *
	 * @param SI_Checkouts $checkout
	 * @param SI_Invoice $invoice
	 * @return SI_Payment|bool FALSE if the payment failed, otherwise a Payment object
	 */
	public function process_payment( SI_Checkouts $checkout, SI_Invoice $invoice ) {

		self::init_authrequest();

		// Create Profile
		$profile_id = $this->create_profile( $checkout, $invoice );
		if ( ! $profile_id ) {
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - could not create profile id: ', $profile_id );
			return false;
		}

		$payment_profile_id = false;
		$new_profile_created = false;
		// If no CC was submitted determine what the payment profile id was selected, if any.
		if ( ! $payment_profile_id ) {
			// Check if the payment profile id was passed
			if ( isset( $_POST['sa_credit_payment_method'] ) ) {
				$payment_profile_id = $_POST['sa_credit_payment_method'];
			}
			// If the payment profile id wasn't passed check the checkout cache for the cim profile id
			elseif ( isset( $checkout->cache['cim_payment_profile'] ) ) {
				$payment_profile_id = $checkout->cache['cim_payment_profile'];
			}
		}

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - payment_profile_id: ', $payment_profile_id );

		// No payment profile id given
		if ( ! is_numeric( $payment_profile_id ) ) {
			$payment_profile_id = $this->add_payment_profile( $profile_id, $checkout, $invoice );
			$new_profile_created = true;
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - adding payment profile: ', $payment_profile_id );
		}

		if ( ! $payment_profile_id ) {
			self::set_error_messages( 'Payment Error: 3742' );
			return false;
		}

		$transaction_id = 0; // If not reset than a PriorCapture
		$response_array = array();

		// Create AUTHORIZATION/CAPTURE Transaction
		$transaction_response = $this->create_transaction( $profile_id, $payment_profile_id, $checkout, $invoice );
		$transaction_id = $transaction_response->transaction_id;

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create response: ', $transaction_response );

		if ( $transaction_response->response_reason_code != 1 ) {
			$this->set_error_messages( $transaction_response->response_reason_text );
			return false;
		}

		// remove the payment profile if store cc is unchecked
		if ( $new_profile_created ) {
			if ( ! isset( $_POST['sa_credit_store_payment_profile'] ) && ! isset( $checkout->cache['sa_credit_store_payment_profile'] ) ) {
				$this->remove_payment_profile( $payment_profile_id, $invoice->get_id() );
				do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - delete profile response: ', $payment_profile_id );
			}
		}
		// convert the transaction_response object to an array for the payment record
		$transaction_json = json_encode( $transaction_response );
		$transaction = json_decode( $transaction_json, true );

		$payment_id = SI_Payment::new_payment( array(
				'payment_method' => $this->get_payment_method(),
				'invoice' => $invoice->get_id(),
				'amount' => $transaction['amount'],
				'data' => array(
					'transaction_id' => $transaction_id,
					'profile_id' => $profile_id,
					'payment_profile_id' => $payment_profile_id,
					'live' => ( self::$api_mode == self::MODE_LIVE ),
					'api_response' => $transaction,
				),
			), SI_Payment::STATUS_AUTHORIZED );
		if ( ! $payment_id ) {
			return false;
		}

		// Go through the routine and do the authorized actions and then complete.
		$payment = SI_Payment::get_instance( $payment_id );
		do_action( 'payment_authorized', $payment );
		$payment->set_status( SI_Payment::STATUS_COMPLETE );
		do_action( 'payment_complete', $payment );

		return $payment;
	}


	public function create_transaction( $profile_id, $payment_profile_id, SI_Checkouts $checkout, SI_Invoice $invoice ) {

		self::init_authrequest();

		// Vars
		$client = $invoice->get_client();

		$user = si_who_is_paying( $invoice );
		// User email or none
		$user_email = ( $user ) ? $user->user_email : '' ;
		$user_id = ( $user ) ? $user->ID : 0 ;

		// Create Auth & Capture Transaction
		$transaction = new AuthorizeNetTransaction;
		$transaction->amount = si_get_number_format( $invoice->get_balance() );
		$tax_total = si_get_number_format( ( $invoice->get_tax_total() + $invoice->get_tax2_total() ) );
		if ( $tax_total > 0.01 ) {
			$transaction->tax->amount = $tax_total;
		}
		$shipping_total = 0;
		if ( $shipping_total > 0.01 ) {
			$transaction->shipping->amount = $shipping_total;
		}
		$transaction->customerProfileId = $profile_id;
		$transaction->customerPaymentProfileId = $payment_profile_id;
		// $transaction->customerShippingAddressId = $customer_address_id;
		$transaction->order->invoiceNumber = (int)$invoice->get_id();

		$response = self::$cim_request->createCustomerProfileTransaction( 'AuthCapture', $transaction );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - createCustomerProfileTransaction response: ', $response );

		// Error check
		if ( $response->xpath_xml->messages->resultCode == 'Error' ) {
			self::set_error_messages( (string) $response->xpath_xml->messages->message->text );
			return false;
		}

		$transaction_response = $response->getTransactionResponse();
		// $transaction_id = $transaction_response->transaction_id;

		return $transaction_response;
	}



	public function process_payment_page_for_cim( SI_Checkouts $checkout ) {
		if ( isset( $_POST['sa_credit_payment_method'] ) && is_numeric( $_POST['sa_credit_payment_method'] ) ) {
			$checkout->cache['cim_payment_profile'] = $_POST['sa_credit_payment_method'];
		}

		if ( isset( $_POST['sa_credit_store_payment_profile'] ) ) {
			$checkout->cache['sa_credit_store_payment_profile'] = true;
		}

		// Banking options
		if ( isset( $_POST['sa_bank_bank_routing'] ) && '' !== $_POST['sa_bank_bank_routing']  ) {
			$checkout->cache['bank_routing'] = $_POST['sa_bank_bank_routing'];
		}

		if ( isset( $_POST['sa_bank_bank_account'] ) && '' !== $_POST['sa_bank_bank_account'] ) {
			$checkout->cache['bank_account'] = $_POST['sa_bank_bank_account'];
		}

		if ( isset( $_POST['sa_bank_store_payment_profile'] ) ) {
			$checkout->cache['sa_credit_store_payment_profile'] = true;
		}
	}

	public static function maybe_not_check_credit_cards( $valid, SI_Checkouts $checkout ) {
		// previous stored profile
		if ( isset( $_POST['sa_credit_payment_method'] ) && is_numeric( $_POST['sa_credit_payment_method'] ) ) {
			self::clear_messages();
			return true;
		}

		// bank
		if ( isset( $_POST['sa_bank_bank_account'] ) && '' !== $_POST['sa_bank_bank_account'] ) {
			self::clear_messages();
			return true;
		}
		return $valid;
	}

	///////////////////
	// checkout page //
	///////////////////

	public function credit_card_cim_js() { ?>
		<script type="text/javascript" charset="utf-8">
			jQuery(document).ready(function() {
				jQuery('.cim_delete_card').on( 'click', function(event){
					event.preventDefault();
					var $remove_card = jQuery( this );
					var $payment_profile = $remove_card.data( 'ref' );
					var $invoice_id = $remove_card.data( 'invoice-id' );
					jQuery.post( si_js_object.ajax_url, { action: '<?php echo self::AJAX_ACTION ?>', cim_action: 'remove_payment_profile', remove_profile: $payment_profile, invoice_id: $invoice_id },
						function( data ) {
							$remove_card.parent().parent().fadeOut();
							jQuery('[value="new_credit"]').prop( 'checked', true );
						}
					);
				});
				hideBillingFields = function() {
					jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
						jQuery(this).removeAttr( 'required' );
						jQuery(this).attr( 'disabled', true );
					});
					enablePaymentMethods();
					return true;
				};

				showBillingFields = function() {
					jQuery('#billing_cc_fields .sa-form-field-required').find('input, select, textarea').each( function() {
						jQuery(this).attr( 'required', true );
						jQuery(this).removeAttr( 'disabled' );
					});
					enablePaymentMethods();
					return true;
				};

				enablePaymentMethods = function() {
					jQuery('#credit_card_fields [name="sa_credit_payment_method"]').each( function() {
						jQuery(this).removeAttr( 'disabled' );
					});
					return true;
				};

				hideBankFields = function() {
					console.log('hide');
					jQuery('[for="sa_credit_store_payment_profile"]').show().attr( 'required', true );
					jQuery('#sa_credit_cc_number').show().attr( 'required', true );
					jQuery('#sa_credit_cc_name').show().attr( 'required', true );
					jQuery('#sa_credit_cc_expiration_month').show().attr( 'required', true );
					jQuery('#sa_credit_cc_expiration_year').show().attr( 'required', true );
					jQuery('#sa_credit_cc_cvv').show().attr( 'required', true );
					jQuery('#sa_bank_bank_routing').hide().removeAttr( 'required' );
					jQuery('#sa_bank_bank_account').hide().removeAttr( 'required' );
					jQuery('[for="sa_bank_store_payment_profile"]').hide().removeAttr( 'required' );
					return true;
				};

				hideCCFields = function() {
					jQuery('[for="sa_bank_store_payment_profile"]').show().attr( 'required', true );
					jQuery('#sa_bank_bank_routing').show().attr( 'required', true );
					jQuery('#sa_bank_bank_account').show().attr( 'required', true );
					jQuery('#sa_credit_cc_number').hide().removeAttr( 'required' );
					jQuery('#sa_credit_cc_name').hide().removeAttr( 'required' );
					jQuery('#sa_credit_cc_expiration_month').hide().removeAttr( 'required' );
					jQuery('#sa_credit_cc_expiration_year').hide().removeAttr( 'required' );
					jQuery('#sa_credit_cc_cvv').hide().removeAttr( 'required' );
					jQuery('[for="sa_credit_store_payment_profile"]').hide().removeAttr( 'required' );
					return true;
				};

				//hideBillingFields();
				hideBankFields();
				jQuery('[name="sa_credit_payment_method"]').live('change', function(e) {
					var selection = jQuery( this ).val();

					if ( selection === 'new_credit' ) {
						showBillingFields();
						hideBankFields();
					}
					else if ( selection === 'new_bank' ) {
						showBillingFields();
						hideCCFields();
					}
					else {
						hideBillingFields();
					};

				});
			});
		</script>
		<style type="text/css">
			.cim_delete_card span { opacity: .3; } 
			.cim_delete_card:hover span { opacity: 1.0; }
			span.sa-form-field-radio.clearfix {
			    display: block;
			}

			.sa-form-aligned .sa-control-group .sa-form-field-radio label {
			    text-align: left;
			    display: block;
			    width: auto;
			    margin: 0;
			}

			#billing_cc_fields .sa-form-field-radio input {
			    width: 2em;
			}
			#billing_cc_fields input#sa_credit_store_payment_profile, #billing_cc_fields  input#sa_bank_store_payment_profile {
			    width: auto;
			}
			#billing_cc_fields label.sa-checkbox {
			    text-align: right;
			    width: 100%;
			}
			#credit_card_fields .sa-controls.input_wrap {
			    margin: 0;
			    display: block;
			    clear: both;
			}
			.sa-form-field-bypass legend {
				margin-top: 20px;
			}
		</style>
		<?php
	}

	public function add_cim_options( $fields, $checkout ) {
		$invoice = $checkout->get_invoice();
		$invoice_id = $invoice->get_id();
		// If multiple payments isn't selected add the credit-card option
		$fields['payment_method'] = array();
		$fields['payment_method']['type'] = 'bypass';
		$fields['payment_method']['weight'] = 0;
		$fields['payment_method']['label'] = self::__( 'Payment Method' );
		$fields['payment_method']['required'] = true;

		// Add CC options to the checkout fields
		$profile_id = self::get_customer_profile_id( $invoice_id );
		$cards = self::payment_card_profiles( $profile_id );
		ob_start();
		?>
			<div class="sa-control-group ">
				<span class="label_wrap">
					<label for="sa_credit_payment_method"><?php self::_e( 'Payment Method' ) ?></label> <span class="required">*</span>
				</span>
				<span class="input_wrap">
					<span class="sa-form-field sa-form-field-radios sa-form-field-required">
						<?php
						if ( ! empty( $cards ) ) : ?>
							<?php foreach ( $cards as $payment_profile_id => $card_number ) : ?>
								<?php if ( ! self::is_payment_profile_hidden( $payment_profile_id, $invoice_id ) ) : ?>
									<?php
										$name = ( '' == $card_number ) ? self::__( 'Checking' ) : $card_number ; ?>
									<span class="sa-form-field-radio clearfix">
										<label for="sa_credit_payment_method_<?php echo (int) $payment_profile_id ?>">
											<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo (int) $payment_profile_id ?>" value="<?php echo (int) $payment_profile_id ?>"><?php printf( '%2$s <a href="javascript:void(0)" data-ref="%3$s" data-invoice-id="%5$s" class="cim_delete_card" title="%4$s"><span class="dashicons dashicons-trash"></span></a>', self::__( 'Previously used' ), $name, (int) $payment_profile_id, self::__( 'Remove this CC from your account.' ), (int) $invoice_id ) ?>
										</label>
									</span>
								<?php endif ?>
							<?php endforeach ?>
						<?php endif ?>
						<span class="sa-form-field-radio clearfix">
							<label for="sa_credit_payment_method_credit">
							<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_credit" value="new_credit" checked="checked"><?php self::_e( 'New credit card:' ) ?></label>
						</span>
					</span>
				</span>
			</div>
		<?php
		$multiselect = ob_get_clean();

		$fields['payment_method']['output'] = $multiselect;
		$fields['store_payment_profile'] = array(
			'type' => 'checkbox',
			'weight' => 100,
			'label' => self::__( 'Save Credit Card' ),
			'default' => true,
		);
		return $fields;
	}

	public static function add_checking_info( $checkout ) {
		$bank_fields = array();

		$bank_fields['section_heading'] = array(
			'type' => 'bypass',
			'weight' => 1,
			'output' => sprintf( '<span class="sa-form-field-radio clearfix"><label for="sa_credit_payment_method_bank"><input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_bank" value="new_bank">%s</label></span>', self::__( 'New checking account:' ) ),
		);
		$bank_fields['bank_routing'] = array(
			'type' => 'text',
			'weight' => 5,
			'label' => self::__( 'Routing Number' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		$bank_fields['bank_account'] = array(
			'type' => 'text',
			'weight' => 10,
			'label' => self::__( 'Checking Account' ),
			'attributes' => array(
				//'autocomplete' => 'off',
			),
			'required' => true,
		);
		$bank_fields['store_payment_profile'] = array(
			'type' => 'checkbox',
			'weight' => 100,
			'label' => self::__( 'Save Bank Info' ),
			'default' => true,
		);
		sa_form_fields( $bank_fields, 'bank' );
	}



	//////////////////////
	// Payment Profile //
	//////////////////////

	/**
	 * Create the Profile within CIM if one doesn't exist.
	 *
	 */
	public function add_payment_profile( $profile_id, SI_Checkouts $checkout, SI_Invoice $invoice ) {
		// Create new customer profile
		$paymentProfile = new AuthorizeNetPaymentProfile;
		$paymentProfile->customerType = 'individual';
		$paymentProfile->billTo->firstName = $checkout->cache['billing']['first_name'];
		$paymentProfile->billTo->lastName = $checkout->cache['billing']['last_name'];
		$paymentProfile->billTo->address = $checkout->cache['billing']['street'];
		$paymentProfile->billTo->city = $checkout->cache['billing']['city'];
		$paymentProfile->billTo->state = $checkout->cache['billing']['zone'];
		$paymentProfile->billTo->zip = $checkout->cache['billing']['postal_code'];
		$paymentProfile->billTo->country = $checkout->cache['billing']['country'];
		$paymentProfile->billTo->phoneNumber = '';
		// $paymentProfile->billTo->customerAddressId = $customer_address_id;

		if ( isset( $checkout->cache['bank_routing'] ) ) {
			// bank info
			$paymentProfile->payment->bankAccount->accountType = 'businessChecking';
			$paymentProfile->payment->bankAccount->routingNumber = $checkout->cache['bank_routing'];
			$paymentProfile->payment->bankAccount->accountNumber = $checkout->cache['bank_account'];
			$paymentProfile->payment->bankAccount->nameOnAccount = $checkout->cache['billing']['first_name'] . ' ' . $checkout->cache['billing']['last_name'];
			//$paymentProfile->payment->bankAccount->echeckType = 'WEB';
			//$paymentProfile->payment->bankAccount->bankName = 'Unknown';
		}
		else {
			// CC info
			$paymentProfile->payment->creditCard->cardNumber = $this->cc_cache['cc_number'];
			$paymentProfile->payment->creditCard->expirationDate = $this->cc_cache['cc_expiration_year'] . '-' . sprintf( '%02s', $this->cc_cache['cc_expiration_month'] );
			$paymentProfile->payment->creditCard->cardCode = $this->cc_cache['cc_cvv'];
		}
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - paymentProfile:', $paymentProfile );

		// Create
		$create_profile_response = self::$cim_request->createCustomerPaymentProfile( $profile_id, $paymentProfile );
		if ( ! $create_profile_response->isOk() ) {
			// In case no validation response is given but there's an error.
			if ( isset( $create_profile_response->xml->messages->message->text ) ) {
				self::set_error_messages( (string) $create_profile_response->xml->messages->message->text );
				return false;
			}
		}
		// Get profile id
		$payment_profile_id = $create_profile_response->getPaymentProfileId();

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - createCustomerPaymentProfile create_profile_response:', $create_profile_response );
		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - payment_profile_id', $payment_profile_id );

		// Save the profile, even if it may be removed later
		$this->save_payment_profile( $payment_profile_id );

		return $payment_profile_id;
	}

	/**
	 * Build an array of card from the profile
	 *
	 * @param integer $profile_id CIM profile ID
	 * @return array
	 */
	public static function payment_card_profiles( $profile_id = 0 ) {
		// Get profile object
		$customer_profile = self::get_customer_profile( $profile_id );
		if ( ! $customer_profile ) {
			return false;
		}
		// Create an array of payment profile card numbers
		$cards = array();
		if ( isset( $customer_profile->xpath_xml->profile->paymentProfiles ) ) {
			if ( ! empty( $customer_profile->xpath_xml->profile->paymentProfiles[0] ) ) {
				foreach ( $customer_profile->xpath_xml->profile->paymentProfiles as $key => $profile ) {
					$name = ( isset( $profile->payment->creditCard->cardNumber ) ) ? self::__( 'Credit Card' ) . ': ' . $profile->payment->creditCard->cardNumber : self::__( 'Checking' ) . ': ' . $profile->payment->bankAccount->accountNumber ;
					$cards[(int)$profile->customerPaymentProfileId] = (string)$name;
				}
			}
			else {
				$name = ( isset( $profile->payment->creditCard->cardNumber ) ) ? self::__( 'Credit Card' ) . ': ' . $profile->payment->creditCard->cardNumber : self::__( 'Checking' ) . ': ' . $profile->payment->bankAccount->accountNumber ;
				$cards[(int)$customer_profile->xpath_xml->profile->paymentProfiles->customerPaymentProfileId] = (string)$name;
			}
		}
		return $cards;
	}

	/**
	 * Does the user have any card profiles
	 *
	 * @param unknown $user_id
	 * @return
	 */
	public static function has_payment_profile( $invoice_id = 0 ) {
		if ( $profile_id = self::get_customer_profile_id( $invoice_id ) ) {
			return count( self::payment_card_profiles( $profile_id ) );
		}
		return false;
	}

	////////////////////////
	// Profile Managment //
	////////////////////////

	/**
	 * Create the Profile within CIM if one doesn't exist.
	 *
	 * @param  SI_Checkouts $checkout
	 * @param  SI_Invoice   $invoice
	 * @param  boolean      $force
	 * @return
	 */
	public function create_profile( SI_Checkouts $checkout, SI_Invoice $invoice, $force = false ) {

		self::init_authrequest();

		$profile_id = self::get_customer_profile_id( $invoice->get_id(), true );
		if ( $profile_id ) {
			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile return saved id: ', $profile_id );
			return $profile_id;
		}

		$client_id = self::get_client_id( $invoice->get_id() );
		$client = SI_Client::get_instance( $client_id );
		$user = si_who_is_paying( $invoice );

		if ( ! is_a( $client, 'SI_Client' ) ) {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile not a valid client_id: ', $client_id );
			return false;
		}

		// Create new customer profile
		$customerProfile = new AuthorizeNetCustomer;
		$customerProfile->description = $client->get_title();
		$customerProfile->merchantCustomerId = $client->get_id();
		$customerProfile->email = $user->user_email;

		// Request and response
		$response = self::$cim_request->createCustomerProfile( $customerProfile );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create customer profile response: ', $response );

		// Error check
		if ( ! $response->isOk() ) {
			$error_message = $response->getMessageText();
			// If the ID already exists tie it to the current user, hopefully the CIM profile is based on more than just email.
			if ( strpos( $error_message, 'duplicate record with ID' ) ) {
				preg_match( '~ID\s+(\S+)~', $error_message, $matches );
				$new_customer_id = $matches[1];
				if ( ! is_numeric( $new_customer_id ) ) {
					self::set_error_messages( sa__( 'A duplicate profile was found. Please contact the site administrator.' ) );
					return false;
				}
			} else {
				self::set_error_messages( $error_message );
				return false;
			}
		}
		else { // New customer profile was created.
			$new_customer_id = $response->getCustomerProfileId();
		}
		// Save profile id with the user for future reference.
		update_post_meta( $client_id, self::CLIENT_META_PROFILE_ID, $new_customer_id );

		do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - create_profile return:  ', $new_customer_id );

		// Return
		return $new_customer_id;

	}

	public static function destroy_profile( $client_id = 0, $invoice_id = 0 ) {
		if ( ! $client_id && $invoice_id ) {
			$client_id = self::get_client_id( $invoice_id );
		}
		if ( ! $client_id ) {
			return;
		}
		delete_post_meta( $client_id, self::CLIENT_META_PROFILE_ID );
	}

	/**
	 * Get the profile id of a user.
	 *
	 * @param int     $profile_id Profile id stored in user meta
	 * @return object
	 */
	public static function get_customer_profile( $profile_id = 0, $invoice_id = 0 ) {
		if ( ! $profile_id ) {
			$profile_id = self::get_customer_profile_id( $invoice_id );
			if ( ! $profile_id ) {
				return false;
			}
		}
		self::init_authrequest();
		$customer_profile = self::$cim_request->getCustomerProfile( $profile_id );
		return $customer_profile;
	}

	public static function get_customer_profile_id( $invoice_id = 0, $validate = false ) {
		$client_id = self::get_client_id( $invoice_id );
		$profile_id = get_post_meta( $client_id, self::CLIENT_META_PROFILE_ID, true );

		if ( $validate && $profile_id ) {
			$customer_profile = self::get_customer_profile( $profile_id );
			// If the profile exists than return it's id
			if ( ! $customer_profile->isError() || $customer_profile->getMessageCode() != 'E00040' ) {
				return $profile_id;
			}
			// profile validation produced an error, remove it from this user and continue creating a new one.
			$profile_id = 0;
			self::destroy_profile( $client_id );

			do_action( 'si_log', __CLASS__ . '::' . __FUNCTION__ . ' - get customer profile from create_profile resulted in an error:  ', $response );
		}

		return $profile_id;
	}

	///////////////////////////////////////
	// Local payment profile management //
	///////////////////////////////////////

	public function remove_payment_profile( $profile_id, $invoice_id = 0 ) {

		self::init_authrequest();

		$client_id = self::get_client_id( $invoice_id );
		$hidden_profiles = get_post_meta( $client_id, self::CLIENT_CIM_HIDDEN_CARDS, true );
		if ( ! is_array( $hidden_profiles ) ) {
			$hidden_profiles = array();
		}
		$hidden_profiles[] = $profile_id;
		update_post_meta( $client_id, self::CLIENT_CIM_HIDDEN_CARDS, $hidden_profiles );

		// modify via CIM
		$customer_profile = self::get_customer_profile_id( $invoice_id );
		$response = self::$cim_request->deleteCustomerPaymentProfile( $customer_profile, $profile_id );
	}

	/**
	 * Simply Removes profile id from the hidden payments
	 * @param  int  $profile_id
	 * @param  integer $invoice_id
	 * @return
	 */
	public function save_payment_profile( $profile_id, $invoice_id = 0 ) {
		$client_id = self::get_client_id( $invoice_id );
		if ( ! $profile_id ) {
			update_post_meta( $client_id, self::CLIENT_CIM_HIDDEN_CARDS, array() );
			return;
		}
		$hidden_profiles = get_post_meta( $client_id, self::CLIENT_CIM_HIDDEN_CARDS, true );
		if ( ! is_array( $hidden_profiles ) ) {
			return;
		}
		// search for position
		$pos = array_search( $profile_id, $hidden_profiles );
		// remove
		unset( $hidden_profiles[$pos] );
		// save
		update_post_meta( $account_id, self::CLIENT_CIM_HIDDEN_CARDS, $hidden_profiles );
	}

	public function is_payment_profile_hidden( $profile_id, $invoice_id = 0 ) {
		$account_id = self::get_client_id( $invoice_id );
		$hidden_profiles = get_post_meta( $account_id, self::CLIENT_CIM_HIDDEN_CARDS, true );
		if ( ! is_array( $hidden_profiles ) ) {
			return false;
		}
		return in_array( $profile_id, $hidden_profiles );
	}

	//////////
	// AJAX //
	//////////

	public function ajax_cim() {
		switch ( $_REQUEST['cim_action'] ) {
			case 'remove_payment_profile':
				self::remove_payment_profile( $_REQUEST['remove_profile'], $_REQUEST['invoice_id'] );
				exit();
			break;

			case 'add_payment_profile':
				self::save_payment_profile( $_REQUEST['add_profile'], $_REQUEST['invoice_id'] );
				exit();
			break;

			default:
			break;
		}
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

	private function get_currency_code( $invoice_id ) {
		return apply_filters( 'si_currency_code', self::$currency_code, $invoice_id, self::PAYMENT_METHOD );
	}

	/**
	 * Grabs error messages from a Authorize response and displays them to the user
	 *
	 * @param array   $response
	 * @param bool    $display
	 * @return void
	 */
	private function set_error_messages( $response, $display = true ) {
		if ( $display ) {
			self::set_message( (string) $response, self::MESSAGE_STATUS_ERROR );
		} else {
			do_action( 'si_error', __CLASS__ . '::' . __FUNCTION__ . ' - Auth.net Error Response', $response );
		}
	}
}
SI_AuthorizeNet_CIM::register();