<?php

use Ebanx\Benjamin\Models\Address;
use Ebanx\Benjamin\Models\Card;
use Ebanx\Benjamin\Models\Country;
use Ebanx\Benjamin\Models\Currency;
use Ebanx\Benjamin\Models\Item;
use Ebanx\Benjamin\Models\Payment;
use Ebanx\Benjamin\Models\Person;

/**
 * Class WC_EBANX_Payment_Adapter
 */
class WC_EBANX_Payment_Adapter {
	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param string                  $gateway_id
	 *
	 * @return Payment
	 * @throws Exception Throws parameter missing exception.
	 */
	public static function transform( $order, $configs, $names, $gateway_id ) {
		$payment_data = array(
			'amountTotal'         => $order->get_total(),
			'orderNumber'         => $order->get_id(),
			'address'             => static::transform_address( $order, $configs, $gateway_id ),
			'items'               => static::transform_items( $order ),
			'merchantPaymentCode' => substr( $order->get_id() . '-' . md5( wp_rand( 123123, 9999999 ) ), 0, 40 ),
			'riskProfileId'       => 'Wx' . str_replace( '.', 'x', WC_EBANX::get_plugin_version() ),
		);

		$person = static::transform_person( $order, $configs, $names, $gateway_id );

		$payment_data['person'] = $person;

		if ( Person::TYPE_BUSINESS === $person->type ) {
			$payment_data['responsible'] = $person;
		}

		if ( 'ebanx-banking-ticket' === $gateway_id ) {
			$payment_data['dueDate'] = static::transform_due_date( $configs );
		}

		return new Payment( $payment_data );
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param string                  $gateway_id
	 *
	 * @return Payment
	 * @throws Exception Throws parameter missing exception.
	 */
	public static function transform_card( $order, $configs, $names, $gateway_id ) {
		$payment = self::transform( $order, $configs, $names, $gateway_id );
		$country = trim( strtolower( WC()->customer->get_billing_country() ) );

		if ( in_array( $country, WC_EBANX_Constants::$credit_card_countries, true ) ) {
			$payment->instalments = '1';

			if ( $configs->settings[ "{$country}_credit_card_instalments" ] > 1 && WC_EBANX_Request::has( 'ebanx_billing_instalments' ) ) {
				$payment->instalments = WC_EBANX_Request::read( 'ebanx_billing_instalments' );
			}
		}

		if ( ! empty( WC_EBANX_Request::read( 'ebanx_device_fingerprint', null ) ) ) {
			$payment->device_id = WC_EBANX_Request::read( 'ebanx_device_fingerprint' );
		}

		$token = WC_EBANX_Request::has( 'ebanx_debit_token' )
			? WC_EBANX_Request::read( 'ebanx_debit_token' )
			: WC_EBANX_Request::read( 'ebanx_token' );

		$brand = WC_EBANX_Request::read( 'ebanx_brand' , '' );

		$payment->card = new Card(
			array(
				'autoCapture' => ( 'yes' === $configs->settings['capture_enabled'] ),
				'token'       => $token,
				'cvv'         => WC_EBANX_Request::read( 'ebanx_billing_cvv' ),
				'type'        => $brand,
			)
		);
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$payment->manualReview = 'yes' === $configs->settings['manual_review_enabled'];

		return $payment;
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @return Address
	 * @throws WC_EBANX_Payment_Exception Throws parameter missing exception.
	 */
	private static function transform_address_from_post_data( $order ) {
		$order_meta_data = self::get_order_meta_data( $order );

		$order_address = array(
			'postcode'  => ! empty( $order_meta_data['_billing_postcode'] ) ? $order_meta_data['_billing_postcode'] : '',
			'address_1' => ! empty( $order_meta_data['_billing_address_1'] ) ? $order_meta_data['_billing_address_1'] : '',
			'number'    => ! empty( $order_meta_data['_billing_number'] ) ? $order_meta_data['_billing_number'] : '',
			'address_2' => ! empty( $order_meta_data['_billing_address_2'] ) ? $order_meta_data['_billing_address_2'] : '',
			'state'     => ! empty( $order_meta_data['_billing_state'] ) ? $order_meta_data['_billing_state'] : '',
			'city'      => ! empty( $order_meta_data['_billing_city'] ) ? $order_meta_data['_billing_city'] : '',
			'country'   => self::get_country_from_iso( $order->get_billing_country() ),
		);

		if (
			empty( $order_address['postcode'] )
			|| empty( $order_address['address_1'] )
			|| empty( $order_address['state'] )
			|| empty( $order_address['country'] )
		) {
			throw new WC_EBANX_Payment_Exception(
				__( 'Missing billing address required fields: postal code, address, state, or country.', 'woocommerce-gateway-ebanx' ),
				500
			);
		}

		$addresses = $order_address['address_1'];

		if ( ! empty( $order_address['address_2'] ) ) {
			$addresses .= ' - ' . $order_address['address_2'];
		}

		$number = ! empty( $order_address['number'] ) ? trim( $order_address['number'] ) : 'S/N';

		return new Address(
			array(
				'address'      => $addresses,
				'streetNumber' => $number,
				'city'         => $order_address['city'],
				'country'      => $order_address['country'],
				'state'        => $order_address['state'],
				'zipcode'      => $order_address['postcode'],
			)
		);
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 *
	 * @throws WC_EBANX_Payment_Exception If an error occurred on transform data
	 * @return Payment
	 */
	public static function transform_subscription_payment( $order, $configs ) {
		return new Payment(
			array(
				'amountTotal'         => $order->get_total(),
				'orderNumber'         => $order->get_id(),
				'address'             => static::transform_address_from_post_data( $order ),
				'person'              => static::transform_person_from_post_data( $order, $configs ),
				'responsible'         => static::transform_person_from_post_data( $order, $configs ),
				'instalments'         => '1',
				'items'               => static::transform_items( $order ),
				'merchantPaymentCode' => substr( $order->get_id() . '-' . md5( rand( 123123, 9999999 ) ), 0, 40 ),
				'riskProfileId'       => 'Wx' . str_replace( '.', 'x', WC_EBANX::get_plugin_version() ),
			)
		);
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	private static function get_order_meta_data( $order ) {
		$data        = get_post_meta( $order->get_id() );
		$data_values = array();

		foreach ( $data as $key => $value ) {
			$data_values[ $key ] = reset( $value );
		}

		return $data_values;
	}

	/**
	 * @param string $iso_country
	 * @param bool   $should_use_default_country
	 *
	 * @return string|null
	 */
	private static function get_country_from_iso( $iso_country, $should_use_default_country = false ) {
		$country = isset( WC()->countries->countries[ $iso_country ] ) ? WC()->countries->countries[ $iso_country ] : null;

		if ( ! empty( $country ) ) {
			return $country;
		}

		return $should_use_default_country ? WC()->countries->countries[ WC_EBANX_Constants::DEFAULT_COUNTRY ] : null;
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param string                  $user_cc_token
	 * @param string                  $user_cc_brand
	 *
	 * @throws WC_EBANX_Payment_Exception If an error occurred on transform data
	 * @return Payment
	 */
	public static function transform_card_subscription_payment( $order, $configs, $user_cc_token, $user_cc_brand = null ) {

		$payment = self::transform_subscription_payment( $order, $configs );

		$payment->card = new Card(
			array(
				'token' => $user_cc_token,
			)
		);
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$payment->manualReview = 'yes' === $configs->settings['manual_review_enabled'];

		if ( ! empty( $user_cc_brand ) ) {
			$payment->card->type = $user_cc_brand;
		}

		return $payment;
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 *
	 * @return string
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function get_person_type_from_order( $order, $configs ) {
		$fields_options = array();
		if ( isset( $configs->settings['brazil_taxes_options'] ) && is_array( $configs->settings['brazil_taxes_options'] ) ) {
			$fields_options = $configs->settings['brazil_taxes_options'];
		}

		if ( count( $fields_options ) === 1 && 'cnpj' === $fields_options[0] ) {
			return Person::TYPE_BUSINESS;
		}
		$brazil_person_ype = get_user_meta( $order->get_user_id(), '_ebanx_billing_brazil_person_type', true );
		if ( empty( $brazil_person_ype ) ) {
			return Person::TYPE_PERSONAL;
		}

		if ( 'cnpj' === $brazil_person_ype || 2 == $brazil_person_ype || 'pessoa jurídica' === strtolower( $brazil_person_ype ) ) {
			return Person::TYPE_BUSINESS;
		}

		return Person::TYPE_PERSONAL;
	}

	/**
	 *
	 * @param WC_Order $order
	 * @param string   $person_type
	 *
	 * @return string
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function get_document_from_order( $order ) {
		$document  = get_user_meta( $order->get_user_id(), '_ebanx_document', true );

		if ( ! empty( $document ) ) {
			return $document;
		}

		throw new Exception( 'INVALID-DOCUMENT' );
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 *
	 * @return Person
	 */
	private static function transform_person_from_post_data( $order, $configs ) {
		$person_type = self::get_person_type_from_order( $order, $configs );
		$document    = self::get_document_from_order( $order );

		return new Person(
			array(
				'type'        => $person_type,
				'document'    => $document,
				'email'       => $order->get_billing_email(),
				'ip'          => get_post_meta( $order->get_id(),  '_customer_ip_address', true ),
				'name'        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'phoneNumber' => $order->get_billing_phone(),
			)
		);
	}

	/**
	 *
	 * @param WC_EBANX_Global_Gateway $configs
	 *
	 * @return DateTime|string
	 */
	private static function transform_due_date( $configs ) {
		$due_date = '';
		if ( ! empty( $configs->settings['due_date_days'] ) ) {
			$due_date = new DateTime();
			$due_date->modify( "+{$configs->settings['due_date_days']} day" );
		}

		return $due_date;
	}

	/**
	 *
	 * @param WC_Order $order
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param string                  $gateway_id
	 *
	 * @return Address
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function transform_address( $order, $configs, $gateway_id ) {
		if (
			( empty( WC_EBANX_Request::read( 'billing_postcode', null ) )
				&& empty( WC_EBANX_Request::read( $gateway_id, null )['billing_postcode'] ) )
			|| ( empty( WC_EBANX_Request::read( 'billing_address_1', null ) )
				&& empty( WC_EBANX_Request::read( $gateway_id, null )['billing_address_1'] ) )
			|| ( empty( WC_EBANX_Request::read( 'billing_state', null ) )
				&& empty( WC_EBANX_Request::read( $gateway_id, null )['billing_state'] ) )
		) {
			throw new Exception( 'INVALID-ADDRESS-FIELDS' );
		}

		$addresses = WC_EBANX_Request::read( 'billing_address_1', $gateway_id );

		if ( ! empty( WC_EBANX_Request::read( 'billing_address_2', null ) ) ) {
			$addresses .= ' - ' . WC_EBANX_Request::read( 'billing_address_2', null );
		}

		$split_address   = WC_EBANX_Helper::split_street( $addresses );
		$street_number   = empty( $addresses['houseNumber'] ) ? 'S/N' : trim( $addresses['houseNumber'] . ' ' . $addresses['additionToAddress'] );

		return new Address(
			array(
				'address'          => $split_address['streetName'],
				'streetNumber'     => $street_number,
				'streetComplement' => $split_address['additionToAddress'],
				'city'             => WC_EBANX_Request::read_customizable_field( 'billing_city', $gateway_id ),
				'country'          => self::get_country_to_address( $order, $configs ),
				'state'            => WC_EBANX_Request::read_customizable_field( 'billing_state', $gateway_id ),
				'zipcode'          => WC_EBANX_Request::read_customizable_field( 'billing_postcode', $gateway_id ),
			)
		);
	}

	/**
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 *
	 * @return string
	 */
	public static function get_country_to_address( $order, $configs ) {
		$address_country  = $order->get_billing_country();
		$currency_country = Currency::currencyToCountry( $configs->currency_code );
		$iso_country      = Country::handleCountryToIso( $currency_country );

		if ( 'yes' === $configs->get_setting_or_default( 'enable_international_credit_card' , 'no')
			&& $address_country !== $iso_country
		) {
			return $currency_country;
		}

		return empty( $address_country ) ? WC_EBANX_Constants::DEFAULT_COUNTRY : $address_country;
	}

	/**
	 *
	 * @param WC_Order                $order
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param string                  $gateway_id
	 *
	 * @return Person
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function transform_person( $order, $configs, $names, $gateway_id ) {
		$document = static::get_document( $configs, $names, $gateway_id );

		$person_data = array(
			'type'        => static::get_person_type( $configs, $names, $gateway_id ),
			'document'    => $document,
			'email'       => $order->get_billing_email(),
			'ip'          => WC_Geolocation::get_ip_address(),
			'name'        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'phoneNumber' => '' !== $order->get_billing_phone() ? $order->get_billing_phone() : WC_EBANX_Request::read_customizable_field('billing_phone', $gateway_id, null ),
		);

		if ( 'yes' === $configs->get_setting_or_default( 'enable_international_credit_card' , 'no')
			&& 'ebanx-credit-card-international' === $gateway_id
		) {
			$person_data['documentCountry'] = trim( strtolower( WC()->customer->get_billing_country() ) );
		}

		return new Person( $person_data );
	}

	/**
	 *
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param string                  $gateway_id
	 *
	 * @return string
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function get_document( $configs, $names, $gateway_id ) {
		$country = trim( strtolower( WC()->customer->get_billing_country() ) );

		if ( WC_EBANX_Constants::COUNTRY_BRAZIL === $country ) {
			return static::get_brazilian_document( $configs, $names, $gateway_id );
		}

		if ( 'yes' === $configs->get_setting_or_default( 'enable_international_credit_card' , 'no')
			&& 'ebanx-credit-card-international' === $gateway_id
		) {
			return WC_EBANX_Request::read( 'ebanx_billing_foreign_document', '' );
		}

		return '';
	}

	/**
	 *
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param string                  $gateway_id
	 *
	 * @return string
	 * @throws Exception Throws parameter missing exception.
	 */
	private static function get_brazilian_document( $configs, $names, $gateway_id ) {
		$cpf  = WC_EBANX_Request::read_customizable_field( $names['ebanx_billing_brazil_document'], $gateway_id );
		$cnpj = WC_EBANX_Request::read_customizable_field( $names['ebanx_billing_brazil_cnpj'], $gateway_id );

		$person_type = static::get_person_type( $configs, $names, $gateway_id );

		$has_cpf  = ! empty( $cpf );
		$has_cnpj = ! empty( $cnpj );

		if (
			( Person::TYPE_BUSINESS === $person_type && ( ! $has_cnpj || empty( WC_EBANX_Request::read_customizable_field( 'billing_company', $gateway_id ) ) ) )
			|| ( Person::TYPE_PERSONAL === $person_type && ! $has_cpf )
		) {
			throw new Exception( 'INVALID-DOCUMENT' );
		}

		if ( Person::TYPE_BUSINESS === $person_type ) {
			return $cnpj;
		}

		return $cpf;
	}

	/**
	 * @param WC_EBANX_Global_Gateway $configs
	 * @param array                   $names
	 * @param array                   $gateway_id
	 *
	 * @return string
	 * @throws Exception Throws parameter missing exception.
	 */
	public static function get_person_type( $configs, $names, $gateway_id ) {
		$fields_options = array();

		if ( isset( $configs->settings['brazil_taxes_options'] ) && is_array( $configs->settings['brazil_taxes_options'] ) ) {
			$fields_options = $configs->settings['brazil_taxes_options'];
		}

		if ( count( $fields_options ) === 1 && 'cnpj' === $fields_options[0] ) {
			return Person::TYPE_BUSINESS;
		}

		$brazil_person_type = WC_EBANX_Request::read_customizable_field( $names['ebanx_billing_brazil_person_type'], $gateway_id );

		if ( 'cnpj' === $brazil_person_type || '2' == $brazil_person_type || 'pessoa jurídica' === strtolower( $brazil_person_type ) ) {
			return Person::TYPE_BUSINESS;
		}

		return Person::TYPE_PERSONAL;
	}

	/**
	 *
	 * @param $order WC_Order $order
	 *
	 * @return array
	 */
 	private static function transform_items( $order ) {
		return array_map(
			function( $product ) {
					return new Item(
						array(
							'name'      => $product['name'],
							'unitPrice' => $product['line_subtotal'],
							'quantity'  => $product['qty'],
							'type'      => $product['type'],
						)
					);
			},
			$order->get_items()
		);
	}
}
