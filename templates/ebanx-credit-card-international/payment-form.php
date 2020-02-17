<?php
/**
 * Credit Card - Checkout form.
 *
 * @author  EBANX.com
 * @package WooCommerce_EBANX/Templates
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="ebanx-credit-card-international-form" class="ebanx-payment-container ebanx-language-br ebanx-credit-card-form">
	<section class="ebanx-form-row">
		<div id="ebanx-container-new-credit-card">
			<?php include_once 'card-template.php'; ?>
		</div>
	</section>
</div>

<script>
	// Custom select fields
	if ('jQuery' in window && 'select2' in jQuery.fn) {
		jQuery('select.ebanx-select-field').select2();
	}
</script>
