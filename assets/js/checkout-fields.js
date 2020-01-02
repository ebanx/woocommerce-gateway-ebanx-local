jQuery(function ( $ ) {
	EBANX.errors.summary.pt_BR['BP-DR-57']  = 'A data do cartão de crédito deve estar no formato MM/AA';
	EBANX.errors.summary.es['BP-DR-57']     = 'Por favor, escribe la fecha en el formato MM/AA';
	EBANX.errors.summary.pt_BR['BP-DR-101'] = 'Ops! Esse cartão não está liberado para fazer compras na internet. Entre em contato com o seu banco para mais informações.';
	EBANX.errors.summary.es['BP-DR-101']    = '¡Lo sentimos!, vuelva a intentarlo con otra tarjeta.';
	// Custom select fields.
	if ( 'select2' in $.fn) {
		$( 'select.ebanx-select-field' ).select2();
		$( '.ebanx-select-field > select' ).select2();
	}

	$( document ).find( '.ebanx_billing_brazil_document input' ).mask( '000.000.000-00' );
	$( document ).find( '.ebanx_billing_brazil_cnpj input' ).mask( '00.000.000/0000-00' );

	$( document ).find( 'input[name*="brazil_document"]' ).mask( '000.000.000-00' );
	$( document ).find( 'input[name*="brazil_cnpj"]' ).mask( '00.000.000/0000-00' );

	const getBillingFields = function (filter) {
		filter = filter || '';

		switch (filter) {
			case '':
				break;
			case 'br':
				filter = 'brazil_';
				break;
			default:
				// Filter is some other country, let's give it an empty set.
				return $( [] );
		}

		return $( '.woocommerce-checkout' ).find( 'p' ).filter(function ( index ) {
			return this.className.match( new RegExp( '.*ebanx_billing_' + filter + '.*$', 'i' ) );
		});
	};

	const isEbanxMethodSelected = function () {
		let selectedMethod = $( 'input[name=payment_method]:checked' ).val();
		return ( typeof selectedMethod !== 'undefined' && selectedMethod.indexOf( 'ebanx' ) !== -1 );
	};

	const disableFields = function ( billingFields ) {
		billingFields.each(function () {
			$( this ).hide().removeAttr( 'required' );
		});
	};

	const enableFields = function ( billingFields ) {
		billingFields.each(function () {
			$( this ).show().attr( 'required', true );
		});
	};

	// Select to choose individuals or companies.
	const taxes = $( '.ebanx_billing_brazil_selector' ).find( 'select' );

	taxes
		.on( 'change', function () {
			disableFields( $( '.ebanx_billing_brazil_selector_option' ) );
			enableFields( $( '.ebanx_billing_brazil_' + this.value ) );
		});

	$( '#billing_country' )
		.on( 'change', function () {
			let country = this.value.toLowerCase();

			disableFields( getBillingFields() );

			if (country && isEbanxMethodSelected()) {
				enableFields( getBillingFields( country ) );
			}

			if (country === 'br' ) {
				taxes.change();
			}
		}).change();

	$( 'body' ).on( 'updated_checkout', function () {
		let paymentMethods = $( '.wc_payment_methods.payment_methods.methods > li > input' );

		if (wc_ebanx_checkout_params.is_sandbox) {
			let messages           = wc_ebanx_checkout_params.sandbox_tag_messages;
			let localizedMessage   = messages['pt-br'];
			let methodsLabels      = $( '.wc_payment_methods.payment_methods.methods > li > label' );
			let ebanxMethodsLabels = methodsLabels.filter(function (index, elm) {
				return /ebanx/.test( $( elm ).attr( 'for' ) );
			});
			$( ebanxMethodsLabels ).find( 'img' ).before( '<span id="sandbox-alert-tag">' + localizedMessage + '</span>' );
		}

		paymentMethods.on( 'change', function ( e ) {
			disableFields( getBillingFields() );
			if (isEbanxMethodSelected() && $( '#billing_country' ).length !== 0) {
				enableFields( getBillingFields( $( '#billing_country' ).val().toLowerCase() ) );
			}
			if ($( '#billing_country' ).val() === 'BR' ) {
				taxes.change();
			}
		});
	});

	if ($( 'select[name="ebanx-banking-ticket[ebanx_billing_brazil_person_type]"]' ).length > 0) {
		$( 'select[name="ebanx-banking-ticket[ebanx_billing_brazil_person_type]"]' ).on( 'change', function (e) {
			if ($( this ).val() == 'cpf' ) {
				$( 'div.cpf-row' ).show();
				$( 'div.cnpj-row' ).hide();
			} else {
				$( 'div.cnpj-row' ).show();
				$( 'div.cpf-row' ).hide();
			}
		});
	}

	if ($( 'select[name="ebanx-credit-card-br[ebanx_billing_brazil_person_type]"]' ).length > 0) {
		$( 'select[name="ebanx-credit-card-br[ebanx_billing_brazil_person_type]"]' ).on( 'change', function (e) {
			if ($( this ).val() != 'cpf' ) {
				$( 'div.cnpj-row' ).show();
				$( 'div.cpf-row' ).hide();
			} else {
				$( 'div.cpf-row' ).show();
				$( 'div.cnpj-row' ).hide();
			}
		});
	}

	if ($( '.ebanx-person-type-field' ).length > 0) {
		$( '.ebanx-person-type-field' ).trigger( 'change' );
	}
});
