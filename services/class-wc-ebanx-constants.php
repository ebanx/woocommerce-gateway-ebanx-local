<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_EBANX_VENDOR_DIR . 'autoload.php';

use Ebanx\Benjamin\Models\Country;

/**
 * Class WC_EBANX_Constants
 */
abstract class WC_EBANX_Constants {

	/**
	 * Define a default country for local payments
	 */
	const DEFAULT_COUNTRY = 'BR';

	/**
	 * Countries that EBANX processes
	 */
	const COUNTRY_BRAZIL = 'br';

	/**
	 * The fixed URL to our settings page, always use this one if you want to redirect to it
	 */
	const SETTINGS_URL = 'admin.php?page=wc-settings&tab=checkout&section=ebanx-global';

	/**
	 * Currencies that EBANX processes
	 */
	const CURRENCY_CODE_BRL = 'BRL'; // Brazil.

	/**
	 * Convert Country abbreviation to Country name
	 */
	const COUNTRY_NAME_FROM_ABBREVIATION = array(
		self::COUNTRY_BRAZIL => Country::BRAZIL,
	);

	/**
	 * Only the currencies allowed and processed by EBANX
	 *
	 * @var array
	 */
	public static $allowed_currency_codes = array(
		self::CURRENCY_CODE_BRL,
	);

	/**
	 *  Local currencies that EBANX processes
	 *
	 * @var array
	 */
	public static $local_currencies = array(
		self::COUNTRY_BRAZIL => self::CURRENCY_CODE_BRL,
	);

	/**
	 * Minimal instalment value for acquirers to approve based on currency
	 */
	const ACQUIRER_MIN_INSTALMENT_VALUE_BRL = 5;

	/**
	 * Max supported credit-card instalments
	 *
	 * @var array
	 */
	public static $max_instalments = array(
		self::COUNTRY_BRAZIL => 12,
	);

	/**
	 * The list of all countries that EBANX processes
	 *
	 * @var array
	 */
	public static $all_countries = array(
		self::COUNTRY_BRAZIL,
	);

	/**
	 * The countries that credit cards are processed by EBANX
	 *
	 * @var array
	 */
	public static $credit_card_countries = array(
		self::COUNTRY_BRAZIL => self::COUNTRY_BRAZIL,
	);

	/**
	 * The countries that credit cards are processed by EBANX
	 *
	 * @var array
	 */
	public static $credit_card_currencies = array(
		self::CURRENCY_CODE_BRL,
	);

	/**
	 * The cash payments processed by EBANX
	 *
	 * @var array
	 */
	public static $cash_payment_gateways_code = array(
		'ebanx-banking-ticket',
	);

	/**
	 * Payment type API codes for each plugin payment gateway
	 *
	 * @var array
	 */
	public static $gateway_to_payment_type_code = array(
		'ebanx-banking-ticket' => '_boleto',
		'ebanx-credit-card-br' => '_creditcard',
	);

	/**
	 * The Brazil taxes available options that EBANX process
	 *
	 * @var array
	 */
	public static $brazil_taxes_allowed = array( 'cpf', 'cnpj' );

	/**
	 * The gateways that plugin uses as identification
	 *
	 * @var array
	 */
	public static $ebanx_gateways_by_country = array(
		self::COUNTRY_BRAZIL => array(
			'ebanx-banking-ticket',
			'ebanx-credit-card-br',
		),
	);
}
