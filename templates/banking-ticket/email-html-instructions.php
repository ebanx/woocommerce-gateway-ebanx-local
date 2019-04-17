<?php
/**
 * Boleto - HTML email instructions.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<h2>Pagamento</h2>

<p class="order_details">
	Use o link abaixo para ver o seu boleto bancário. Você pode imprimi-lo e pagá-lo via internet banking ou numa lotérica.<br/>
	<a class="button" href="<?php echo esc_url( $boleto_url ); ?>" target="_blank">Pagar boleto bancário</a><br/>
	O seu pedido será processado assim que recebermos a confirmação do pagamento do boleto.<br/>
</p>
