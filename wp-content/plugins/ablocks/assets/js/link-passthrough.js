/**
 * aBlocks — Query Param Passthrough (frontend).
 *
 * Reads tracked query params (ref, utm_*, …) from the current URL and appends
 * whichever are present to every "tagged" link on the page. A link is tagged
 * when it (or a wrapping block) carries the configured CSS class — so it works
 * both when the class sits on the <a> and when Gutenberg puts it on the block
 * wrapper with the <a> nested inside.
 *
 * Config is injected before this file via wp_add_inline_script as
 * window.ABlocksLinkPassthrough = { keys: string[], linkClass: string }.
 */
( function () {
	'use strict';

	var config = window.ABlocksLinkPassthrough;
	if ( ! config || ! Array.isArray( config.keys ) || ! config.keys.length ) {
		return;
	}
	if (
		typeof window.URL !== 'function' ||
		typeof window.URLSearchParams !== 'function'
	) {
		return; // Very old browser — bail rather than throw.
	}

	var mode = config.mode || 'class';
	var linkClass = ( config.linkClass || 'aff-link' ).trim();
	var keywords = ( Array.isArray( config.keywords ) ? config.keywords : [] )
		.map( function ( word ) {
			return String( word ).toLowerCase();
		} )
		.filter( Boolean );

	// Nothing can match — bail before touching the DOM.
	if ( mode === 'class' && ! linkClass ) {
		return;
	}
	if ( mode === 'keyword' && ! keywords.length ) {
		return;
	}

	var persist = config.persist !== false;
	var cookieDays = parseInt( config.cookieDays, 10 );
	if ( isNaN( cookieDays ) ) {
		cookieDays = 30;
	}
	var COOKIE = 'ablocks_pt';

	function readCookie( name ) {
		var parts = ( document.cookie || '' ).split( '; ' );
		for ( var i = 0; i < parts.length; i++ ) {
			var eq = parts[ i ].indexOf( '=' );
			if ( eq > -1 && parts[ i ].slice( 0, eq ) === name ) {
				return decodeURIComponent( parts[ i ].slice( eq + 1 ) );
			}
		}
		return '';
	}

	function writeCookie( name, value, days ) {
		var expires = '';
		if ( days > 0 ) {
			var d = new Date();
			d.setTime( d.getTime() + days * 24 * 60 * 60 * 1000 );
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie =
			name +
			'=' +
			encodeURIComponent( value ) +
			expires +
			'; path=/; SameSite=Lax';
	}

	// Fresh values come from the current URL; remembered values come from the
	// cookie. The URL always wins (a newer ?ref= overrides a stored one).
	var current = new URLSearchParams( window.location.search );
	var fresh = {};
	config.keys.forEach( function ( key ) {
		var value = current.get( key );
		if ( value !== null && value !== '' ) {
			fresh[ key ] = value;
		}
	} );

	var stored = {};
	if ( persist ) {
		var raw = readCookie( COOKIE );
		if ( raw ) {
			try {
				new URLSearchParams( raw ).forEach( function ( value, key ) {
					if (
						config.keys.indexOf( key ) !== -1 &&
						value !== '' &&
						fresh[ key ] === undefined
					) {
						stored[ key ] = value;
					}
				} );
			} catch ( e ) {}
		}
	}

	// Effective set = remembered values overlaid by anything fresh in the URL.
	var effective = {};
	config.keys.forEach( function ( key ) {
		if ( fresh[ key ] !== undefined ) {
			effective[ key ] = fresh[ key ];
		} else if ( stored[ key ] !== undefined ) {
			effective[ key ] = stored[ key ];
		}
	} );

	var present = Object.keys( effective ).map( function ( key ) {
		return [ key, effective[ key ] ];
	} );

	if ( ! present.length ) {
		return;
	}

	// Persist whenever the URL brought fresh params, so later pages that drop
	// the query string can still recover them from the cookie.
	if ( persist && Object.keys( fresh ).length ) {
		var out = new URLSearchParams();
		present.forEach( function ( pair ) {
			out.set( pair[ 0 ], pair[ 1 ] );
		} );
		writeCookie( COOKIE, out.toString(), cookieDays );
	}

	// In 'class' mode, match the <a> directly or any <a> inside a tagged
	// wrapper; other modes start from every link and filter per anchor.
	var selector =
		mode === 'class'
			? 'a.' + linkClass + ', .' + linkClass + ' a'
			: 'a[href]';

	function decorate( root ) {
		var anchors;
		try {
			anchors = ( root || document ).querySelectorAll( selector );
		} catch ( e ) {
			return;
		}

		anchors.forEach( function ( anchor ) {
			var href = anchor.getAttribute( 'href' );
			if ( ! href ) {
				return;
			}

			var lowered = href.toLowerCase().replace( /^\s+/, '' );
			if (
				lowered.charAt( 0 ) === '#' ||
				lowered.indexOf( 'mailto:' ) === 0 ||
				lowered.indexOf( 'tel:' ) === 0 ||
				lowered.indexOf( 'javascript:' ) === 0 || // eslint-disable-line no-script-url
				lowered.indexOf( 'data:' ) === 0
			) {
				return;
			}

			var url;
			try {
				url = new URL( href, window.location.href );
			} catch ( e ) {
				return;
			}

			if ( url.protocol !== 'http:' && url.protocol !== 'https:' ) {
				return;
			}

			// 'all' mode is same-site only, so the ref is never leaked to
			// unrelated external domains.
			if ( mode === 'all' && url.origin !== window.location.origin ) {
				return;
			}

			// 'keyword' mode only touches links whose URL contains a word.
			if ( mode === 'keyword' ) {
				var haystack = url.href.toLowerCase();
				var matched = keywords.some( function ( word ) {
					return haystack.indexOf( word ) !== -1;
				} );
				if ( ! matched ) {
					return;
				}
			}

			var changed = false;
			present.forEach( function ( pair ) {
				if ( url.searchParams.get( pair[ 0 ] ) === pair[ 1 ] ) {
					return; // Already carries this exact value.
				}
				url.searchParams.set( pair[ 0 ], pair[ 1 ] );
				changed = true;
			} );

			if ( changed ) {
				anchor.setAttribute( 'href', url.toString() );
			}
		} );
	}

	function run() {
		decorate( document );

		// Re-decorate links injected later (interactive blocks, AJAX content…).
		if ( typeof window.MutationObserver === 'function' ) {
			var observer = new MutationObserver( function ( mutations ) {
				for ( var i = 0; i < mutations.length; i++ ) {
					if ( mutations[ i ].addedNodes.length ) {
						decorate( document );
						return;
					}
				}
			} );
			observer.observe( document.body, {
				childList: true,
				subtree: true,
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
