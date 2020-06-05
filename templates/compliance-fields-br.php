<?php
	$order_id = get_query_var( 'order-pay' );

if ( $order_id ) {
	$wp_order             = wc_get_order( $order_id );
	$order_person_type = $wp_order ? get_post_meta( $wp_order->get_id(), '_billing_persontype', true ) : null;
	$cpf               = $wp_order ? get_post_meta( $wp_order->get_id(), '_billing_cpf', true ) : null;
	$cnpj              = $wp_order ? get_post_meta( $wp_order->get_id(), '_billing_cnpj', true ) : null;

	$person_type_filed_name = $names['ebanx_billing_brazil_person_type'] ? $names['ebanx_billing_brazil_person_type'] : 'ebanx_billing_brazil_person_type';
	$cpf_filed_name = $names['ebanx_billing_brazil_document'] ? $names['ebanx_billing_brazil_document'] : 'ebanx_billing_brazil_document';
	$cnpj_field_name = $names['ebanx_billing_brazil_cnpj'] ? $names['ebanx_billing_brazil_cnpj'] : 'ebanx_billing_brazil_cnpj';

	$address = $wp_order->get_address();
	$company = $wp_order->get_billing_company();

	$person_type = ( '1' == $order_person_type ||
		'cpf' === $order_person_type ||
		'pessoa física' === strtolower( $order_person_type )
	) ? 'cpf' : 'cnpj';

	$fields = array(
		$cnpj_field_name => array(
			'label'      => 'CNPJ',
			'value'      => $cnpj,
			'class_row'  => 'cnpj-row',
		),
		'billing_company'               => array(
			'label'     => __( 'Company name', 'woocommerce-gateway-ebanx' ),
			'value'     => $company,
			'class_row' => 'cnpj-row',
		),
		$cpf_filed_name => array(
			'label'     => 'CPF',
			'value'     => $cpf,
			'class_row' => 'cpf-row',
		),
		'billing_phone'                 => array(
			'label' => 'Telephone',
			'value' => $address['phone'],
		),
		'billing_postcode'              => array(
			'label' => 'Postcode / ZIP',
			'value' => $address['postcode'],
		),
		'billing_address_1'             => array(
			'label' => __( 'Street address', 'woocommerce-gateway-ebanx' ),
			'value' => $address['address_1'],
		),
		'billing_city'                  => array(
			'label' => __( 'Town / City', 'woocommerce-gateway-ebanx' ),
			'value' => $address['city'],
		),
		'billing_country'               => array(
			'value' => $address['country'],
			'type'  => 'hidden',
		),
	);

	$countries_obj = new WC_Countries();
	$states        = $countries_obj->get_states( 'BR' );
}
?>

<?php if ( $order_id ) : ?>
	<div class="ebanx-compliance-fields ebanx-compliance-fiels-br">
		<div class="ebanx-form-row ebanx-form-row-wide">
			<label for="<?php echo esc_attr( "{$id}[{$person_type_filed_name}]" ); ?>"><?php esc_html_e( 'Person type', 'woocommerce-gateway-ebanx' ); ?></label>
			<select name="<?php echo esc_attr( "{$id}[{$person_type_filed_name}]" ); ?>" id="<?php echo esc_attr( "{$id}[{$person_type_filed_name}]" ); ?>" class="ebanx-select-field ebanx-person-type-field">
				<option value="cpf" <?php echo 'cpf' === $person_type ? 'selected="selected"' : ''; ?>>CPF</option>
				<option value="cnpj"<?php echo 'cnpj' === $person_type ? 'selected="selected"' : ''; ?>>CNPJ</option>
			</select>
		</div>

		<?php foreach ( $fields as $name => $field ) : ?>
			<?php if ( isset( $field['type'] ) && 'hidden' === $field['type'] ) : ?>
				<input
					type="hidden"
					name="<?php echo esc_attr( "{$id}[{$name}]" ); ?>"
					value="<?php echo esc_attr( isset( $field['value'] ) ? $field['value'] : null ); ?>"
					class="input-text"
				/>
			<?php else : ?>
				<div class="ebanx-form-row ebanx-form-row-wide <?php echo esc_attr( isset( $field['class_row'] ) ? $field['class_row'] : '' ); ?>">
					<label for="<?php echo esc_attr( "{$id}[{$name}]" ); ?>"><?php echo esc_attr( $field['label'] ); ?></label>
					<input
						type="<?php echo esc_attr( isset( $field['type'] ) ? $field['type'] : 'text' ); ?>"
						name="<?php echo esc_attr( "{$id}[{$name}]" ); ?>"
						id="<?php echo esc_attr( "{$id}[{$name}]" ); ?>"
						value="<?php echo esc_attr( isset( $field['value'] ) ? $field['value'] : null ); ?>"
						class="input-text"
					/>
				</div>
			<?php endif ?>
		<?php endforeach ?>
		<div class="ebanx-form-row ebanx-form-row-wide">
			<label for="<?php echo esc_attr( "{$id}[billing_state]" ); ?>"><?php esc_html_e( 'State / County', 'woocommerce-gateway-ebanx' ); ?></label>
			<select name="<?php echo esc_attr( "{$id}[billing_state]" ); ?>" id="<?php echo esc_attr( "{$id}[billing_state]" ); ?>" class="ebanx-select-field">
				<option value="" selected><?php esc_html_e( 'Select...', 'woocommerce-gateway-ebanx' ); ?></option>
				<?php foreach ( $states as $abbr => $name ) : ?>
					<option value="<?php echo esc_attr( $abbr ); ?>" <?php echo strtolower( $abbr ) === strtolower( $address['state'] ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>
<?php endif ?>
