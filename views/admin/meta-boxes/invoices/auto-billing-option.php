<?php 
	$selected = ( $auto_bill ) ? self::__( 'Auto Payment: <b>Attempt</b>' ) : self::__( 'Auto Payment: <b>No</b>' );
	 ?>
<div class="misc-pub-section" data-edit-id="attempt-auto-bill" data-edit-type="checkbox">
	<span id="attempt-auto-bill" class="wp-media-buttons-icon"><?php echo self::__( $selected ) ?> <span title="<?php self::_e( 'Allow for the payment to be automatically processed.' ) ?>" class="helptip"></span></span>

	<a href="#edit_attempt-auto-bill" class="edit-attempt-auto-bill hide-if-no-js edit_control" >
		<span aria-hidden="true"><?php si_e( 'Edit' ) ?></span> <span class="screen-reader-text"><?php si_e( 'Select different attempt-auto-bill' ) ?></span>
	</a>

	<div id="attempt-auto-bill_div" class="control_wrap hide-if-js">
		<div class="attempt-auto-bill-wrap">
			<label for="attempt_auto_bill" class="sa-checkbox">
				<span class="sa-form-field sa-form-field-checkbox">
					<input type="checkbox" name="attempt_auto_bill" id="attempt_auto_bill" value="Attempt" class="checkbox" <?php checked( $auto_bill, true, true) ?>>
				</span>
				<?php self::_e( 'Attempt an automatic payment on the invoice due date <em>if the client has a payment profile setup</em>.' ) ?>
			</label>
 		</div>
		<p>
			<a href="#edit_attempt-auto-bill" class="save_control save-attempt-auto-bill hide-if-no-js button"><?php si_e( 'OK' ) ?></a>
			<a href="#edit_attempt-auto-bill" class="cancel_control cancel-attempt-auto-bill hide-if-no-js button-cancel"><?php si_e( 'Cancel' ) ?></a>
		</p>
 	</div>
</div>
<?php if ( $balance > 0.00 && $client_id && SI_Auto_Billing::can_charge_client( $client_id ) ): ?>
	<div class="misc-pub-section" data-edit-id="attempt-auto-bill" data-edit-type="checkbox">
		<?php printf( '<button class="payment_capture button button-small" data-client_id="%3$s" data-invoice_id="%2$s">%1$s</button>', sprintf( self::__( 'Attempt %s Payment' ), sa_get_formatted_money( $balance ) ), $invoice_id, $client_id, $balance ); ?>
	</div>
<?php endif ?>
