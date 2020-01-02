;(function ($) {
	// Checkout manager managed fields.
	const modesField                = $( '#woocommerce_ebanx-global_brazil_taxes_options' );
	const fields                    = $( '.ebanx-checkout-manager-field' );
	const fieldsToggler             = $( '#woocommerce_ebanx-global_checkout_manager_enabled' );
	const ebanxAdvancedOptionEnable = $( '.ebanx-advanced-option-enable' );
	const optionsToggler            = $( '#woocommerce_ebanx-global_advanced_options_title' );

	const countryPayments = {
		brazil: $( '#woocommerce_ebanx-global_brazil_payment_methods' )
	};

	const disableFields = function (jqElementList) {
		jqElementList.closest( 'tr' ).hide();
	};

	const enableFields = function (jqElementList) {
		jqElementList.closest( 'tr' ).show();
	};

	const updateFields = function () {
		let modes     = modesField.val();
		let brazilVal = countryPayments.brazil.val();

		disableFields( fields );

		if (fieldsToggler.length === 1 && fieldsToggler[0].checked) {

			enableFields( fields.filter( '.always-visible' ) );
			if (brazilVal != null && brazilVal.length > 0 && modes != null) {
				for (var i in modes) {
					enableFields( fields.filter( '.' + modes[i] ) );
				}

				if (modes.length === 2) {
					enableFields( fields.filter( '.cpf_cnpj' ) );
				}
			}

			if (brazilVal == null) {
				optionsToggler.hide();
				disableFields( ebanxAdvancedOptionEnable );
			} else {
				optionsToggler.css( 'display', 'table' );
				enableFields( ebanxAdvancedOptionEnable );
			}
		}
	};

	fieldsToggler.click(function () {
		updateFields();
	});

	modesField.change(function () {
		updateFields();
	});

	for (var i in countryPayments) {
		countryPayments[i].change(function () {
			updateFields();
		});
	}

	const toggleElements = function () {
		let wasClosed = optionsToggler.hasClass( 'closed' );
		optionsToggler.toggleClass( 'closed' );
		$( '.ebanx-advanced-option' )
			.add( $( '.ebanx-advanced-option' ).closest( '.form-table' ) )
			.slideToggle( 'fast' );

		// Extra call to update checkout manager stuff on open.
		if (wasClosed) {
			updateFields();
		}

		localStorage.setItem( 'ebanx_advanced_options_toggle', wasClosed ? 'open' : 'closed' );
	};

	optionsToggler
		.addClass( 'togglable' )
		.click( toggleElements );

	if (localStorage.getItem( 'ebanx_advanced_options_toggle' ) != 'open' ) {
		toggleElements();
	} else {
		// Extra call to update checkout manager stuff if it's already open.
		updateFields();
	}
})( jQuery );
