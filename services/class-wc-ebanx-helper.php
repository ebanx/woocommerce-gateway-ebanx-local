<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WC_EBANX_GATEWAYS_DIR . 'class-wc-ebanx-global-gateway.php';
require_once WC_EBANX_SERVICES_DIR . 'class-wc-ebanx-constants.php';
require_once WC_EBANX_VENDOR_DIR . 'autoload.php';

use RuntimeException as RuntimeException;
use Ebanx\Benjamin\Services\Gateways\CreditCard;
use Ebanx\Benjamin\Models\Configs\Config;
use Ebanx\Benjamin\Models\Configs\CreditCardConfig;

/**
 * Class WC_EBANX_Helper
 */
abstract class WC_EBANX_Helper {

	/**
	 * Flatten an array
	 *
	 * @param  array $array The array to flatten.
	 * @return array        The new array flatted
	 */
	public static function flatten( array $array ) {
		$return = array();
		array_walk_recursive(
			$array,
			function( $value ) use ( &$return ) {
				$return[] = $value;
			}
		);

		return $return;
	}

	/**
	 * Return config integration key by environment.
	 *
	 * @return string
	 */
	public static function get_integration_key() {
		$config = new WC_EBANX_Global_Gateway();

		return 'yes' === $config->settings['sandbox_mode_enabled'] ? $config->settings['sandbox_private_key'] : $config->settings['live_private_key'];
	}

	/**
	 * Splits address in street name, house number and addition
	 *
	 * @param  string $address  Address to be split.
	 * @throws RuntimeException When it is impossible to split the address by regex matching.
	 *
	 * @return array
	 */
	public static function split_street( $address ) {
		$result = preg_match( '/^([^,\-\/\#0-9]*)\s*[,\-\/\#]?\s*([0-9]+)\s*[,\-\/]?\s*([^,\-\/]*)(\s*[,\-\/]?\s*)([^,\-\/]*)$/', $address, $matches );

		if ( false === $result ) {
			throw new RuntimeException( sprintf( 'Problems trying to parse address: \'%s\'', $address ) );
		}

		if ( 0 === $result ) {
			return array(
				'street'        => $address,
				'number'       => '',
				'complement' => '',
			);
		}

		$street     = $matches[1];
		$number     = $matches[2];
		$complement = $matches[3] . $matches[4] . $matches[5];

		if ( empty( $street ) ) {
			$street     = $matches[3];
			$complement = $matches[5];
		}

		return array(
			'street'     => $street,
			'number'     => $number,
			'complement' => $complement,
		);
	}

	/**
	 * Get post id from meta key and value
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return int|bool
	 */
	public static function get_post_id_by_meta_key_and_value( $key, $value ) {
		global $wpdb;

		$query = sprintf( 'SELECT * FROM %s WHERE meta_key="%s" AND meta_value="%s"', $wpdb->postmeta, esc_sql( $key ),  esc_sql( $value ) );
		$meta  = $wpdb->get_results( $query );
		if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta[0] ) ) {
			$meta = $meta[0];
		}
		if ( is_object( $meta ) ) {
			return $meta->post_id;
		} else {
			return false;
		}
	}

	/**
	 *
	 * @param array $array
	 *
	 * @return object
	 */
	public static function array_to_object( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = static::array_to_object( $value );
			}
		}
		return (object) $array;
	}

	/**
	 *
	 * Verifies if user cart has any subscription product
	 *
	 * @return bool
	 */
	public static function checkout_contains_subscription() {
		global $product;
		if ( class_exists( 'WC_Subscription' ) && ! empty( $product ) ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || strpos( get_class( $product ), 'Subscription' ) !== false;
		}

		return false;
	}

	private static function is_change_payment_method_page_subscription() {
		if ( empty( $_GET['change_payment_method'] ) ) {
			return false;
		}

		if ( ! is_numeric( $_GET['change_payment_method'] ) ) {
			return false;
		}

		if ( wcs_is_subscription( $_GET['change_payment_method'] ) ) {
			return true;
		}

		return wcs_order_contains_subscription( $_GET['change_payment_method'], 'any' );
	}

	/**
	 * Verifies if enable instalment on the cart with subscription in checkout
	 *a
	 * @return bool
	 */
	public static function disable_instalments_on_checkout() {
		if ( ! class_exists( 'WC_Subscription' ) ) {
			return false;
		}

		if ( self::is_change_payment_method_page_subscription() ) {
			return true;
		}

		$disable_instalments_when_subscription = WC_Subscriptions_Cart::cart_contains_subscription();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$subscription_period = $product->get_meta( '_subscription_period' );
			if ( strpos( get_class( $product ), 'Subscription' ) !== false && 'year' === $subscription_period ) {
				$disable_instalments_when_subscription = false;
				break;
			}
		}

		return $disable_instalments_when_subscription;
	}

	/**
	 *
	 * @param string $country_abbr
	 *
	 * @return mixed
	 */
	public static function get_instalments_by_country( $country_abbr ) {
		$config             = new Config();
		$credit_card_config = new CreditCardConfig();
		$credit_card        = new CreditCard( $config, $credit_card_config );
		return $credit_card->getInstalmentsByCountry( WC_EBANX_Constants::COUNTRY_NAME_FROM_ABBREVIATION[ $country_abbr ] );
	}
}
