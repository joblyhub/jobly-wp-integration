/* Copy-to-clipboard for shortcode buttons. */
document.addEventListener( 'click', function ( e ) {
	var el = e.target.closest( '[data-jobly-copy]' );
	if ( ! el || ! navigator.clipboard ) {
		return;
	}
	e.preventDefault();
	var label = el.textContent;
	navigator.clipboard.writeText( el.getAttribute( 'data-jobly-copy' ) ).then( function () {
		el.textContent = '✓';
		setTimeout( function () {
			el.textContent = label;
		}, 1200 );
	} );
} );
