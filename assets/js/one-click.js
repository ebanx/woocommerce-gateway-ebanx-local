jQuery( document ).ready(function ( $ ) {
	let buttonContainer = $( '.ebanx-one-click-button-container' );
	let button          = $( '#ebanx-one-click-button' );
	let hidden          = $( '#ebanx-one-click' );
	let close           = $( '.ebanx-one-click-close-button' );
	let tooltip         = $( '.ebanx-one-click-tooltip' );
	let cvv             = $( '#ebanx-one-click-cvv-input' );
	let payButton       = $( '.ebanx-one-click-pay' );
	let instalments     = $( '.ebanx-instalments' );
	let form            = $( '#ebanx-one-click-form' );
	let isProcessing    = false;

	let addError = function ( el ) {
		$( el ).addClass( 'is-invalid' );
	};

	let removeError = function ( el ) {
		$( el ).removeClass( 'is-invalid' );
	};

	tooltip.keypress(function ( e ) {
		let key = e.key || e.keyCode;
		if (key === 'Enter' || key === 13) {
			e.preventDefault();
			form.submit();
		}
		return true;
	});

	button.on( 'click', function ( e ) {
		e.preventDefault();
		tooltip.toggleClass( 'is-active' );
	} );

	close.on( 'click', function ( e ) {
		e.preventDefault();
		tooltip.removeClass( 'is-active' );
	});

	form.on( 'submit', function () {
		payButton.text( payButton.attr( 'data-processing-label' ) ).attr( 'disabled', 'disabled' );
		return true;
	} );

	cvv.on( 'keyup', function () {
		let value = cvv.val();

		if ( ! (value.length >= 3 && value.length <= 4)) {
			addError( cvv );
		} else {
			removeError( cvv );
		}
	});

	// Align the tooltip.
	if (buttonContainer.css( 'text-align' ) === 'center') {
		tooltip.css({
			left: '50%',
			marginLeft: -Math.abs( tooltip.outerWidth() / 2 )
		});
	} else if (buttonContainer.css( 'text-align' ) === 'right') {
		tooltip.css({
			left: 'auto',
			right: 0
		});
	}
});
