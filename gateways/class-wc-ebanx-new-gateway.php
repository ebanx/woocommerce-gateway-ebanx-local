<?php

use Ebanx\Benjamin\Models\Configs\CreditCardConfig;
use Ebanx\Benjamin\Models\Country;
use Ebanx\Benjamin\Models\Currency;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_EBANX_New_Gateway
 */
class WC_EBANX_New_Gateway extends WC_EBANX_Gateway {
	/**
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 *
	 * @var WC_EBANX_Global_Gateway
	 */
	public $configs;

	/**
	 *
	 * @var bool
	 */
	protected $is_sandbox_mode;

	/**
	 *
	 * @var string
	 */
	protected $private_key;

	/**
	 *
	 * @var string
	 */
	protected $public_key;

	/**
	 *
	 * @var \Ebanx\Benjamin\Facade
	 */
	protected $ebanx;

	/**
	 *
	 * @var \Ebanx\Benjamin\Services\Gateways\DirectGateway
	 */
	protected $ebanx_gateway;

	/**
	 *
	 * @var WC_Logger
	 */
	protected $log;

	/**
	 *
	 * @var string
	 */
	public $icon;

	/**
	 *
	 * @var array
	 */
	public $names;

	/**
	 *
	 * @var string
	 */
	protected $merchant_currency;

	/**
	 *
	 * @var string
	 */
	protected $api_name;

	/**
	 *
	 * @var array
	 */
	protected static $ebanx_params = array();

	/**
	 *
	 * @var int
	 */
	protected static $total_gateways = 0;

	/**
	 *
	 * @var int
	 */
	protected static $initialized_gateways = 0;

	/**
	 * Constructor
	 */
	public function __construct() {
		self::$total_gateways++;

		$this->user_id         = get_current_user_id();
		$this->configs         = new WC_EBANX_Global_Gateway();
		$this->is_sandbox_mode = ( 'yes' === $this->configs->settings['sandbox_mode_enabled'] );
		$this->private_key     = $this->is_sandbox_mode ? $this->configs->settings['sandbox_private_key'] : $this->configs->settings['live_private_key'];
		$this->public_key      = $this->is_sandbox_mode ? $this->configs->settings['sandbox_public_key'] : $this->configs->settings['live_public_key'];
		$this->ebanx           = ( new WC_EBANX_Api( $this->configs ) )->ebanx();

		if ( 'yes' === $this->configs->settings['debug_enabled'] ) {
			$this->log = new WC_Logger();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_assets' ), 100 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );

		$this->supports = array(
			'refunds',
		);

		$this->icon              = $this->show_icon();
		$this->names             = $this->get_billing_field_names();
		$this->merchant_currency = strtoupper( get_woocommerce_currency() );
	}

	/**
	 * Insert the necessary assets on checkout page
	 *
	 * @return void
	 */
	public function checkout_assets() {
		if ( is_checkout() ) {
			wp_enqueue_script(
				'woocommerce_ebanx_checkout_fields',
				plugins_url( 'assets/js/checkout-fields.js', WC_EBANX::DIR ),
				array( 'jquery' ),
				WC_EBANX::get_plugin_version(),
				true
			);
			$checkout_params = array(
				'is_sandbox'           => $this->is_sandbox_mode,
				'sandbox_tag_messages' => array(
					'pt-br' => 'EM TESTE',
					'es'    => 'EN PRUEBA',
				),
			);
			wp_localize_script( 'woocommerce_ebanx_checkout_fields', 'wc_ebanx_checkout_params', apply_filters( 'wc_ebanx_checkout_params', $checkout_params ) );
		}

		if ( is_checkout() && $this->is_sandbox_mode ) {
			wp_enqueue_style(
				'woocommerce_ebanx_sandbox_style',
				plugins_url( 'assets/css/sandbox-checkout-alert.css', WC_EBANX::DIR ),
				array(),
				WC_EBANX::get_plugin_version()
			);
		}

		if (
			is_wc_endpoint_url( 'order-pay' ) ||
			is_wc_endpoint_url( 'order-received' ) ||
			is_wc_endpoint_url( 'view-order' ) ||
			is_checkout()
		) {
			wp_enqueue_style(
				'woocommerce_ebanx_paying_via_ebanx_style',
				plugins_url( 'assets/css/paying-via-ebanx.css', WC_EBANX::DIR ),
				array(),
				WC_EBANX::get_plugin_version()
			);

			static::$ebanx_params = array(
				'key'     => $this->public_key,
				'mode'    => $this->is_sandbox_mode ? 'test' : 'production',
				'ajaxurl' => admin_url( 'admin-ajax.php', null ),
			);

			self::$initialized_gateways++;

			if ( self::$initialized_gateways === self::$total_gateways ) {
				wp_localize_script( 'woocommerce_ebanx_credit_card', 'wc_ebanx_params', apply_filters( 'wc_ebanx_params', static::$ebanx_params ) );
			}
		}
	}


	/**
	 * The main method to process the payment that came from WooCommerce checkout
	 * This method checks the information sent by WooCommerce and if they are correct, sends a request to EBANX API
	 * The catch block captures the errors and checks the error code returned by EBANX API and then shows to the user the correct error message
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		try {
			apply_filters( 'ebanx_before_process_payment', $order );

			// Save user's fields.
			$this->save_user_meta_fields( $order );

			if ( $order->get_total() > 0 ) {
				$data = $this->transform_payment_data( $order );

				$response = $this->ebanx_gateway->create( $data );

				WC_EBANX_Checkout_Logger::persist(
					array(
						'request'  => $data,
						'response' => $response,
					)
				);

				$this->process_response( $response, $order );
			} else {
				$order->add_order_note( __( 'EBANX: The order with value 0 was finished.', 'woocommerce-gateway-ebanx' ) );
				$order->payment_complete();
			}

			do_action( 'ebanx_after_process_payment', $order );

			return $this->dispatch(
				array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				)
			);
		} catch ( Exception $e ) {
			$country = $this->get_transaction_address( 'country' );

			$message = WC_EBANX_Errors::get_error_message( $e, $country );

			WC()->session->set( 'refresh_totals', true );
			WC_EBANX::log( "EBANX Error: $message" );
			$order->add_order_note( "EBANX Error: $message" );

			wc_add_notice( $message, 'error' );

			do_action( 'ebanx_process_payment_error', $message );

			return array();
		}
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @return \Ebanx\Benjamin\Models\Payment
	 * @throws Exception Throws parameter missing exception.
	 */
	protected function transform_payment_data( $order ) {
		return WC_EBANX_Payment_Adapter::transform( $order, $this->configs, $this->names, $this->id );
	}

	/**
	 * Get the country from current order on the edit page of wc-admin
	 *
	 * @return string
	 */
	public function get_country_from_current_order_on_admin() {
		global $post;

		if ( empty( $post ) || ! isset( $post->ID ) ) {
			return '';
		}

		if ( ! is_admin() ) {
			return '';
		}

		$order = wc_get_order( (int) $post->ID );
		if ( ! is_a( $order, WC_Order::class ) ) {
			return '';
		}

		return $order->get_billing_country();
	}

	/**
	 * Get the customer's address
	 *
	 * @param  string $attr
	 *
	 * @return boolean|array
	 * @throws Exception Throws parameter missing message.
	 */
	public function get_transaction_address( $attr = '' ) {
		$customer = WC()->customer;
		$country_from_request = WC_EBANX_Request::read( 'billing_country', null );
		$customer_country = empty( $customer ) ? null : $customer->get_billing_country();

		if ( empty( $customer_country ) && empty( $country_from_request ) ) {
			return false;
		}

		$address = [
			'country' => trim( strtolower( $customer_country ) ),
			'address' => $customer->get_billing_address(),
			'state' => $customer->get_billing_state(),
			'city' => $customer->get_billing_city(),
			'postcode' => $customer->get_billing_postcode(),
		];

		if ( ! empty( $country_from_request ) ) {
			$address['country'] = trim( strtolower( $country_from_request ) );
		}

		if ( ! empty( $attr ) && ! empty( $address[ $attr ] ) ) {
			return $address[ $attr ];
		}

		return $address;
	}

	/**
	 * Get the iso country from customer's address or
	 * current order on the edit page of wc-admin
	 *
	 * @return string
	 */
	public function get_iso_country_from_customer_or_order_on_admin() {
		$iso_country = $this->get_transaction_address( 'country' );

		if ( empty( $iso_country ) ) {
			$iso_country = $this->get_country_from_current_order_on_admin();
		}

		return $iso_country;
	}

	/**
	 * Get the country from customer's address or
	 * current order on the edit page of wc-admin
	 *
	 * @return string
	 */
	public function get_country_from_customer_or_order_on_admin() {
		$iso_country = $this->get_iso_country_from_customer_or_order_on_admin();

		return Country::fromIso( $iso_country );
	}

	/**
	 *
	 * @param array    $response
	 * @param WC_Order $order
	 *
	 * @throws WC_EBANX_Payment_Exception Throws error message.
	 * @throws Exception Throws parameter missing exception.
	 */
	protected function process_response( $response, $order ) {
		// translators: placeholder contains request response.
		WC_EBANX::log( sprintf( __( 'Processing response: %s', 'woocommerce-gateway-ebanx' ), print_r( $response, true ) ) );

		if ( 'SUCCESS' !== $response['status'] ) {
			$this->process_response_error( $response, $order );
		}

		// translators: placeholder contains ebanx payment hash.
		$message = sprintf( __( 'Payment approved. Hash: %s', 'woocommerce-gateway-ebanx' ), $response['payment']['hash'] );

		WC_EBANX::log( $message );

		// Save post's meta fields.
		$this->save_order_meta_fields( $order, WC_EBANX_Helper::array_to_object( $response ) );

		$payment_status = $response['payment']['status'];
		if ( 'CO' === $payment_status ) {
			$order->payment_complete( $response['payment']['hash'] );
			$order->add_order_note( $this->get_order_note_from_payment_status( $payment_status ) );
			do_action( 'ebanx_process_response', $order );
			return;
		}

		$order->add_order_note( $this->get_order_note_from_payment_status( $payment_status ) );
		$order->update_status( $this->get_order_status_from_payment_status( $payment_status ) );

		do_action( 'ebanx_process_response', $order );
	}

	/**
	 *
	 * @param array    $response
	 * @param WC_Order $order
	 *
	 * @throws WC_EBANX_Payment_Exception Throws error message.
	 * @throws Exception Throws parameter missing exception.
	 */
	protected function process_response_error( $response, $order ) {
		if (
			isset( $response['payment']['transaction_status'] )
			&& 'NOK' === $response['payment']['transaction_status']['code']
			&& 'EBANX' === $response['payment']['transaction_status']['acquirer']
			&& $this->is_sandbox_mode
		) {
//			throw new Exception( 'SANDBOX-INVALID-CC-NUMBER' );
		}

		$status_code    = ! empty ( $response['status_code'] ) ? $response['status_code'] : 'GENERAL';
		$status_message = ! empty ( $response['status_message'] ) ? $response['status_message'] : '';

		if ( $this->is_refused_credit_card( $response, $status_code ) ) {
			$status_code           = 'REFUSED-CC';
			$status_message = $response['payment']['transaction_status']['description'];
		}

		$error = self::handle_error( $status_code, $status_message );

		$order->update_status( 'failed', $error['message'] );
		$order->add_order_note( $error['message'] );

		do_action( 'ebanx_process_response_error', $order, $error['code'] );

		throw new WC_EBANX_Payment_Exception( $error['message'], $error['code'] );
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @throws Exception Throws parameter missing exception.
	 */
	protected function save_user_meta_fields( $order ) {
		if ( ! $this->user_id ) {
			$this->user_id = get_current_user_id();
		}

		if ( ! isset( $this->user_id ) ) {
			return;
		}

		$country  = trim( strtolower( $order->get_billing_country() ) );
		$document = $this->save_document( $country );

		if ( false !== $document ) {
			update_user_meta( $this->user_id, '_ebanx_document', $document );
		}
	}

	/**
	 *
	 * @param int    $order_id
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order = wc_get_order( $order_id );
			$hash  = get_post_meta( $order_id, '_ebanx_payment_hash', true );

			do_action( 'ebanx_before_process_refund', $order, $hash );

			if ( ! $order || is_null( $amount ) || ! $hash ) {
				return false;
			}

			if ( empty( $reason ) ) {
				$reason = __( 'No reason specified.', 'woocommerce-gateway-ebanx' );
			}

			$split = self::get_split_if_exists( $order_id );

			$response = $this->ebanx->refund()->requestByHashWithSplit( $hash, $amount, $reason, '', $split );

			WC_EBANX_Refund_Logger::persist(
				array(
					'request'  => array( $hash, $amount, $reason ),
					'response' => $response, // Response from request to EBANX.
				)
			);

			if ( 'SUCCESS' !== $response['status'] ) {
				do_action( 'ebanx_process_refund_error', $order, $response );

				switch ( $response['status_code'] ) {
					case 'BP-REF-7':
						$message = __( 'The payment cannot be refunded because it is not confirmed.', 'woocommerce-gateway-ebanx' );
						break;
					default:
						$message = $response['status_message'];
				}

				return new WP_Error( 'ebanx_process_refund_error', $message );
			}

			$refunds = $response['payment']['refunds'];

			// translators: plasceholders contain amount, refund id and reason.
			$order->add_order_note( sprintf( __( 'EBANX: Refund requested. %1$s - Refund ID: %2$s - Reason: %3$s.', 'woocommerce-gateway-ebanx' ), wc_price( $amount ), $response['refund']['id'], $reason ) );

			update_post_meta( $order_id, '_ebanx_payment_refunds', $refunds );

			do_action( 'ebanx_after_process_refund', $order, $response, $refunds );

			return true;
		} catch ( Exception $e ) {
			return new WP_Error( 'ebanx_process_refund_error', __( 'We could not finish processing this refund. Please try again.', 'woocommerce-gateway-ebanx' ) );
		}
	}

	/**
	 * Create the hooks to process cash payments
	 *
	 * @param  array  $codes
	 * @param  string $notification_type
	 *
	 * @return WC_Order
	 */
	final public function process_hook( array $codes, $notification_type ) {
		do_action( 'ebanx_before_process_hook', $codes, $notification_type );

		WC_EBANX_Notification_Received_Logger::persist( array( 'data' => $_GET ) );

		if ( isset( $codes['hash'] ) && ! empty( $codes['hash'] ) && isset( $codes['merchant_payment_code'] ) && ! empty( $codes['merchant_payment_code'] ) ) {
			unset( $codes['merchant_payment_code'] );
		}

		$data = $this->ebanx->paymentInfo()->findByHash( $codes['hash'], $this->is_sandbox_mode );

		WC_EBANX_Notification_Query_Logger::persist(
			array(
				'codes' => $codes,
				'data'  => $data,
			)
		);

		$order_id = WC_EBANX_Helper::get_post_id_by_meta_key_and_value( '_ebanx_payment_hash', $data['payment']['hash'] );

		$order = new WC_Order( $order_id );

		switch ( strtoupper( $notification_type ) ) {
			case 'REFUND':
				$this->process_refund_hook( $order, $data );

				break;
			case 'UPDATE':
				$this->update_payment( $order, $data );

				break;
		};

		do_action( 'ebanx_after_process_hook', $order, $notification_type );

		return $order;
	}

	/**
	 * Updates the payment when receive a notification from EBANX
	 *
	 * @param WC_Order $order
	 * @param array    $data
	 * @return void
	 */
	final public function update_payment( $order, $data ) {
		$request_status = strtoupper( $data['payment']['status'] );

		if ( 'completed' === $order->get_status() && 'CA' === $request_status ) {
			$order->add_order_note( sprintf( __( 'EBANX: The notification about change payment status was ignored, payment already Completed.', 'woocommerce-gateway-ebanx' ) ) );
			return;
		}

		$status     = array(
			'CO' => 'Confirmed',
			'CA' => 'Canceled',
			'PE' => 'Pending',
			'OP' => 'Opened',
		);
		$new_status = null;
		$old_status = $order->get_status();

		switch ( $request_status ) {
			case 'CO':
				$order->payment_complete( $data['payment']['hash'] );
				break;
			case 'CA':
				$new_status = 'failed';
				break;
			case 'PE':
				$new_status = 'on-hold';
				break;
			case 'OP':
				$new_status = 'pending';
				break;
		}

		if ( isset( $new_status ) && $new_status !== $old_status ) {
			$payment_status = $status[ $data['payment']['status'] ];
			// translators: placeholder contains a status updated.
			$order->add_order_note( sprintf( __( 'EBANX: The payment has been updated to: %s.', 'woocommerce-gateway-ebanx' ), $payment_status ) );
			$order->update_status( $new_status );
		}
	}

	/**
	 * Updates the refunds when receivers a EBANX refund notification
	 *
	 * @param WC_Order $order
	 * @param array    $data
	 * @return void
	 */
	final public function process_refund_hook( $order, $data ) {
		$refunds = current( get_post_meta( $order->get_id(), '_ebanx_payment_refunds' ) );

		foreach ( $refunds as $k => $ref ) {
			foreach ( $data['payment']['refunds'] as $refund ) {
				if ( $ref['id'] === $refund['id'] ) {
					if ( 'CO' === $refund['status'] && 'CO' !== $refunds[ $k ]['status'] ) {
						// translators: placeholder contains refund id.
						$order->add_order_note( sprintf( __( 'EBANX: Your Refund was confirmed to EBANX - Refund ID: %s', 'woocommerce-gateway-ebanx' ), $refund['id'] ) );
					}
					if ( 'CA' === $refund['status'] && 'CA' !== $refunds[ $k ]['status'] ) {
						// translators: placeholder contains refund id.
						$order->add_order_note( sprintf( __( 'EBANX: Your Refund was canceled to EBANX - Refund ID: %s', 'woocommerce-gateway-ebanx' ), $refund['id'] ) );
					}

					$refunds[ $k ]['status']       = $refund['status'];
					$refunds[ $k ]['cancel_date']  = $refund['cancel_date'];
					$refunds[ $k ]['request_date'] = $refund['request_date'];
					$refunds[ $k ]['pending_date'] = $refund['pending_date'];
					$refunds[ $k ]['confirm_date'] = $refund['confirm_date'];
				}
			}
		}

		update_post_meta( $order->get_id(), '_ebanx_payment_refunds', $refunds );
	}

	/**
	 *
	 * @param string $status
	 *
	 * @return string
	 */
	private function get_order_note_from_payment_status( $status ) {
		$notes = array(
			'CO' => __( 'EBANX: The transaction was paid.', 'woocommerce-gateway-ebanx' ),
			'PE' => __( 'EBANX: The order is awaiting payment.', 'woocommerce-gateway-ebanx' ),
			'OP' => __( 'EBANX: The payment was opened.', 'woocommerce-gateway-ebanx' ),
			'CA' => __( 'EBANX: The payment has failed.', 'woocommerce-gateway-ebanx' ),
		);

		return $notes[ strtoupper( $status ) ];
	}

	/**
	 *
	 * @param string $payment_status
	 *
	 * @return string
	 */
	private function get_order_status_from_payment_status( $payment_status ) {
		$order_status = array(
			'CO' => 'processing',
			'PE' => 'on-hold',
			'CA' => 'failed',
			'OP' => 'pending',
		);

		return $order_status[ strtoupper( $payment_status ) ];
	}

	/**
	 *
	 * @param array  $response
	 * @param string $code
	 *
	 * @return bool
	 */
	private function is_refused_credit_card( $response, $code ) {
		return 'GENERAL' === $code
				&& array_key_exists( 'payment', $response )
				&& is_array( $response['payment'] )
				&& array_key_exists( 'transaction_status', $response['payment'] )
				&& is_array( $response['payment']['transaction_status'] )
				&& array_key_exists( 'code', $response['payment']['transaction_status'] )
				&& 'NOK' === $response['payment']['transaction_status']['code'];
	}

	/**
	 *
	 * @param string $country
	 *
	 * @return string|bool
	 * @throws Exception Throws parameter missing exception.
	 */
	private function save_document( $country ) {
		if ( 'ebanx-credit-card-international' === $this->id && WC_EBANX_Request::has( 'ebanx_billing_foreign_document' ) ) {
			$foreign_document = sanitize_text_field( WC_EBANX_Request::read( 'ebanx_billing_foreign_document', null ) );
			update_user_meta( $this->user_id, '_ebanx_billing_foreign_document', $foreign_document );

			return $foreign_document;
		}

		$field_name = 'document';

		if ( WC_EBANX_Constants::COUNTRY_BRAZIL === $country ) {
			$person_type = sanitize_text_field( WC_EBANX_Request::read( $this->names['ebanx_billing_brazil_person_type'], null ) );
			if ( ! empty ( $person_type ) ) {
				update_user_meta( $this->user_id, '_ebanx_billing_brazil_person_type', $person_type );
			}

			if ( ( 'cnpj' === $person_type || '2' == $person_type || 'pessoa jurídica' === strtolower( $person_type ) )
				&& WC_EBANX_Request::has( $this->names['ebanx_billing_brazil_cnpj'] ) ) {
				$field_name = 'cnpj';
			}
		}

		$country = strtolower( Country::fromIso( strtoupper( $country ) ) );

		if ( ! WC_EBANX_Request::has( $this->names[ 'ebanx_billing_' . $country . '_' . $field_name ] ) ) {
			return false;
		}

		$document = sanitize_text_field( WC_EBANX_Request::read( $this->names[ 'ebanx_billing_' . $country . '_' . $field_name ] ) );

		update_user_meta( $this->user_id, '_ebanx_billing_' . $country . '_' . $field_name, $document );

		return $document;
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

	private function get_split_if_exists( $order_id ) {
		$split = get_post_meta( $order_id, '_ebanx_order_split_rules', true );

		return ! empty( $split ) && is_array( $split ) ? $split : [];
	}

	/**
	 * @param $code
	 * @param $message
	 *
	 * @return array
	 */
	private static function handle_error( $code, $message ) {
		if ( has_filter( 'handdle_ebanx_response_error_filter' ) ) {
			return [
				'code' => 'CUSTOMIZED_ERROR_MESSAGE',
				'message' => apply_filters( 'handdle_ebanx_response_error_filter', $message, $code ),
			];
		}

		return [
			'code' => $code,
			// translators: placeholders contain bp-dr code and corresponding message.
			'message' => sprintf( __( 'EBANX: An error occurred: %1$s - %2$s', 'woocommerce-gateway-ebanx' ), $code, $message ),
		];
	}
}
