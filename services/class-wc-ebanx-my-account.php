<?php
/**
 * EBANX.com My Account actions
 *
 * @package WooCommerce_EBANX/Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EBANX_My_Account enables the thank you pages
 */
class WC_EBANX_My_Account {


	/**
	 * Constructor and initialize the filters and actions
	 */
	public function __construct() {
		// Actions.
		add_action( 'woocommerce_order_details_after_order_table_items', array( $this, 'order_details' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 100 );

		// Filters.
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_orders_banking_ticket_link' ), 10, 2 );
	}

	/**
	 * Load the assets needed by my account page
	 *
	 * @return void
	 */
	public function assets() {
		wp_enqueue_style(
			'woocommerce_my_account_style',
			plugins_url( 'assets/css/my-account.css', WC_EBANX::DIR ),
			array(),
			WC_EBANX::get_plugin_version()
		);
	}

	/**
	 * Add banking ticket link/button in My Orders section on My Accout page.
	 *
	 * @param array    $actions Actions.
	 * @param WC_Order $order   Order data.
	 *
	 * @return array
	 */
	public function my_orders_banking_ticket_link( $actions, $order ) {
		if ( 'ebanx-banking-ticket' === $order->get_payment_method() && in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
			$url = get_post_meta( $order->get_id(), 'Banking Ticket URL', true );

			if ( ! empty( $url ) ) {
				$actions[] = array(
					'url'  => $url,
					'name' => __( 'View Banking Ticket', 'woocommerce-gateway-ebanx' ),
				);
			}
		}

		return $actions;
	}

	/**
	 * Call thankyou pages on order details page on My Account by gateway method
	 *
	 * @param  WC_Order $order      The object order.
	 * @return void
	 */
	public static function order_details( $order ) {
		// For test purposes.
		$hash = get_post_meta( $order->get_id(), '_ebanx_payment_hash', true );

		printf( '<input type="hidden" name="ebanx_payment_hash" value="%s" />', $hash ); // phpcs:ignore WordPress.XSS.EscapeOutput

		switch ( $order->get_payment_method() ) {
			case 'ebanx-credit-card-br':
				WC_EBANX_Credit_Card_BR_Gateway::thankyou_page( $order );
				break;
			case 'ebanx-banking-ticket':
				WC_EBANX_Banking_Ticket_Gateway::thankyou_page( $order );
				break;
		}
	}
}

/**
 * Initialize the thank you pages
 */
new WC_EBANX_My_Account();
