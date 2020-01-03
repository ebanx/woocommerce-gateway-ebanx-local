jQuery( document ).ready(function ( $ ) {
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
