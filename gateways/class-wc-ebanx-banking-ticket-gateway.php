<?php

use Ebanx\Benjamin\Models\Country;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_EBANX_Banking_Ticket_Gateway
 */
class WC_EBANX_Banking_Ticket_Gateway extends WC_EBANX_New_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id            = 'ebanx-banking-ticket';
		$this->method_title  = __( 'EBANX - Banking Ticket', 'woocommerce-gateway-ebanx' );
		$this->currency_code = WC_EBANX_Constants::CURRENCY_CODE_BRL;
		$this->api_name      = 'boleto';
		$this->title         = 'Boleto bancário';
		$this->description   = 'Pague com boleto bancário.';

		parent::__construct();

		$this->ebanx_gateway = $this->ebanx->boleto();

		$this->enabled = is_array( $this->configs->settings['brazil_payment_methods'] ) ? in_array( $this->id, $this->configs->settings['brazil_payment_methods'], true ) ? 'yes' : false : false;

		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_banking_ticket_instructions' ), 50, 3 );
	}

	/**
	 * Add banking ticket link on email.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 * @param  bool   $plain_text    Plain text or HTML.
	 *
	 * @return string                Payment instructions.
	 */
	public function email_banking_ticket_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! $order->has_status( array( 'on-hold', 'processing' ) ) || 'ebanx-banking-ticket' !== $this->id ) {
			return;
		}

		$boleto_url = get_post_meta( $order->get_id(), '_boleto_url', true );

		$data = array(
			'boleto_url' => $boleto_url,
		);

		if ( ! empty( $boleto_url ) ) {
			if ( $plain_text ) {
				wc_get_template(
					'banking-ticket/email-plain-instructions.php',
					$data,
					'woocommerce/ebanx/',
					WC_EBANX::get_templates_path()
				);
			} else {
				wc_get_template(
					'banking-ticket/email-html-instructions.php',
					$data,
					'woocommerce/ebanx/',
					WC_EBANX::get_templates_path()
				);
			}
		}
	}

	/**
	 * Check if the method is available to show to the users
	 *
	 * @return boolean
	 * @throws Exception Throws missing param message.
	 */
	public function is_available() {
		$country = $this->get_country_from_customer_or_order_on_admin();

		if ( ! parent::is_available() ) {
			return false;
		}

		if ( ! $this->ebanx_gateway->isAvailableForCountry( $country ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The HTML structure on checkout page
	 */
	public function payment_fields() {
		$message = $this->get_sandbox_form_message( $this->get_transaction_address( 'country' ) );
		wc_get_template(
			'sandbox-checkout-alert.php',
			array(
				'is_sandbox_mode' => $this->is_sandbox_mode,
				'message'         => $message,
			),
			'woocommerce/ebanx/',
			WC_EBANX::get_templates_path()
		);

		$description = $this->get_description();
		if ( isset( $description ) ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		wc_get_template(
			'banking-ticket/checkout-instructions.php',
			array(
				'id'    => $this->id,
				'names' => $this->names,
			),
			'woocommerce/ebanx/',
			WC_EBANX::get_templates_path()
		);
	}

	/**
	 * Mount the data to send to EBANX API
	 *
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function request_data( $order ) {
		$data                                 = parent::request_data( $order );
		$data['payment']['payment_type_code'] = $this->api_name;

		return $data;
	}

	/**
	 * Save order's meta fields for future use
	 *
	 * @param  WC_Order $order The order created.
	 * @param  Object   $request The request from EBANX success response.
	 * @return void
	 */
	protected function save_order_meta_fields( $order, $request ) {
		parent::save_order_meta_fields( $order, $request );

		update_post_meta( $order->get_id(), '_payment_due_date', $request->payment->due_date );
		update_post_meta( $order->get_id(), '_boleto_url', $request->payment->boleto_url );
		update_post_meta( $order->get_id(), '_boleto_barcode', $request->payment->boleto_barcode );
	}

	/**
	 * Algh to create the barcode of Boleto
	 *
	 * @param  string $code The boleto's code.
	 * @return array|string
	 */
	public static function barcode_anti_fraud( $code ) {
		if ( strlen( $code ) !== 47 ) {
			return '';
		}

		return array(
			'boleto1' => substr( $code, 0, 5 ),
			'boleto2' => substr( $code, 5, 5 ),
			'boleto3' => substr( $code, 10, 5 ),
			'boleto4' => substr( $code, 15, 6 ),
			'boleto5' => substr( $code, 21, 5 ),
			'boleto6' => substr( $code, 26, 6 ),
			'boleto7' => substr( $code, 32, 1 ),
			'boleto8' => substr( $code, 33, 14 ),
		);
	}

	/**
	 * The page of order received, we call them as "Thank you pages"
	 *
	 * @param  WC_Order $order The order created.
	 * @return void
	 */
	public static function thankyou_page( $order ) {
		$boleto_url         = get_post_meta( $order->get_id(), '_boleto_url', true );
		$boleto_basic       = $boleto_url . '&format=basic';
		$boleto_pdf         = $boleto_url . '&format=pdf';
		$boleto_print       = $boleto_url . '&format=print';
		$boleto_mobile      = $boleto_url . '&device_target=mobile';
		$barcode            = get_post_meta( $order->get_id(), '_boleto_barcode', true );
		$customer_email     = get_post_meta( $order->get_id(), '_billing_email', true );
		$customer_name      = get_post_meta( $order->get_id(), '_billing_first_name', true );
		$boleto_due_date    = get_post_meta( $order->get_id(), '_payment_due_date', true );
		$boleto_hash        = get_post_meta( $order->get_id(), '_ebanx_payment_hash', true );
		$barcode_anti_fraud = self::barcode_anti_fraud( $barcode );

		$data = array(
			'data'         => array(
				'boleto_hash'    => $boleto_hash,
				'barcode'        => $barcode,
				'barcode_fraud'  => $barcode_anti_fraud,
				'url_basic'      => $boleto_basic,
				'url_pdf'        => $boleto_pdf,
				'url_print'      => $boleto_print,
				'url_mobile'     => $boleto_mobile,
				'url_iframe'     => get_site_url() . '/?ebanx=order-received&hash=' . $boleto_hash,
				'customer_email' => $customer_email,
				'customer_name'  => $customer_name,
				'due_date'       => $boleto_due_date,
			),
			'order_status' => $order->get_status(),
			'method'       => 'banking-ticket',
		);

		parent::thankyou_page( $data );

		wp_enqueue_script(
			'woocommerce_ebanx_email_instructions_fingerprint2',
			'https://print.ebanx.com.br/assets/sources/fingerprint/fingerprint2.min.js',
			array(),
			WC_EBANX::get_plugin_version(),
			true
		);
		wp_enqueue_script(
			'woocommerce_ebanx_email_instructions_browserdetect',
			'https://print.ebanx.com.br/assets/sources/fingerprint/browserdetect.js',
			array(),
			WC_EBANX::get_plugin_version(),
			true
		);
		wp_enqueue_script(
			'woocommerce_ebanx_email_instructions_mystiquefingerprint',
			'https://print.ebanx.com.br/assets/sources/fingerprint/mystiquefingerprint.js',
			array(),
			WC_EBANX::get_plugin_version(),
			true
		);
		wp_add_inline_script(
			'mystique',
			'!function(){let t={justPrint:!1,paymentHash:boleto_hash};Mystique.registerFingerprint(null,t,boleto_type)}();'
		);
		wp_enqueue_script(
			'woocommerce_ebanx_order_received',
			plugins_url( 'assets/js/order-received.js', WC_EBANX::DIR ),
			array( 'jquery' ),
			WC_EBANX::get_plugin_version(),
			true
		);
	}
}
