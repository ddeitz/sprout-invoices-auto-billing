<div id="client_billing_fields" class="admin_fields si_clearfix">
<h2><?php echo get_the_title( $client_id ) ?></h2>
<h4><?php self::_e( 'Saved Payment Profiles' ) ?></h4>
	<form id="payment_options_update_<?php echo $client_id ?>" class="sa-form sa-form-stacked payment_options_update" action="" method="post">
		<div class="sa-control-group si_clearfix">
			<span class="label_wrap">
				<label for="sa_credit_payment_method">&nbsp;</label>
			</span>
			<span class="input_wrap si_clearfix">
				<span class="sa-form-field sa-form-field-radios sa-form-field-required">	
					<?php foreach ( $payment_profiles as $payment_profile_id => $name ) : ?>
						<div class="sa-form-field-radio si_clearfix">
							<label for="sa_credit_payment_method_<?php echo (int) $payment_profile_id ?>">
								<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_<?php echo (int) $payment_profile_id ?>" value="<?php echo (int) $payment_profile_id ?>" <?php checked( (int) $default_payment_profile_id, (int) $payment_profile_id ) ?>>&nbsp;<?php printf( '%1$s <a href="javascript:void(0)" data-ref="%2$s" data-client-id="%4$s" class="cim_delete_card" title="%3$s"><span class="dashicons dashicons-trash"></span></a>', $name, (int) $payment_profile_id, self::__( 'Remove this CC from your account.' ), (int) $client_id ) ?>
							</label>
						</div>
					<?php endforeach ?>
					<div class="sa-form-field-radio si_clearfix">
						<label for="sa_credit_payment_method_none">
						<input type="radio" name="sa_credit_payment_method" id="sa_credit_payment_method_none" value="" <?php checked( $default_payment_profile_id, '' ) ?>>&nbsp;<?php self::_e( 'None' ) ?></label>
					</div>
					<div class="sa-form-field-radio si_clearfix">
						<label for="sa_credit_payment_method_credit">
						<input type="radio" name="sa_credit_payment_method" value="new_credit" id="sa_credit_payment_method_credit">&nbsp;<?php self::_e( 'New credit card' ) ?></label>
					</div>
					<div class="sa-form-field-radio si_clearfix">
						<label for="sa_credit_payment_method_bank">
						<input type="radio" name="sa_credit_payment_method" value="new_bank" id="sa_credit_payment_method_bank">&nbsp;<?php self::_e( 'New bank' ) ?></label>
					</div>
					
				</span>
			</span>
		</div>

		<div class="credit_card_fields">
			<?php
				$billing_fields = SI_Credit_Card_Processors::get_standard_address_fields();
				uasort( $billing_fields, array( 'SI_Controller', 'sort_by_weight' ) );
				$cc_fields = SI_Credit_Card_Processors::default_credit_fields();
				$bank_fields = SI_AuthorizeNet_CIM::checking_account_fields(); // TODO detach from CIM
				unset( $bank_fields['section_heading'] );
				unset( $bank_fields['store_payment_profile'] );
					?>
			<div class="billing_cc_fields si_clearfix">
				<fieldset class="billing_fields sa-fieldset si_clearfix">
					<legend><?php si_e( 'Billing' ) ?></legend>
					<?php sa_form_fields( $billing_fields, 'billing' ); ?>
				</fieldset>
				<fieldset class="credit_fields sa-fieldset si_clearfix">
					<legend><?php si_e( 'Credit Card' ) ?></legend>
					<?php sa_form_fields( $cc_fields, 'credit' ); ?>
				</fieldset>
				<fieldset class="bank_fields sa-fieldset si_clearfix">
					<legend><?php si_e( 'Bank Info' ) ?></legend>
					<?php sa_form_fields( $bank_fields, 'credit' ); ?>
				</fieldset>

			</div><!-- #billing_cc_fields -->
		</div>
		<input type="hidden" name="payments_action" value="save_payment_option" />
		<input type="hidden" name="client_id" value="<?php echo $client_id ?>" />
		<button type="submit" class="button button-primary credit_card_submit"><?php si_e( 'Submit' ) ?></button>
	</form>
</div>