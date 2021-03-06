<!-- Installments -->
<?php

$currency            = ! empty( $currency ) ? $currency : get_woocommerce_currency();
$selected_instalment = isset( $selected_instalment ) ? $selected_instalment : 1;

if ( count( $instalments_terms ) > 1 ) : ?>
	<section class="ebanx-form-row">
		<?php if ( WC_EBANX_Helper::disable_instalments_on_checkout() ) : ?>
			<input type="hidden" name="ebanx-credit-card-installments" value="1" />
		<?php else : ?>
			<label for="ebanx-card-installments">
				<?php
					echo esc_html( $instalments );
				?>
				<span class="required">*</span>
			</label>
			<select
				data-country="<?php echo esc_attr( $country ); ?>"
				data-amount="<?php echo esc_attr( $cart_total ); ?>"
				data-currency="<?php echo esc_attr( $currency ); ?>"
				data-order-id="<?php echo esc_attr( get_query_var( 'order-pay' ) ); ?>"
				class="ebanx-instalments ebanx-select-field"
				name="ebanx-credit-card-installments"
			>
				<?php foreach ( $instalments_terms as $instalment ) : ?>
					<option value="<?php echo esc_attr( $instalment['number'] ); ?>" <?php echo $selected_instalment == $instalment['number'] ? 'selected="selected"' : ''; ?>">
						<?php
							// @codingStandardsIgnoreLine
							printf( __( '%1$dx %2$s', 'woocommerce-gateway-ebanx' ), absint( $instalment['number'] ), esc_html( strip_tags( wc_price( $instalment['price'], array( 'currency' => $currency ) ) ) ) );
							// @codingStandardsIgnoreLine
							echo esc_html( $instalment['has_interest'] ? __( $with_interest, 'woocommerce-gateway-ebanx' ) : '' );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
	</section>
	<div class="clear"></div>
<?php endif; ?>
