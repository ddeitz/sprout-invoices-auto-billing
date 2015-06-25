;(function( $, si, undefined ) {

	si.paymentDashboard = {
		config: {
			loader: '<span class="si_loader si_inline_spinner" style="visibility:visible;display:inline-block;"></span>',
		},
	};

	si.paymentDashboard.hideFields = function( fields_class ) {
		$('.billing_cc_fields .' + fields_class ).find('input, select, textarea').each( function() {
			if ( $(this).parent().hasClass('sa-form-field-required') ) {
				$(this).removeAttr( 'required' );
			};
		});
		$('.billing_cc_fields .' + fields_class).hide();
		return true;
	};

	si.paymentDashboard.showFields = function( fields_class ) {
		$('.billing_cc_fields .' + fields_class).find('input, select, textarea').each( function() {
			if ( $(this).parent().hasClass('sa-form-field-required') ) {
				jQuery(this).attr( 'required', true );
			};
		});
		$('.billing_cc_fields .' + fields_class).fadeIn();
		return true;
	};

	/**
	 * methods
	 */
	si.paymentDashboard.init = function() {

		si.paymentDashboard.hideFields( 'billing_fields' );
		si.paymentDashboard.hideFields( 'credit_fields' );
		si.paymentDashboard.hideFields( 'bank_fields' );
		$('[name="sa_credit_payment_method"]').live('change', function(e) {
			var selection = $( this ).val();
			if ( selection === 'new_credit' ) {
				si.paymentDashboard.showFields( 'billing_fields' );
				si.paymentDashboard.showFields( 'credit_fields' );
				si.paymentDashboard.hideFields( 'bank_fields' );
			}
			else if ( selection === 'new_bank' ) {
				si.paymentDashboard.showFields( 'billing_fields' );
				si.paymentDashboard.hideFields( 'credit_fields' );
				si.paymentDashboard.showFields( 'bank_fields' );
			}
			else {
				si.paymentDashboard.hideFields( 'billing_fields' );
				si.paymentDashboard.hideFields( 'credit_fields' );
				si.paymentDashboard.hideFields( 'bank_fields' );
			};

		});

		$('.payment_options_update').on( 'submit', function(event){
			event.preventDefault();
			var $form = $( this ),
				$button = $form.find( '.credit_card_submit' );
				data = $form.serialize();

			$('span.inline_message').hide();
			$button.after(si.paymentDashboard.config.loader);
			$.post( si_js_object.ajax_url, { action: 'si_ap_payment_option_save', submission: data, nonce: si_js_object.security },
				function( response ) {
					$('.si_loader').hide();
					if ( response.success ) {
						$button.after('<span class="inline_message inline_success_message">' + response.data.message + '</span>');
						location.reload();
					}
					else {
						$button.after('<span class="inline_message inline_error_message">' + response.data.message + '</span>');
					};

				}
			);
		});

		$('.cim_delete_card').on( 'click', function(event){
			event.preventDefault();
			var $remove_card = jQuery( this ),
				$payment_profile = $remove_card.data( 'ref' ),
				$client_id = $remove_card.data( 'client-id' );
			$.post( si_js_object.ajax_url, { action: 'cim_card_mngt', cim_action: 'remove_payment_profile', remove_profile: $payment_profile, invoice_id: $client_id }, // sending client id instead of invoice_id; CIM will figure it out.
				function( data ) {
					$remove_card.parent().parent().fadeOut();
					$( '#payment_options_update_' + $client_id + ' #sa_credit_payment_method_none').prop( 'checked', true );
				}
			);
		});
	};


})( jQuery, window.si = window.si || {} );

// Init
jQuery(function() {
	si.paymentDashboard.init();
});