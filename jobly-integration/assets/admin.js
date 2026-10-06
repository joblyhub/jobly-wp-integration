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

	// Media Library picker for the landing hero image.
	document.addEventListener( 'click', function ( e ) {
		var box = e.target.closest( '[data-jobly-media]' );
		if ( ! box || ! window.wp || ! wp.media ) {
			return;
		}
		var input = box.querySelector( 'input[type="hidden"]' );
		var preview = box.querySelector( '.jobly-media__preview' );
		if ( e.target.closest( '[data-jobly-media-clear]' ) ) {
			input.value = '';
			preview.textContent = '';
			return;
		}
		var pick = e.target.closest( '[data-jobly-media-pick]' );
		if ( ! pick ) {
			return;
		}
		var frame = wp.media( { title: pick.getAttribute( 'data-title' ), multiple: false, library: { type: 'image' } } );
		frame.on( 'select', function () {
			var a = frame.state().get( 'selection' ).first().toJSON();
			input.value = a.id;
			preview.textContent = '';
			var img = document.createElement( 'img' );
			img.src = ( a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url );
			img.alt = '';
			preview.appendChild( img );
		} );
		frame.open();
	} );

	// Live SERP / social preview while typing SEO templates.
	function fill( tpl, vars ) {
		Object.keys( vars ).forEach( function ( k ) {
			tpl = tpl.split( k ).join( vars[ k ] );
		} );
		return tpl.replace( /\s+/g, ' ' ).replace( /\.\.(?!\.)/g, '.' ).trim();
	}
	document.addEventListener( 'input', function ( e ) {
		var input = e.target.closest( '[data-serp-input]' );
		if ( ! input ) {
			return;
		}
		var scope = input.getAttribute( 'data-serp-for' ) || 'job';
		var box = document.querySelector( '[data-jobly-seo-preview="' + scope + '"]' );
		if ( ! box ) {
			return;
		}
		var vars = JSON.parse( box.getAttribute( 'data-vars' ) || '{}' );
		var sel = input.getAttribute( 'data-serp-input' ) === 'title' ? '[data-serp-title]' : '[data-serp-desc]';
		var text = fill( input.value || input.getAttribute( 'placeholder' ) || '', vars );
		box.querySelectorAll( sel ).forEach( function ( el ) {
			el.textContent = text;
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
