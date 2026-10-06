/* Jobly.si HRM admin: copy buttons with "Kopirano" feedback, live accent preview in the wizard. */
( function () {
	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		try {
			document.execCommand( 'copy' );
		} catch ( err ) {
			// Nothing else to try.
		}
		document.body.removeChild( ta );
		return Promise.resolve();
	}

	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '[data-jobly-copy]' );
		if ( ! el ) {
			return;
		}
		e.preventDefault();
		var text = el.getAttribute( 'data-jobly-copy' );
		var done = el.getAttribute( 'data-copied' ) || '✓';
		var target = el.querySelector( '.jobly-copybtn__label' ) || el;
		var label = target.textContent;
		var copy = navigator.clipboard ? navigator.clipboard.writeText( text ) : fallbackCopy( text );
		copy.catch( function () {
			return fallbackCopy( text );
		} ).then( function () {
			target.textContent = done;
			el.classList.add( 'is-copied' );
			setTimeout( function () {
				target.textContent = label;
				el.classList.remove( 'is-copied' );
			}, 1600 );
		} );
	} );

	var accent = document.querySelector( '[data-jobly-accent]' );
	var preview = document.querySelector( '.jobly-preview' );
	if ( accent && preview ) {
		accent.addEventListener( 'input', function () {
			preview.style.setProperty( '--jobly-accent', accent.value );
		} );
	}
}() );
