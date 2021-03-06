<?php

use Ebanx\Benjamin\Models\Configs\CreditCardConfig;
use Ebanx\Benjamin\Models\Country;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_EBANX_Credit_Card_International_Gateway
 */
class WC_EBANX_Credit_Card_International_Gateway extends WC_EBANX_Credit_Card_Gateway {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id            = 'ebanx-credit-card-international';
		$this->method_title  = __( 'EBANX - International Credit Card', 'woocommerce-gateway-ebanx' );
		$this->currency_code = WC_EBANX_Constants::CURRENCY_CODE_BRL;

		$this->title       = 'Cartão de Crédito Internacional';
		$this->description = 'Pague com cartão de crédito internacional.';

		parent::__construct();

		$is_brazilian_method_selected = is_array( $this->configs->settings['brazil_payment_methods'] )
			&& in_array( $this->id, $this->configs->settings['brazil_payment_methods'], true );

		$is_international_credit_card_enabled = 'yes' === $this->configs->get_setting_or_default('enable_international_credit_card', 'no' );

		$this->enabled = $is_brazilian_method_selected && $is_international_credit_card_enabled
			? 'yes'
			: false;

		$this->icon = null;
	}

	/**
	 * Check if the method is available to show to the users
	 *
	 * @return boolean
	 * @throws Exception Throws missing param message.
	 */
	public function is_available() {
		$iso_country = $this->get_iso_country_from_customer_or_order_on_admin();
		$iso_country = trim( strtolower( $iso_country ) );
		$is_international_credit_card_enabled = $this->configs->get_setting_or_default('enable_international_credit_card', false);

		if ( ! parent::is_available() ) {
			return false;
		}

		if ( ! $is_international_credit_card_enabled ) {
			return false;
		}

		if ( in_array( $iso_country, WC_EBANX_Constants::$allowed_countries, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The main method to process the payment came from WooCommerce checkout
	 * This method check the informations sent by WooCommerce and if them are fine, it sends the request to EBANX API
	 * The catch captures the errors and check the code sent by EBANX API and then show to the users the right error message
	 *
	 * @param  integer $order_id The ID of the order created.
	 *
	 * @return array
	 * @throws Exception Shows param missing message.
	 */
	public function process_payment( $order_id ) {
		$this->ebanx_gateway = $this->ebanx->creditCard(
			new CreditCardConfig(
				array(
					'maxInstalments'      => 1,
					'minInstalmentAmount' => 1,
				)
			)
		);

		return WC_EBANX_New_Gateway::process_payment( $order_id );
	}

	/**
	 * The HTML structure on checkout page
	 *
	 * @throws Exception Throws missing param message.
	 */
	public function payment_fields() {
		$cart_total = $this->get_order_total();
		$country    = $this->get_transaction_address( 'country' );
		$currency   = WC_EBANX_Constants::CURRENCY_CODE_BRL;

		$message = $this->get_sandbox_form_message( $country );
		wc_get_template(
			'sandbox-checkout-alert.php',
			array(
				'is_sandbox_mode' => $this->is_sandbox_mode,
				'message'         => $message,
			),
			'woocommerce/ebanx/',
			WC_EBANX::get_templates_path()
		);

		wc_get_template(
			$this->id . '/payment-form.php',
			array(
				'currency'      => $currency,
				'country'       => $country,
				'currency_code' => $this->currency_code,
				'cart_total'    => $cart_total,
				'id'            => $this->id,
			),
			'woocommerce/ebanx/',
			WC_EBANX::get_templates_path()
		);
	}
}
