<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IS_TEST' ) ) {
	$active_plugins = get_option( 'active_plugins' );
	if ( ! in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
		wp_die( 'Sorry, but this plugin requires the Woocommerce plugin to be installed and active.', null, array( 'back_link' => true ) );
	}

	require_once WC_EBANX_SERVICES_DIR . 'class-wc-ebanx-notice.php';
	require_once WC_EBANX_SERVICES_DIR . 'class-wc-ebanx-constants.php';
	require_once WC_EBANX_SERVICES_DIR . 'class-wc-ebanx-helper.php';
}

/**
 * Class WC_EBANX_Global_Gateway
 */
final class WC_EBANX_Global_Gateway extends WC_Payment_Gateway {
	const CC_COUNTRIES_FROM_ISO = array(
		WC_EBANX_Constants::COUNTRY_BRAZIL => 'Brazil',
	);

	/**
	 * Mock to insert when plugin is installed
	 *
	 * @var array
	 */
	public static $defaults = array(
		'sandbox_private_key'         => '',
		'sandbox_public_key'          => '',
		'sandbox_mode_enabled'        => 'yes',
		'debug_enabled'               => 'yes',
		'brazil_payment_methods'      => array(
			'ebanx-credit-card-br',
			'ebanx-banking-ticket',
		),
		'save_card_data'                         => 'yes',
		'one_click'                              => 'yes',
		'capture_enabled'                        => 'yes',
		'billing_phone_required'                 => 'yes',
		'enable_international_credit_card'       => 'no',
		'international_credit_card_instalments'  => '1',
		'br_credit_card_instalments'             => '1',
		'due_date_days'                          => '3',
		'brazil_taxes_options'                   => 'cpf',
		'br_interest_rates_enabled'              => 'no',
		'manual_review_enabled'                  => 'no',
		'br_min_instalment_value_brl'            => '5',
		'international_min_instalment_value_brl' => '5',

	);

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->notices = new WC_EBANX_Notice();

		$this->id                 = 'ebanx-global';
		$this->method_title       = __( 'EBANX Pay', 'woocommerce-gateway-ebanx' );
		$this->method_description = __( 'EBANX Pay easy-to-setup checkout allows your business to accept local payments in Brazil.', 'woocommerce-gateway-ebanx' );

		$this->merchant_currency = strtoupper( get_woocommerce_currency() );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * This method always will return false, it doesn't need to show to the customers
	 *
	 * @return boolean Always return false
	 */
	public function is_available() {
		return false;
	}

	/**
	 * Error handling
	 */
	public function validate_due_date_days_field() {
		if ( WC_EBANX_Request::read( 'woocommerce_ebanx-global_due_date_days' ) < 1 ) {
			WC_EBANX_Request::set( 'woocommerce_ebanx-global_due_date_days', self::$defaults['due_date_days'] );

			$this->notices
				->with_message( __( 'Days To Expiration must be greater than or equal to 1.', 'woocommerce-gateway-ebanx' ) )
				->with_type( 'error' )
				->display();

			return;
		}

		return WC_EBANX_Request::read( 'woocommerce_ebanx-global_due_date_days' );
	}

	/**
	 * Define the fields on EBANX WooCommerce settings page and set the defaults when the plugin is installed
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$fields = array(
			'integration_title'         => array(
				'title'       => __( 'Integration', 'woocommerce-gateway-ebanx' ),
				'type'        => 'title',
				'description' => __( 'You can obtain the integration keys sending email to <a href="mailto:localintegration@ebanxpay.com">localintegration@ebanxpay.com</a>', 'woocommerce-gateway-ebanx' ),
			),
			'sandbox_private_key'       => array(
				'title' => __( 'Sandbox Integration Key', 'woocommerce-gateway-ebanx' ),
				'type'  => 'text',
			),
			'sandbox_public_key'        => array(
				'title' => __( 'Sandbox Public Integration Key', 'woocommerce-gateway-ebanx' ),
				'type'  => 'text',
			),
			'live_private_key'          => array(
				'title' => __( 'Live Integration Key', 'woocommerce-gateway-ebanx' ),
				'type'  => 'text',
			),
			'live_public_key'           => array(
				'title' => __( 'Live Public Integration Key', 'woocommerce-gateway-ebanx' ),
				'type'  => 'text',
			),
			'sandbox_mode_enabled'      => array(
				'title'       => __( 'EBANX Sandbox', 'woocommerce-gateway-ebanx' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Sandbox Mode', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'EBANX Sandbox is a testing environment that mimics the live environment. Use it to make payment requests to see how your ecommerce processes them.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
			),
			'debug_enabled'             => array(
				'title'       => __( 'Debug Log', 'woocommerce-gateway-ebanx' ),
				'label'       => __( 'Enable Debug Log', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Record all errors that occur when executing a transaction.', 'woocommerce-gateway-ebanx' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
			),
			'display_methods_title'     => array(
				'title'       => __( 'Enable Payment Methods', 'woocommerce-gateway-ebanx' ),
				'type'        => 'title',
				'description' => sprintf( __( 'Set up payment methods for your checkout. Confirm that method is enabled on your contract.', 'woocommerce-gateway-ebanx' ), 'http://google.com' ),
			),
			'brazil_payment_methods'    => array(
				'title'   => __( 'Brazil', 'woocommerce-gateway-ebanx' ),
				'type'    => 'multiselect',
				'class'   => 'wc-enhanced-select',
				'options' => array(
					'ebanx-credit-card-br'            => 'Credit Card',
					'ebanx-banking-ticket'            => 'Boleto bancário',
					'ebanx-credit-card-international' => 'International Credit Card',
				),
				'default' => array(
					'ebanx-credit-card-br',
					'ebanx-banking-ticket',
				),
			),
			'payments_options_title'    => array(
				'title' => __( 'Payment Options', 'woocommerce-gateway-ebanx' ),
				'type'  => 'title',
			),
			'credit_card_options_title' => array(
				'title' => __( 'Credit Card', 'woocommerce-gateway-ebanx' ),
				'type'  => 'title',
				'class' => 'ebanx-payments-option',
			),
			'save_card_data'            => array(
				'title'       => __( 'Save Card Data', 'woocommerce-gateway-ebanx' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable saving card data', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Allow your customer to save credit card and debit card data for future purchases.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
				'class'       => 'ebanx-payments-option',
			),
			'one_click'                 => array(
				'type'        => 'checkbox',
				'title'       => __( 'One-Click Payment', 'woocommerce-gateway-ebanx' ),
				'label'       => __( 'Enable one-click-payment', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Allow your customer to complete payments in one-click using credit cards saved.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
				'class'       => 'ebanx-payments-option',
			),
			'capture_enabled'           => array(
				'type'        => 'checkbox',
				'title'       => __( 'Enable Auto-Capture', 'woocommerce-gateway-ebanx' ),
				'label'       => __( 'Capture the payment immediately', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Automatically capture payments from your customers, just for credit card. Otherwise you will need to capture the payment going to: WooCommerce -> Orders. Not captured payments will be cancelled in 4 days.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
				'class'       => 'ebanx-payments-option',
			),
			'enable_international_credit_card'  => array(
				'type'        => 'checkbox',
				'title'       => __( 'Enable international credit card', 'woocommerce-gateway-ebanx' ),
				'label'       => __( 'Enable international credit card', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Enable international credit card. Default is false.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
				'class'       => 'ebanx-payments-option',
			),
			'billing_phone_required'           => array(
				'type'        => 'checkbox',
				'title'       => __( 'Billing phone is required', 'woocommerce-gateway-ebanx' ),
				'label'       => __( 'Billing phone is required', 'woocommerce-gateway-ebanx' ),
				'description' => __( 'Makes billing phone required. Default is true.', 'woocommerce-gateway-ebanx' ),
				'desc_tip'    => true,
				'class'       => 'ebanx-payments-option',
			),
		);

		$interest_rates_array = array_map(
			function ( $country_abbr ) {
					$currency_code          = strtolower( $this->merchant_currency );
					$country                = self::CC_COUNTRIES_FROM_ISO[ $country_abbr ];
					$interest_rates_array   = array();
					$interest_rates_array[] = array(
						"{$country_abbr}_payments_options_title" => array(
							'title' => __( sprintf( 'Interest Options for %s', $country ), 'woocommerce-gateway-ebanx' ),
							'type'  => 'title',
							'class' => 'ebanx-payments-option',
						),
					);
					$interest_rates_array[] = array(
						"{$country_abbr}_interest_rates_enabled" => array(
							'type'        => 'checkbox',
							'title'       => __( 'Interest Rates', 'woocommerce-gateway-ebanx' ),
							'label'       => __( sprintf( 'Enable interest rates for %s', $country ), 'woocommerce-gateway-ebanx' ),
							'description' => __( 'Enable and set a custom interest rate for your customers according to the number of Instalments you allow the payment.', 'woocommerce-gateway-ebanx' ),
							'desc_tip'    => true,
							'class'       => 'ebanx-payments-option',
						),
					);
					$interest_rates_array[] = array(
						"{$country_abbr}_credit_card_instalments" => array(
							'title'       => __( sprintf( 'Maximum nº of Instalments for %s', $country ), 'woocommerce-gateway-ebanx' ),
							'type'        => 'select',
							'class'       => 'wc-enhanced-select ebanx-payments-option ebanx-credit-card-instalments',
							'options'     => WC_EBANX_Helper::get_instalments_by_country( $country_abbr ),
							'description' => __( 'Establish the maximum number of instalments in which your customer can pay, as consented on your contract.', 'woocommerce-gateway-ebanx' ),
							'desc_tip'    => true,
						),
					);

				if ( in_array( strtoupper( $currency_code ), WC_EBANX_Constants::$credit_card_currencies, true ) ) {
					$interest_rates_array[] = array(
						"{$country_abbr}_min_instalment_value_$currency_code" => array(
							'title'             =>  __( sprintf('Minimum Instalment for %s (%s)', $country, strtoupper( $currency_code ) ), 'woocommerce-gateway-ebanx' ) ,
							'type'              => 'number',
							'class'             => 'ebanx-payments-option',
							'placeholder'       => sprintf(
								// translators: placeholder contains default instalments.
								__( 'The default is %d', 'woocommerce-gateway-ebanx' ),
								$this->get_min_instalment_value_for_currency( $currency_code )
							),
							'custom_attributes' => array(
								'min'  => $this->get_min_instalment_value_for_currency( $currency_code ),
								'step' => '0.01',
							),
							'desc_tip'          => true,
							'description'       => __( 'Set the minimum installment value to show to the options for your customer on the checkout page. The default values are Brazil: BRL 5. Any amount under these will not be considered.', 'woocommerce-gateway-ebanx' ),
						),
					);
				}

				for ( $i = 1; $i <= 12; $i++ ) {
					$interest_rates_array[] = array(
						"{$country_abbr}_interest_rates_" . sprintf( '%02d', $i ) => array(
							// translators: placeholder contains interest tx per instalment.
							'title'             => sprintf( __( '%dx interest rate in %s', 'woocommerce-gateway-ebanx' ), $i, '%' ),
							'type'              => 'number',
							'custom_attributes' => array(
								'min'  => '0',
								'step' => 'any',
							),
							'class'             => "interest-rates-fields interest-$country_abbr ebanx-payments-option",
							'placeholder'       => __( 'eg: 15.7%', 'woocommerce-gateway-ebanx' ),
						),
					);
				}

				return $interest_rates_array;
			},
			array_keys( WC_EBANX_Constants::$credit_card_countries )
		);

		$countries_length = count( self::CC_COUNTRIES_FROM_ISO );

		for ( $i = 0; $i < $countries_length; $i++ ) {
			$array_length = count( $interest_rates_array[ $i ] );
			for ( $j = 0; $j < $array_length; $j++ ) {
				$fields = array_merge( $fields, $interest_rates_array[ $i ] [ $j ] );
			}
		}

		$fields = array_merge(
			$fields,
			array(
				'cash_options_title' => array(
					'title' => __( 'Cash Payments', 'woocommerce-gateway-ebanx' ),
					'type'  => 'title',
					'class' => 'ebanx-payments-option',
				),
				'due_date_days'      => array(
					'title'       => __( 'Days to Expiration', 'woocommerce-gateway-ebanx' ),
					'class'       => 'ebanx-due-cash-date ebanx-payments-option',
					'description' => __( 'Define the maximum number of days on which your customer can complete the payment of Boleto', 'woocommerce-gateway-ebanx' ),
					'desc_tip'    => true,
				),
			)
		);

		$fields['due_date_days']['type'] = (
			in_array( $this->merchant_currency, WC_EBANX_Constants::$local_currencies, true ) ?
				'number' : 'select'
		);

		if ( ! in_array( $this->merchant_currency, WC_EBANX_Constants::$local_currencies, true ) ) {
			$fields['due_date_days']['class']  .= ' wc-enhanced-select';
			$fields['due_date_days']['options'] = array(
				'1' => '1',
				'2' => '2',
				'3' => '3',
			);
		}

		$fields = array_merge(
			$fields,
			array(
				'advanced_options_title'              => array(
					'title' => __( 'Advanced Options', 'woocommerce-gateway-ebanx' ),
					'type'  => 'title',
				),
				'brazil_taxes_options'                => array(
					'title'       => __( 'Enable Checkout for:', 'woocommerce-gateway-ebanx' ),
					'type'        => 'multiselect',
					'required'    => true,
					'class'       => 'wc-enhanced-select ebanx-advanced-option brazil-taxes',
					'options'     => array(
						'cpf'  => __( 'CPF - Individuals', 'woocommerce-gateway-ebanx' ),
						'cnpj' => __( 'CNPJ - Companies', 'woocommerce-gateway-ebanx' ),
					),
					'default'     => array( 'cpf' ),
					'description' => __( 'In order to process with the EBANX Plugin in Brazil there a few mandatory fields such as CPF identification for individuals and CNPJ for companies.', 'woocommerce-gateway-ebanx' ),
					'desc_tip'    => true,
				),
				'checkout_manager_enabled'            => array(
					'title'       => __( 'Checkout Manager', 'woocommerce-gateway-ebanx' ),
					'label'       => __( 'Use my checkout manager fields', 'woocommerce-gateway-ebanx' ),
					'type'        => 'checkbox',
					'class'       => 'ebanx-advanced-option ebanx-advanced-option-enable',
					'description' => __( 'If you make use of a Checkout Manager, please identify the HTML name attribute of the fields.', 'woocommerce-gateway-ebanx' ),
					'desc_tip'    => true,
				),
				'checkout_manager_brazil_person_type' => array(
					'title'       => __( 'Entity Type Selector', 'woocommerce-gateway-ebanx' ),
					'type'        => 'text',
					'class'       => 'ebanx-advanced-option ebanx-checkout-manager-field cpf_cnpj',
					'placeholder' => __( 'eg: billing_brazil_persontype', 'woocommerce-gateway-ebanx' ),
				),
				'checkout_manager_cpf_brazil'         => array(
					'title'       => __( 'CPF', 'woocommerce-gateway-ebanx' ),
					'type'        => 'text',
					'class'       => 'ebanx-advanced-option ebanx-checkout-manager-field cpf',
					'placeholder' => __( 'eg: billing_brazil_cpf', 'woocommerce-gateway-ebanx' ),
				),
				'checkout_manager_cnpj_brazil'        => array(
					'title'       => __( 'CNPJ', 'woocommerce-gateway-ebanx' ),
					'type'        => 'text',
					'class'       => 'ebanx-advanced-option ebanx-checkout-manager-field cnpj',
					'placeholder' => __( 'eg: billing_brazil_cnpj', 'woocommerce-gateway-ebanx' ),
				),
			)
		);

		$this->form_fields = apply_filters( 'ebanx_settings_form_fields', $fields );

		$this->inject_defaults();
	}

	/**
	 * Inject the default data based on mock
	 *
	 * @return void
	 */
	private function inject_defaults() {
		foreach ( $this->form_fields as $field => &$properties ) {
			if ( ! isset( self::$defaults[ $field ] ) ) {
				continue;
			}

			$properties['default'] = self::$defaults[ $field ];
		}
	}

	/**
	 * Gets the min instalment value for the provided currency
	 *
	 * @param  string $currency_code The lower-cased currency code.
	 * @return double
	 * @throws InvalidArgumentException When currency code does not accepts Credit Card payment.
	 */
	private function get_min_instalment_value_for_currency( $currency_code = null ) {
		if ( null === $currency_code ) {
			$currency_code = strtolower( $this->merchant_currency );
		}
		if ( ! in_array( strtoupper( $currency_code ), WC_EBANX_Constants::$credit_card_currencies, true ) ) {
			throw new InvalidArgumentException( "The provided currency code doesn't accept Credit Card payment", 1 );
		}

		switch ( $currency_code ) {
			case WC_EBANX_Constants::CURRENCY_CODE_BRL:
				return WC_EBANX_Constants::ACQUIRER_MIN_INSTALMENT_VALUE_BRL;
			default:
				return 0;
		}
	}

	/**
	 * Fetches a single setting from the gateway settings if found, otherwise it returns an optional default value
	 *
	 * @param  string $name    The setting name to fetch.
	 * @param  mixed  $default The default value in case setting is not present.
	 * @return mixed
	 */
	public function get_setting_or_default( $name, $default = null ) {
		if ( ! isset( $this->settings[ $name ] ) || empty( $this->settings[ $name ] ) ) {
			return $default;
		}

		return $this->settings[ $name ];
	}
}
