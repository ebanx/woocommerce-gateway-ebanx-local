jQuery( document ).ready(function ( $ ) {
	let clipboard = new Clipboard( '.ebanx-button--copy' );

	clipboard.on( 'success', function ( e ) {
		let $target = $( e.trigger );

		$target
			.addClass( 'ebanx-button--copy-success' )
			.text( '✔︎ Copiado!' );

		setTimeout(function () {
			$target
				.removeClass( 'ebanx-button--copy-success' )
				.text( 'Copiar' );

		}, 2000 );
	});

	clipboard.on( 'error', function ( e ) {
		let $target = $( e.trigger );

		$target
			.addClass( 'ebanx-button--copy-error' )
			.text( 'Erro! :(' );

		setTimeout( function () {
			$target
				.addClass( 'ebanx-button--copy-error' )
				.text( 'Copiar ' );
		}, 2000 );
	});

	// iFrame Resizer.
	let iframe = $( '.woocommerce-order-received iframe' );

	if ( iframe ) {
		let resizeIframe = function resizeIframe() {
			iframe.height( iframe.contents().height() );
		};

		$( window ).on( 'load', function () {
			resizeIframe();
		} );

		iframe.contents().on( 'resize', function () {
			resizeIframe();
		} );
	}
});
