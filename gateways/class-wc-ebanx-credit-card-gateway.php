<?php

use Ebanx\Benjamin\Models\Configs\CreditCardConfig;
use Ebanx\Benjamin\Models\Country;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_EBANX_Credit_Card_Gateway
 */
abstract class WC_EBANX_Credit_Card_Gateway extends WC_EBANX_New_Gateway {

	/**
	 * Max length of card token
	 *
	 * @var int
	 */
	const MAX_LENGTH_CARD_TOKEN = 128;

	/**
	 * Min length of card token
	 *
	 * @var int
	 */
	const MIN_LENGTH_CARD_TOKEN = 32;

	/**
	 * Max length of masked card number
	 *
	 * @var int
	 */
	const MAX_LENGTH_MASKED_CARD_NUMBER = 19;

	/**
	 * Min length of masked card number
	 *
	 * @var int
	 */
	const MIN_LENGTH_MASKED_CARD_NUMBER = 14;

	/**
	 * The rates for each instalment
	 *
	 * @var array
	 */
	protected $instalment_rates = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_name = '_creditcard';

		parent::__construct();

		$this->ebanx         = ( new WC_EBANX_Api( $this->configs ) )->ebanx();
		$this->ebanx_gateway = $this->ebanx->creditCard( new CreditCardConfig() );

		add_action( 'woocommerce_order_edit_status', array( $this, 'capture_payment_action' ), 10, 2 );

		$this->supports = array(
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
//			'subscription_payment_method_change_admin'
		);

		add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
		add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );

		add_action( 'wcs_default_retry_rules', array( $this, 'retryRules' ) );
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'scheduled_subscription_payment' ) );
	}

	/**
	 *
	 * @return array
	 */
	public function retryRules() {
		return array(
			array(
				'retry_after_interval'            => DAY_IN_SECONDS,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => 2 * DAY_IN_SECONDS,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
		);
	}

	/**
	 * Process scheduled subscription payments.
	 *
	 * @param string $subscription_id subscription ID.
	 *
	 * @return bool|void
	 * @throws Exception Shows missing params message.
	 */
	public function scheduled_subscription_payment( $subscription_id ) {
		global $counter;
		$counter++;

		if ( 1 < $counter ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		$country = $this->get_transaction_address( 'country' );

		$card = self::get_card_to_renew_subscription( $subscription );

		if ( ! empty( $card['token'] ) ) {
			try {
				$payment = WC_EBANX_Payment_Adapter::transform_card_subscription_payment( $subscription, $this->configs, $card['token'], $card['brand'] );

				$response = $this->ebanx->creditCard( $this->get_credit_card_config( $country ) )->create( $payment );
			} catch (WC_EBANX_Payment_Exception $exception ) {
				$subscription->payment_failed();
				$subscription->add_order_note(
					sprintf(
					'%s %s',
						__( 'EBANX: Transaction Failed. Reason:', 'woocommerce-gateway-ebanx' ),
						$exception->getMessage()
					)
				);

				return false;
			}

			WC_EBANX_Subscription_Renewal_Logger::persist(
				array(
					'subscription_id' => $subscription_id,
					'payment_method'  => $this->id,
					'request'         => $payment,
					'response'        => $response, // Response from response to EBANX.
				)
			);

			if ( 'ERROR' === $response['status'] ) {
				$subscription->payment_failed();
				// translators: placeholders contain bp-dr code and corresponding message.
				$error_message = sprintf( __( 'EBANX: An error occurred: %1$s - %2$s', 'woocommerce-gateway-ebanx' ), $response['status_code'], $response['status_message'] );
				$subscription->add_order_note( $error_message );

				WC_EBANX::log( $response['status_message'] );

				return false;
			}

			if ( 'SUCCESS' === $response['status'] ) {
				switch ( $response['payment']['status'] ) {
					case 'CO':
						$subscription->payment_complete( $response['payment']['hash'] );
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $subscription );
						$subscription->add_order_note( __( 'EBANX: Transaction Received', 'woocommerce-gateway-ebanx' ) );
						break;
					case 'CA':
						$subscription->payment_failed();
						$subscription->add_order_note(
							sprintf(
								'%s %s',
								__( 'EBANX: Transaction Failed. Reason:', 'woocommerce-gateway-ebanx' ),
								$response['payment']['transaction_status']['description']
							)
						);
						break;
					case 'OP':
						$subscription->payment_failed();
						$subscription->add_order_note( __( 'EBANX: Transaction Pending', 'woocommerce-gateway-ebanx' ) );
						break;
				}
			}

			return true;
		}

		$subscription->add_order_note( 'EBANX: Token not found to renew subscriptions.');

		$parent_order = $subscription->get_parent();

		if ( $parent_order ) {
			$payment_method = get_post_meta( $parent_order->get_id(), '_payment_method', true );
			$subscription->add_order_note( sprintf( 'EBANX: The parent order %s was processed by %s', $parent_order->get_id(), $payment_method ) );
		}

		WC_Subscriptions_Manager::expire_subscriptions_for_order( $subscription );

		return false;
	}

	/**
	 * Check the Auto Capture
	 *
	 * @param  array $actions
	 * @return array
	 */
	public function auto_capture( $actions ) {
		if ( is_array( $actions ) ) {
			$actions['custom_action'] = __( 'Capture by EBANX', 'woocommerce-gateway-ebanx' );
		}

		return $actions;
	}

	/**
	 *
	 * @param int    $order_id
	 * @param string $status
	 *
	 * @throws Exception Throws missing parameter exception.
	 */
	public function capture_payment_action( $order_id, $status ) {
		$action = WC_EBANX_Request::read( 'action', null );
		$order  = wc_get_order( $order_id );

		if ( $order->get_payment_method() !== $this->id
			|| 'processing' !== $status
			|| 'woocommerce_mark_order_status' !== $action ) {
			return;
		}

		WC_EBANX_Capture_Payment::capture_payment( $order_id );
	}

	/**
	 * Insert the necessary assets on checkout page
	 *
	 * @return void
	 */
	public function checkout_assets() {
		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-credit-card-form' );
			wp_enqueue_script( 'woocommerce_ebanx_jquery_mask', plugins_url( 'assets/js/jquery-mask.js', WC_EBANX::DIR ), array( 'jquery' ), WC_EBANX::get_plugin_version(), true );
			wp_enqueue_script( 'woocommerce_ebanx_credit_card', plugins_url( 'assets/js/credit-card.js', WC_EBANX::DIR ), array( 'jquery', 'ebanx_libjs' ), WC_EBANX::get_plugin_version(), true );

			// If we're on the checkout page we need to pass ebanx.js the address of the order.
			if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
				$order_key = urldecode( $_GET['order'] );
				$order_id  = absint( $_GET['order_id'] );
				$order     = wc_get_order( $order_id );

				if ( $order->get_id() === $order_id && $order->get_order_key() === $order_key ) {
					static::$ebanx_params['billing_first_name'] = $order->get_billing_first_name();
					static::$ebanx_params['billing_last_name']  = $order->get_billing_last_name();
					static::$ebanx_params['billing_address_1']  = $order->get_billing_address_1();
					static::$ebanx_params['billing_address_2']  = $order->get_billing_address_2();
					static::$ebanx_params['billing_state']      = $order->get_billing_state();
					static::$ebanx_params['billing_city']       = $order->get_billing_city();
					static::$ebanx_params['billing_postcode']   = $order->get_billing_postcode();
					static::$ebanx_params['billing_country']    = $order->get_billing_country();
				}
			}
		}

		parent::checkout_assets();
	}

	/**
	 * Mount the data to send to EBANX API
	 *
	 * @param WC_Order $order
	 * @return \Ebanx\Benjamin\Models\Payment
	 * @throws Exception When missing card params or when missing device fingerprint.
	 */
	protected function transform_payment_data( $order ) {
		if ( empty( WC_EBANX_Request::read( 'ebanx_token', null ) )
			|| empty( WC_EBANX_Request::read( 'ebanx_masked_card_number', null ) )
			|| empty( WC_EBANX_Request::read( 'ebanx_brand', null ) )
			|| empty( WC_EBANX_Request::read( 'ebanx_billing_cvv', null ) )
		) {
			throw new Exception( 'MISSING-CARD-PARAMS' );
		}

		if ( empty( WC_EBANX_Request::read( 'ebanx_is_one_click', null ) ) && empty( WC_EBANX_Request::read( 'ebanx_device_fingerprint', null ) ) ) {
			throw new Exception( 'MISSING-DEVICE-FINGERPRINT' );
		}

		return WC_EBANX_Payment_Adapter::transform_card( $order, $this->configs, $this->names, $this->id );
	}

	/**
	 *
	 * @param array    $request
	 * @param WC_Order $order
	 *
	 * @throws Exception Throws missing parameter exception.
	 * @throws WC_EBANX_Payment_Exception Throws error message.
	 */
	protected function process_response( $request, $order ) {
		if ( 'SUCCESS' !== $request['status'] || ! $request['payment']['pre_approved'] ) {
			$this->process_response_error( $request, $order );
		}

		parent::process_response( $request, $order );
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
		parent::save_order_meta_fields( $order, $request );

		update_post_meta( $order->get_id(), '_cards_brand_name', $request->payment->payment_type_code );
		update_post_meta( $order->get_id(), '_instalments_number', $request->payment->instalments );
		update_post_meta( $order->get_id(), '_masked_card_number', WC_EBANX_Request::read( 'ebanx_masked_card_number' ) );
	}

	/**
	 * Save user's meta fields for future use
	 *
	 * @param  WC_Order $order The order created.
	 *
	 * @return void
	 */
	protected function save_user_meta_fields( $order ) {
		parent::save_user_meta_fields( $order );

		if ( ! $this->user_id ) {
			$this->user_id = $order->get_user_id();
		}

		if ( ! $this->user_id
			|| $this->get_setting_or_default( 'save_card_data', 'no' ) !== 'yes'
			|| ! WC_EBANX_Request::has( 'ebanx-save-credit-card' )
			|| WC_EBANX_Request::read( 'ebanx-save-credit-card' ) !== 'yes' ) {
			return;
		}

		$cards = get_user_meta( $this->user_id, '_ebanx_credit_card_token', true );
		$cards = ! empty( $cards ) ? $cards : array();

		$ebanx_token              = WC_EBANX_Request::read( 'ebanx_token', null );
		$ebanx_brand              = WC_EBANX_Request::read( 'ebanx_brand', null );
		$ebanx_masked_card_number = WC_EBANX_Request::read( 'ebanx_masked_card_number', null );

		$card = new \stdClass();

		$card->token         = $ebanx_token;
		$card->brand         = $ebanx_brand;
		$card->masked_number = $ebanx_masked_card_number;

		foreach ( $cards as $saved_card ) {
			if ( empty( $saved_card ) ) {
				continue;
			}

			if ( $saved_card->masked_number === $card->masked_number && $saved_card->brand === $card->brand ) {
				$saved_card->token = $card->token;
				unset( $card );
			}
		}

		if ( isset( $card ) ) {
			$cards[] = $card;
		}

		update_user_meta( $this->user_id, '_ebanx_credit_card_token', array_values( $cards ) );
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
		// TODO: check if is token or new credit card.
		$has_instalments = ( WC_EBANX_Request::has( 'ebanx_billing_instalments', false ) || WC_EBANX_Request::has( 'ebanx-credit-card-installments', false ) );
		$billing_country = trim( strtolower( get_post_meta( $order_id, '_billing_country', true ) ) );
		$country_abbr    = empty( $billing_country ) ? strtolower( WC_EBANX_Constants::DEFAULT_COUNTRY ) : $billing_country;

		$this->ebanx_gateway = $this->ebanx->creditCard( $this->get_credit_card_config( $country_abbr ) );

		if ( $has_instalments ) {
			$country     = Country::fromIso( $country_abbr );
			$total_price = get_post_meta( $order_id, '_order_total', true );
			// TODO: check if is token or new credit card.
			$instalments     = WC_EBANX_Request::has( 'ebanx_billing_instalments' ) ? WC_EBANX_Request::read( 'ebanx_billing_instalments' ) : WC_EBANX_Request::read( 'ebanx-credit-card-installments', 1 );
			$instalment_term = self::get_instalment_term( $this->ebanx_gateway->getPaymentTermsForCountryAndValue( $country, $total_price ), $instalments );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$total_price = $instalment_term->baseAmount;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$total_price *= $instalment_term->instalmentNumber;
			update_post_meta( $order_id, '_order_total', $total_price );
		}

		self::save_subscription_credit_card_if_necessary( $order_id );

		return parent::process_payment( $order_id );
	}

	/**
	 * The page of order received, we call them as "Thank you pages"
	 *
	 * @param  WC_Order $order The order created.
	 * @return void
	 */
	public static function thankyou_page( $order ) {
		$instalments_number = get_post_meta( $order->get_id(), '_instalments_number', true );

		$order_amount       = $order->get_total();
		$instalments_number = ! empty( $instalments_number ) ? $instalments_number : 1;
		$currency           = $order->get_currency();

		$data = array(
			'data'         => array(
				'card_brand_name'    => get_post_meta( $order->get_id(), '_cards_brand_name', true ),
				'instalments_number' => $instalments_number,
				'instalments_amount' => wc_price( round( $order_amount / $instalments_number, 2 ), array( 'currency' => $currency ) ),
				'masked_card'        => substr( get_post_meta( $order->get_id(), '_masked_card_number', true ), -4 ),
				'customer_email'     => $order->get_billing_email(),
				'customer_name'      => $order->get_billing_first_name(),
				'total'              => wc_price( $order_amount, array( 'currency' => $currency ) ),
				'hash'               => get_post_meta( $order->get_id(), '_ebanx_payment_hash', true ),
			),
			'order_status' => $order->get_status(),
			'method'       => $order->get_payment_method(),
		);

		parent::thankyou_page( $data );
	}

	/**
	 * Calculates the interests and values of items based on interest rates settings
	 *
	 * @param string $country
	 * @param int    $amount
	 *
	 * @return array   An array of instalment with price, amount, if it has interests and the number
	 */
	public function get_payment_terms( $country, $amount ) {
		$credit_card_gateway = $this->ebanx->creditCard( $this->get_credit_card_config( $country ) );
		$country_full_name   = Country::fromIso( $country );
		$instalments_terms   = $credit_card_gateway->getPaymentTermsForCountryAndValue( $country_full_name, $amount );

		foreach ( $instalments_terms as $term ) {
			$instalments[] = array(
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName
				'price'        => $term->baseAmount,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName
				'has_interest' => $term->hasInterests,
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName
				'number'       => $term->instalmentNumber,
			);
		}

		try {
			$apply_filters = apply_filters( 'ebanx_get_payment_terms', $instalments );
		} catch ( Exception $e ) {
			return array();
		}

		return $apply_filters;
	}

	/**
	 *
	 * @param string $country
	 *
	 * @return string
	 */
	public static function get_instalment_title_by_country( $country ) {
		if ( WC_EBANX_Constants::COUNTRY_BRAZIL === $country ) {
			return 'Número de parcelas';
		}

		return 'Instalments number';
	}

	/**
	 * The HTML structure on checkout page
	 *
	 * @throws Exception Throws missing param message.
	 */
	public function payment_fields() {
		$cart_total = $this->get_order_total();

		$cards = array();

		$save_card = $this->get_setting_or_default( 'save_card_data', 'no' ) === 'yes';

		if ( $save_card ) {
			$cards = array_filter(
				(array) get_user_meta(
					$this->user_id,
					'_ebanx_credit_card_token',
					true
				),
				function ( $card ) {
					return ! empty( $card->brand ) && ! empty( $card->token ) && ! empty( $card->masked_number );
				}
			);
		}

		$country           = $this->get_transaction_address( 'country' );
		$instalments_terms = $this->get_payment_terms( $country, $cart_total );

		$currency = WC_EBANX_Constants::$local_currencies[ $country ];

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
				'currency'            => $currency,
				'country'             => $country,
				'selected_instalment' => WC_EBANX_Request::read( 'ebanx_billing_instalments', 1 ),
				'instalments_terms'   => $instalments_terms,
				'currency_code'       => $this->currency_code,
				'cards'               => (array) $cards,
				'cart_total'          => $cart_total,
				'place_order_enabled' => $save_card,
				'instalments'         => self::get_instalment_title_by_country( $country ),
				'id'                  => $this->id,
				'add_tax'             => false,
				'with_interest'       => WC_EBANX_Constants::COUNTRY_BRAZIL === $country ? ' com taxas' : '',
				'names'               => $this->names,
			),
			'woocommerce/ebanx/',
			WC_EBANX::get_templates_path()
		);
	}

	/**
	 *
	 * @param string $country_abbr
	 *
	 * @return CreditCardConfig
	 */
	private function get_credit_card_config( $country_abbr ) {
		$currency_code = strtolower( get_woocommerce_currency() );

		$credit_card_config = new CreditCardConfig(
			array(
				'maxInstalments'      => $this->configs->settings[ "{$country_abbr}_credit_card_instalments" ],
				'minInstalmentAmount' => isset( $this->configs->settings[ "{$country_abbr}_min_instalment_value_$currency_code" ] ) ? $this->configs->settings[ "{$country_abbr}_min_instalment_value_$currency_code" ] : null,
			)
		);

		for ( $i = 1; $i <= $this->configs->settings[ "{$country_abbr}_credit_card_instalments" ]; $i++ ) {
			$credit_card_config->addInterest( $i, floatval( $this->configs->settings[ "{$country_abbr}_interest_rates_" . sprintf( '%02d', $i ) ] ) );
		}

		return $credit_card_config;
	}

	/**
	 * @param WC_Subscription $subscription
	 * @return array
	 */
	private static function get_card_to_renew_subscription( $subscription ) {
		$ebanx_token              = get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_token', true );
		$ebanx_brand              = get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_brand', true );
		$ebanx_masked_card_number = get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_masked_number', true );

		if ( ! empty( $ebanx_token ) ) {
			$subscription->add_order_note( __( 'EBANX: Subscription credit card selected for renewal.', 'woocommerce-gateway-ebanx' ) );
			return [
				'token'              => $ebanx_token,
				'brand'              => $ebanx_brand,
				'masked_card_number' => $ebanx_masked_card_number,
			];
		} else {
			$orders_list = $subscription->get_related_orders( 'ids', array( 'parent', 'renewal' ) );
			foreach ( $orders_list as $order_id ) {
				$ebanx_token              = get_post_meta( $order_id, '_ebanx_subscription_credit_card_token', true );
				$ebanx_brand              = get_post_meta( $order_id, '_ebanx_subscription_credit_card_brand', true );
				$ebanx_masked_card_number = get_post_meta( $order_id, '_ebanx_subscription_credit_card_masked_number', true );

				$subscription->add_order_note( __( 'EBANX: Order credit card selected for renewal.', 'woocommerce-gateway-ebanx' ) );

				if ( ! empty( $ebanx_brand ) && ! empty ( $ebanx_token ) && ! empty( $ebanx_masked_card_number ) ) {
					return [
						'token'              => $ebanx_token,
						'brand'              => $ebanx_brand,
						'masked_card_number' => $ebanx_masked_card_number,
					];
				}
			}
		}

		$user_cc = get_user_meta( $subscription->get_customer_id(), '_ebanx_credit_card_token', true );

		$last_user_cc = end($user_cc);

		$user_cc_token      = ! empty( $last_user_cc->token ) ? $last_user_cc->token : null;
		$user_cc_brand      = ! empty( $last_user_cc->brand ) ? $last_user_cc->brand : null;
		$masked_card_number = ! empty( $last_user_cc->brand ) ? $last_user_cc->masked_card_number : null;

		$subscription->add_order_note( __( 'EBANX: User credit card selected for renewal.', 'woocommerce-gateway-ebanx' ) );
		return [
			'token'              => $user_cc_token,
			'brand'              => $user_cc_brand,
			'masked_card_number' => $masked_card_number,
		];
	}

	/**
	 * @param int $order_id
	 */
	private static function save_subscription_credit_card_if_necessary( $order_id ) {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

 		$is_or_contain_subscription = wcs_is_subscription( $order_id ) || wcs_order_contains_subscription( $order, 'any' );

		if ( ! $is_or_contain_subscription ) {
			return;
		}

		$subscription_renewal_id = get_post_meta( $order_id, '_subscription_renewal', true );
		$subscription_id         = ! empty( $subscription_renewal_id ) ? (int) $subscription_renewal_id : $order_id;

		if ( $order_id === $subscription_id ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			if ( ! empty( $subscriptions ) ) {
				$subscription_id = end( $subscriptions )->get_id();
			}
		}

		$ebanx_token              = WC_EBANX_Request::read( 'ebanx_token', null );
		$ebanx_brand              = WC_EBANX_Request::read( 'ebanx_brand', null );
		$ebanx_masked_card_number = WC_EBANX_Request::read( 'ebanx_masked_card_number', null );

		if ( ! empty( $ebanx_token ) ) {
			update_post_meta( $subscription_id, '_ebanx_subscription_credit_card_token', $ebanx_token );
			update_post_meta( $subscription_id, '_ebanx_subscription_credit_card_brand', $ebanx_brand );
			update_post_meta( $subscription_id, '_ebanx_subscription_credit_card_masked_number', $ebanx_masked_card_number );
			$order->add_order_note( __( 'EBANX: The subscription credit card was saved.', 'woocommerce-gateway-ebanx' ) );
		}
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_ebanx_subscription_credit_card_token' => array(
					'value' => get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_token', true ),
					'label' => 'EBANX Card token',
				),
				'_ebanx_subscription_credit_card_brand' => array(
					'value' => get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_brand', true ),
					'label' => 'EBANX Card Brand',
				),
				'_ebanx_subscription_credit_card_masked_number' => array(
					'value' => get_post_meta( $subscription->get_id(), '_ebanx_subscription_credit_card_masked_number', true ),
					'label' => 'EBANX Masked Card Number',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array $payment_meta associative array of meta data required for automatic payments
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			if ( ! isset( $payment_meta['post_meta']['_ebanx_subscription_credit_card_token']['value'] ) || empty( $payment_meta['post_meta']['_ebanx_subscription_credit_card_token']['value'] ) ) {
				throw new Exception( 'A card token value is required.' );
			} elseif ( strlen( $payment_meta['post_meta']['_ebanx_subscription_credit_card_token']['value'] ) > self::MAX_LENGTH_CARD_TOKEN) {
				throw new Exception( 'Invalid card token. A valid card token must have length less or equal 128.' );
			} elseif ( strlen( $payment_meta['post_meta']['_ebanx_subscription_credit_card_token']['value'] ) < self::MIN_LENGTH_CARD_TOKEN) {
				throw new Exception( 'Invalid card token. A valid card token must have length greater or equal 32.' );
			}

			$brands = array( 'amex', 'aura', 'discover', 'elo', 'hipercard', 'visa', 'mastercard' );
			if ( ! isset( $payment_meta['post_meta']['_ebanx_subscription_credit_card_brand']['value'] ) || empty( $payment_meta['post_meta']['_ebanx_subscription_credit_card_brand']['value'] ) ) {
				throw new Exception( 'A card brand value is required.' );
			} elseif ( !in_array( $payment_meta['post_meta']['_ebanx_subscription_credit_card_brand']['value'], $brands ) ) {
				throw new Exception( 'Invalid card brand. Card brand is not supported.' );
			}

			if ( ! isset( $payment_meta['post_meta']['_ebanx_subscription_credit_card_masked_number']['value'] ) || empty( $payment_meta['post_meta']['_ebanx_subscription_credit_card_masked_number']['value'] ) ) {
				throw new Exception( 'A masked card number value is required.' );
			} elseif ( strlen( $payment_meta['post_meta']['_ebanx_subscription_credit_card_masked_number']['value'] ) > self::MAX_LENGTH_MASKED_CARD_NUMBER) {
				throw new Exception( 'Invalid masked card number. A valid masked card number must have length less or equal 128.' );
			} elseif ( strlen( $payment_meta['post_meta']['_ebanx_subscription_credit_card_masked_number']['value'] ) < self::MIN_LENGTH_MASKED_CARD_NUMBER) {
				throw new Exception( 'Invalid masked card number. A valid masked card number must have length greater or equal 32.' );
			}

		}
	}
}
