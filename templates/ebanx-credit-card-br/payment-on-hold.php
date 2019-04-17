<?php
/**
 * Credit Card - Payment processed.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ebanx-thank-you-page ebanx-thank-you-page--br ebanx-thank-you-page--cc-br">
	<p><strong><?php echo esc_html( $customer_name ); ?>, recebemos o seu pedido e estamos analisando a sua solicitação. :) </strong></p>

	<p>Você deve receber um e-mail de confirmação em breve.</p>

	<input type="hidden" id="ebanx-payment-hash" data-doraemon-hash="<?php echo esc_html( $hash ); ?>">
</div>
