<?php
/**
 * Oxxo - Payment EBANX Pending.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<hr>
<div class="banking-ticket__desc">
    <p class="woocommerce-thankyou-order-received">¡Listo <?=$customer_name ?>! Tu boleta EBANX de pago en OXXO ha sido generada.</p>
    <p>Enviamos una copia a <strong><?=$customer_email ?></strong>.</p>
    <p>No lo olvides: tu boleta vence el día <strong><?php echo date_i18n('d/m', strtotime($due_date)) ?></strong>. Después de esa fecha no será posible realizar el pago y la boleta será cancelada automáticamente.</p>
	<p>¿Dudas? Con gusto te <a href="https://www.ebanx.com/mx/ayuda/pagos/boleta" target="_blank">ayudaremos</a>.</p>
</div>

<hr>
<div class="banking-ticket__actions">
    <div class="ebanx-button--group ebanx-button--group-two">
        <a href="<?=$url_pdf ?>" target="_blank" class="button banking-ticket__action">Guardar como PDF</a>
        <a href="<?=$url_print ?>" target="_blank" class="button banking-ticket__action">Imprimir OXXO</a>
    </div>
</div>
<hr>

<div>
    <iframe src="<?= $url_iframe; ?>" style="width: 100%; height: 1000px; border: 0px;"></iframe>
</div>
