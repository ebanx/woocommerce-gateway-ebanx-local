<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_EBANX_Gateway
 */
class WC_EBANX_Gateway extends WC_Payment_Gateway {

	/**
	 *
	 * @var $ebanx_params
	 */
	protected static $ebanx_params = array();

	/**
	 *
	 * @var int
	 */
	protected static $initialized_gateways = 0;

	/**
	 *
	 * @var int
	 */
	protected static $total_gateways = 0;

	/**
	 * Current user id
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Constructor
	 */
	public function __construct() {
		self::$total_gateways++;

		$this->user_id = get_current_user_id();

		$this->configs = new WC_EBANX_Global_Gateway();

		$this->is_sandbox_mode = ( 'yes' === $this->configs->settings['sandbox_mode_enabled'] );

		$this->private_key = $this->is_sandbox_mode ? $this->configs->settings['sandbox_private_key'] : $this->configs->settings['live_private_key'];

		$this->public_key = $this->is_sandbox_mode ? $this->configs->settings['sandbox_public_key'] : $this->configs->settings['live_public_key'];

		if ( 'yes' === $this->configs->settings['debug_enabled'] ) {
			$this->log = new WC_Logger();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_assets' ), 100 );

		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );

		$this->supports = array( 'refunds' );

		$this->icon = $this->show_icon();

		$this->names = $this->get_billing_field_names();

		$this->merchant_currency = strtoupper( get_woocommerce_currency() );
	}

	/**
	 * Check if the method is available to show to the users
	 *
	 * @return boolean
	 */
	public function is_available() {
		return parent::is_available()
			&& 'yes' === $this->enabled
			&& ! empty( $this->public_key )
			&& ! empty( $this->private_key );
	}

	/**
	 * Insert custom billing fields on checkout page
	 *
	 * @param  array $fields WooCommerce's fields.
	 * @return array         The new fields.
	 */

	public function checkout_fields( $fields ) {
		$fields_options = array();
		if ( isset( $this->configs->settings['brazil_taxes_options'] ) && is_array( $this->configs->settings['brazil_taxes_options'] ) ) {
			$fields_options = $this->configs->settings['brazil_taxes_options'];
		}

		$is_billing_phone_required = ( 'yes' === $this->configs->get_setting_or_default('billing_phone_required', 'yes' ) );

		$fields['billing_phone']['required'] = $is_billing_phone_required;

		if ( in_array('ebanx-credit-card-international', $this->configs->get_setting_or_default('brazil_payment_methods', array( ) ), true )
			&& 'yes' === $this->configs->get_setting_or_default('enable_international_credit_card', 'no' )
		) {
			$international_document       = get_user_meta( $this->user_id, '_ebanx_billing_foreign_document', true );
			$is_foreign_document_required = WC_EBANX_Request::is_post_empty() || WC_EBANX_Constants::DEFAULT_COUNTRY !== strtoupper( WC_EBANX_Request::read( 'billing_country', '' ) );

			$ebanx_billing_foreign_document = array(
				'type'     => 'text',
				'label'    => __( 'Document', 'woocommerce-gateway-ebanx' ),
				'required' => $is_foreign_document_required,
				'class'    => array( 'form-row-wide' ),
				'default'  => isset( $international_document ) ? $international_document : '',
			);

			$fields['billing']['ebanx_billing_foreign_document'] = $ebanx_billing_foreign_document;
		}

		if ( 'yes' !== $this->configs->get_setting_or_default('checkout_manager_enabled', 'no' ) ) {
			// CPF and CNPJ are enabled.
			if ( in_array( 'cpf', $fields_options, true ) && in_array( 'cnpj', $fields_options, true ) ) {
				$ebanx_billing_brazil_person_type = array(
					'type' => 'select',
					'label' => __('Select an option', 'woocommerce-gateway-ebanx'),
					'default' => 'cpf',
					'required' => true,
					'class' => array('ebanx_billing_brazil_selector', 'ebanx-select-field'),
					'options' => array(
						'cpf' => __('CPF - Individuals', 'woocommerce-gateway-ebanx'),
						'cnpj' => __('CNPJ - Companies', 'woocommerce-gateway-ebanx'),
					),
				);

				$fields['billing']['ebanx_billing_brazil_person_type'] = $ebanx_billing_brazil_person_type;
			}

			$has_post_data = ! WC_EBANX_Request::is_post_empty();
			$person_type   = WC_EBANX_Request::read_customizable_field( 'ebanx_billing_brazil_person_type', $this->id, null );

			// CPF is enabled.
			if ( in_array( 'cpf', $fields_options, true ) ) {
				$cpf = get_user_meta( $this->user_id, '_ebanx_billing_brazil_document', true );

				$is_ebanx_brazil_document_required = ! $has_post_data || 'cpf' === $person_type;

				$fields['billing']['ebanx_billing_brazil_document'] = array(
					'type'    => 'text',
					'label'   => 'CPF',
					'required' => $is_ebanx_brazil_document_required,
					'class'   => array( 'ebanx_billing_brazil_document', 'ebanx_billing_brazil_cpf', 'ebanx_billing_brazil_selector_option', 'form-row-wide' ),
					'default' => isset( $cpf ) ? $cpf : '',
				);
			}

			// CNPJ is enabled.
			if ( in_array( 'cnpj', $fields_options, true ) ) {
				$cnpj = get_user_meta( $this->user_id, '_ebanx_billing_brazil_cnpj', true );
				$is_ebanx_brazil_cnpj_required =  ! $has_post_data || 'cnpj' === $person_type;

				$fields['billing']['ebanx_billing_brazil_cnpj'] = array(
					'type'    => 'text',
					'label'   => 'CNPJ',
					'required' => $is_ebanx_brazil_cnpj_required,
					'class'   => array( 'ebanx_billing_brazil_cnpj', 'ebanx_billing_brazil_cnpj', 'ebanx_billing_brazil_selector_option', 'form-row-wide' ),
					'default' => isset( $cnpj ) ? $cnpj : '',
				);
			}
		}

		return $fields;
	}

	/**
	 * Fetches the billing field names for compatibility with checkout managers
	 *
	 * @return array
	 */
	public function get_billing_field_names() {
		return array(
			// Brazil General.
			'ebanx_billing_brazil_person_type' => $this->get_checkout_manager_settings_or_default( 'checkout_manager_brazil_person_type', 'ebanx_billing_brazil_person_type' ),
			// Brazil CPF.
			'ebanx_billing_brazil_document'    => $this->get_checkout_manager_settings_or_default( 'checkout_manager_cpf_brazil', 'ebanx_billing_brazil_document' ),
			// Brazil CNPJ.
			'ebanx_billing_brazil_cnpj'        => $this->get_checkout_manager_settings_or_default( 'checkout_manager_cnpj_brazil', 'ebanx_billing_brazil_cnpj' ),
		);
	}

	/**
	 * Fetches a single checkout manager setting from the gateway settings if found, otherwise it returns an optional default value
	 *
	 * @param  string $name    The setting name to fetch.
	 * @param  mixed  $default The default value in case setting is not present.
	 * @return mixed
	 */
	private function get_checkout_manager_settings_or_default( $name, $default = null ) {
		if ( ! isset( $this->configs->settings['checkout_manager_enabled'] ) || 'yes' !== $this->configs->settings['checkout_manager_enabled'] ) {
			return $default;
		}

		return $this->get_setting_or_default( $name, $default );
	}

	/**
	 * Fetches a single setting from the gateway settings if found, otherwise it returns an optional default value
	 *
	 * @param  string $name    The setting name to fetch.
	 * @param  mixed  $default The default value in case setting is not present.
	 * @return mixed
	 */
	public function get_setting_or_default( $name, $default = null ) {
		return $this->configs->get_setting_or_default( $name, $default );
	}

	/**
	 * The icon on the right of the gateway name on checkout page
	 *
	 * @return string The URI of the icon
	 */
	public function show_icon() {
		return plugins_url( '/assets/images/' . $this->id . '.png', plugin_basename( dirname( __FILE__ ) ) );
	}

	/**
	 * Output the admin settings in the correct format.
	 *
	 * @return void
	 */
	public function admin_options() {
		include WC_EBANX_TEMPLATES_DIR . 'views/html-admin-page.php';
	}

	/**
	 * The page of order received, we call them as "Thank you pages"
	 *
	 * @param  array $data
	 * @return void
	 */
	public static function thankyou_page( $data ) {
		$file_name = "{$data['method']}/payment-{$data['order_status']}.php";

		if ( file_exists( WC_EBANX::get_templates_path() . $file_name ) ) {
			wc_get_template(
				$file_name,
				$data['data'],
				'woocommerce/ebanx/',
				WC_EBANX::get_templates_path()
			);
		}
	}

	/**
	 * Clean the cart and dispatch the data to request
	 *
	 * @param  array $data  The checkout's data.
	 * @return array
	 */
	protected function dispatch( $data ) {
		WC()->cart->empty_cart();

		return $data;
	}

	/**
	 * Save order's meta fields for future use
	 *
	 * @param  WC_Order $order The order created.
	 * @param  Object   $request The request from EBANX success response.
	 *
	 * @return void
	 */
	protected function save_order_meta_fields( $order, $request ) {
		// To save only on DB to internal use.
		update_post_meta( $order->get_id(), '_ebanx_payment_hash', $request->payment->hash );
		update_post_meta( $order->get_id(), '_ebanx_payment_merchant_payment_code', $request->payment->merchant_payment_code );
		update_post_meta( $order->get_id(), '_ebanx_payment_open_date', $request->payment->open_date );

		if ( WC_EBANX_Request::has( 'billing_email' ) ) {
			update_post_meta( $order->get_id(), '_ebanx_payment_customer_email', sanitize_email( WC_EBANX_Request::read( 'billing_email' ) ) );
		}

		if ( WC_EBANX_Request::has( 'billing_phone' ) ) {
			update_post_meta( $order->get_id(), '_ebanx_payment_customer_phone', sanitize_text_field( WC_EBANX_Request::read( 'billing_phone' ) ) );
		}

		if ( WC_EBANX_Request::has( 'billing_address_1' ) ) {
			update_post_meta( $order->get_id(), '_ebanx_payment_customer_address', sanitize_text_field( WC_EBANX_Request::read( 'billing_address_1' ) ) );
		}
	}

	/**
	 * Generates the checkout message
	 *
	 * @param int    $amount The total price of the order.
	 * @param string $currency Possible currencies: BRL.
	 * @param string $country The country code.
	 *
	 * @return string
	 */
	public function get_checkout_message( $amount, $currency, $country ) {
		$price    = wc_price( $amount, array( 'currency' => $currency ) );
		$language = $this->get_language_by_country( $country );

		$texts = array(
			'pt-br' => array(
				'INTRO'                               => 'Total a pagar ',
				WC_EBANX_Constants::CURRENCY_CODE_BRL => 'em Reais',
			),
			'es'    => array(
				'INTRO'                               => 'Total a pagar en ',
				WC_EBANX_Constants::CURRENCY_CODE_BRL => 'Real brasileño',
			),
		);

		$message  = $texts[ $language ]['INTRO'];
		$message .= ! empty( $texts[ $language ][ $currency ] ) ? $texts[ $language ][ $currency ] : $currency;
		$message .= ': <strong class="ebanx-amount-total">' . $price . '</strong>';

		return $message;
	}

	/**
	 *
	 * @param string $country
	 *
	 * @return string
	 */
	protected function get_language_by_country( $country ) {
		$languages = array(
			'ar' => 'es',
			'mx' => 'es',
			'cl' => 'es',
			'pe' => 'es',
			'co' => 'es',
			'ec' => 'es',
			'br' => 'pt-br',
		);
		if ( ! array_key_exists( $country, $languages ) ) {
			return 'pt-br';
		}
		return $languages[ $country ];
	}

	/**
	 *
	 * @param string $country
	 *
	 * @return string
	 */
	protected function get_sandbox_form_message( $country ) {
		$messages = array(
			'pt-br' => 'Ainda estamos testando esse tipo de pagamento. Por isso, a sua compra não será cobrada nem enviada.',
			'es'    => 'Todavia estamos probando este método de pago. Por eso su compra no sera cobrada ni enviada.',
		);

		return $messages[ $this->get_language_by_country( $country ) ];
	}

	/**
	 * @param array $instalment_terms
	 * @param int   $instalment
	 *
	 * @return mixed
	 */
	public static function get_instalment_term( $instalment_terms, $instalment ) {
		foreach ( $instalment_terms as $instalment_term ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName
			if ( $instalment_term->instalmentNumber == $instalment ) {
				return $instalment_term;
			}
		}
	}
}
