<?php
/**
 * Boleto - Plain email instructions.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

echo '\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n';

echo 'Pagamento';

echo "\n\n";

echo 'Use o link abaixo para ver o seu boleto bancário. Você pode imprimi-lo e pagá-lo via internet banking ou numa lotérica.';

echo '\n';

echo esc_url( $boleto_url );

echo "\n";

echo 'O seu pedido será processado assim que recebermos a confirmação do pagamento do boleto.';
