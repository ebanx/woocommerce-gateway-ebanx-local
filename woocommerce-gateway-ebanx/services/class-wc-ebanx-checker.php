<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_EBANX_Checker {

	/**
	* Checks if sandbox mode is enabled and warns the user if it is.
	*
	* @return void
	*/
	public static function check_sandbox_mode($context) {

		if (!$context->is_sandbox_mode) {
			return;
		}

		$info_message = __('EBANX - The Sandbox Mode option is enabled, in this mode, none of your transactions will be processed.', 'woocommerce-gateway-ebanx');
		$context->notices
			->with_message($info_message)
			->with_type('info')
			->persistent()
			->display();
	}

	/**
	 * Check if the merchant's integration keys are valid
	 *
	 * @return boolean
	 */
	public static function check_merchant_api_keys($context)
	{
		try {
			if (empty($context->public_key) && empty($context->private_key)) {
				$context->notices
					->with_message(sprintf(__('EBANX - We are almost there. To start selling, <a href="%s">set your integration keys.</a>', 'woocommerce-gateway-ebanx'), admin_url( WC_EBANX_Constants::SETTINGS_URL )))
					->with_type('warning')
					->persistent()
					->enqueue();

				return;
			}

			if (get_option('_ebanx_api_was_checked') === 'success') {
				return;
			}

			if (get_option('_ebanx_api_was_checked') === 'error') {
				throw new Exception('API-VERIFICATION-KEY-ERROR');
			}

			\Ebanx\Config::set(array('integrationKey' => $context->private_key, 'testMode' => $context->is_sandbox_mode));

			$res = \Ebanx\Ebanx::getMerchantIntegrationProperties(array('integrationKey' => $context->private_key));
			$res_public = \Ebanx\Ebanx::getMerchantIntegrationPublicProperties(array('public_integration_key' => $context->public_key));

			if ( $res->status !== 'SUCCESS' || $res_public->status !== 'SUCCESS' ) {
				throw new Exception('API-VERIFICATION-KEY-ERROR');
			}

			update_option('_ebanx_api_was_checked', 'success');
		} catch (Exception $e) {
			update_option('_ebanx_api_was_checked', 'error');

			$api_url = 'https://api.ebanx.com';

			$message = sprintf('EBANX - Could not connect to our servers. Please check if your server can reach our API (<a href="%1$s">%1$s</a>) and your integrations keys are correct.', $api_url);
			$context->notices
				->with_message($message)
				->with_type('error')
				->persistent();
			if (empty($_POST)) {
				$context->notices->enqueue();
				return;
			}
			$context->notices->display();
		}
	}

	/**
	 * Check if the merchant environment
	 *
	 * @return void
	 */
	public static function check_environment($context)
	{
		$environment_warning = $context->get_environment_warning();

		if ($environment_warning && is_plugin_active(plugin_basename(__FILE__))) {
			$context->notices
				->with_message($environment_warning)
				->with_type('error')
				->persistent()
				->enqueue();
		}
	}

	/**
	 * Check if the currency is suported
	 *
	 * @return void
	 */
	public static function check_currency($context)
	{
		if (!in_array(get_woocommerce_currency(), WC_EBANX_Constants::$CURRENCIES_CODES_ALLOWED)) {
			$message = __('EBANX Gateway - Does not support the Currency you have set on the WooCommerce settings. To process with the EBANX plugin choose one of the following: %1$s.', 'woocommerce-gateway-ebanx');
			$message = sprintf($message, implode(', ', WC_EBANX_Constants::$CURRENCIES_CODES_ALLOWED));

			$context->notices
				->with_message($message)
				->with_type('warning')
				->persistent()
				->enqueue();

		}
	}

	/**
	 * Check if the protocol is not HTTPS
	 *
	 * @return void
	 */
	public static function check_https_protocol($context) {
		if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
			$message = __('EBANX - To improve the site security, we recommend the use of HTTPS protocol on site pages.', 'woocommerce-gateway-ebanx');

			$context->notices
				->with_message($message)
				->with_type('info')
				->persistent()
				->enqueue();
		}
	}
}