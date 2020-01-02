;(function ( $ ) {
	let woocommerceSettings = $( '.woocommerce_page_wc-settings' );

	if (woocommerceSettings.length < 1) {
		return;
	}

	let subsub = $( '.subsubsub > li' );

	for (var i = 0, t = subsub.length; i < t; ++i) {
		let s   = $( subsub[i] );
		let sub = $( s ).find( 'a' );

		if (sub.text().indexOf( 'EBANX -' ) !== -1) {
			continue;
		}

		s.css( {
			display: 'inline-block'
		} );
	}

	let last = subsub
		.filter( function () {
			return $( this ).css( 'display' ) === 'inline-block';
		} ).last();

	if (last.length < 1) {
		return;
	}

	last.html( last.html().replace( / \| ?/g, '' ) );

	$( '.ebanx-select' ).select2();
})( jQuery );
