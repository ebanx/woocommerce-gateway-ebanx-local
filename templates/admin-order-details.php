<div class="form-field form-field-wide">
	<h3><?php esc_html_e( 'EBANX Order Details', 'woocommerce-gateway-ebanx' ); ?></h3>
	<?php if ( 'ebanx-credit-card-br' === $order->get_payment_method() && ! empty( $instalments_number ) ) : ?>
		<p>
			<?php esc_html_e( 'Instalments number', 'woocommerce-gateway-ebanx' ); ?>
			<br>
			<?php echo esc_html_e( $instalments_number ); ?>
		</p>
	<?php endif ?>
	<?php if ( 'ebanx-banking-ticket' === $order->get_payment_method()  && ! empty( $boleto_url ) ) : ?>
		<p>
			<?php esc_html_e( 'Boleto URL', 'woocommerce-gateway-ebanx' ); ?>
			<br>
			<a href="<?php echo esc_url( $boleto_url ); ?>" class="ebanx-text-overflow" target="_blank"><?php echo esc_url( $boleto_url ); ?></a>
		</p>
	<?php endif ?>
	<?php if ( 'ebanx-banking-ticket' === $order->get_payment_method() && ! empty( $boleto_barcode ) ) : ?>
		<p>
			<?php esc_html_e( 'Boleto barcode', 'woocommerce-gateway-ebanx' ); ?>
			<br>
			<?php echo esc_html_e( $boleto_barcode ); ?>
		</p>
	<?php endif ?>
	<p>
		<?php esc_html_e( 'Dashboard Payment Link', 'woocommerce-gateway-ebanx' ); ?>
		<br>
		<a href="<?php echo esc_url( $dashboard_link ); ?>" class="ebanx-text-overflow" target="_blank"><?php echo esc_url( $dashboard_link ); ?></a>
	</p>
	<p>
		<?php esc_html_e( 'Payment Hash', 'woocommerce-gateway-ebanx' ); ?>
		<br>
		<?php echo esc_attr( $payment_hash ); ?>
	</p>
	<?php if ( 'pending' === $order->get_status() && $payment_checkout_url ) : ?>
		<p>
			<strong><?php esc_html_e( 'Customer Payment Link', 'woocommerce-gateway-ebanx' ); ?></strong>
			<br>
			<input type="text" value="<?php echo esc_url( $payment_checkout_url ); ?>" onfocus="this.select();" onmouseup="return false;" readonly>
		</p>
	<?php endif ?>
</div>


<style>
	.ebanx-text-overflow {
		text-overflow: ellipsis;
		white-space: nowrap;
		width: 100%;
		overflow: hidden;
		display: block;
	}
</style>
