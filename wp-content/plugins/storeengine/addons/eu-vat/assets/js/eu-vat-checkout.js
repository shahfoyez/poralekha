/**
 * EU VAT — Checkout-side validation. (Vanilla JS, no jQuery.)
 *
 * Listens to changes on #billing_eu_vat_number, debounces, POSTs to the
 * storeengine/eu_vat/validate AJAX endpoint, and renders the progress message
 * inside .storeengine-eu-vat-status. On a valid response, it dispatches an
 * "update_checkout" so the cart totals refresh without VAT.
 */
( function () {
	'use strict';

	if ( typeof window.StoreEngineGlobal === 'undefined' ) {
		return;
	}

	const config = window.StoreEngineGlobal.eu_vat || {};
	const messages = config.messages || {};

	const ajaxUrl = window.StoreEngineGlobal.ajaxurl;
	const nonce = window.StoreEngineGlobal.storeengine_nonce;

	let debounceTimer = null;
	let lastValidatedValue = null;

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		// Delegated input/change handling for the VAT field (the field may be
		// re-rendered by checkout AJAX, so bind on document).
		document.addEventListener( 'input', onVatFieldEvent );
		document.addEventListener( 'change', onVatFieldEvent );

		// Re-validate when billing country changes — exemption rules are
		// country-sensitive, and the user may have changed country after
		// entering VAT.
		document.addEventListener( 'change', function ( e ) {
			if ( ! e.target || ! e.target.closest( '#billing_country' ) ) {
				return;
			}
			const vatField = document.querySelector(
				'#billing_eu_vat_number'
			);
			if ( vatField && vatField.value ) {
				lastValidatedValue = null;
				validate( vatField.value );
			}
		} );
	} );

	function onVatFieldEvent( e ) {
		if ( ! e.target || ! e.target.closest( '#billing_eu_vat_number' ) ) {
			return;
		}
		handleInput( e );
	}

	function handleInput( e ) {
		const value = e.target.value;
		clearTimeout( debounceTimer );

		if ( ! value || value.trim().length < 4 ) {
			renderStatus( '', '' );
			lastValidatedValue = null;
			return;
		}

		if ( value === lastValidatedValue ) {
			return;
		}

		renderStatus( messages.validating || 'Validating…', 'validating' );
		debounceTimer = setTimeout( () => validate( value ), 600 );
	}

	function validate( value ) {
		lastValidatedValue = value;
		const countryEl = document.querySelector( '#billing_country' );
		const companyEl = document.querySelector( '#billing_company' );
		const country = ( countryEl && countryEl.value ) || '';
		const company = ( companyEl && companyEl.value ) || '';

		const body = new URLSearchParams();
		body.set( 'action', 'storeengine/eu_vat/validate' );
		body.set( 'security', nonce );
		body.set( 'vat_number', value );
		body.set( 'billing_country', country );
		body.set( 'billing_company', company );

		fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: body.toString(),
		} )
			.then( ( res ) => res.json() )
			.then( ( response ) => {
				if ( ! response || ! response.success ) {
					renderStatus(
						messages.validation_failed || 'Validation failed.',
						'failed'
					);
					return;
				}
				const r = response.data || {};
				renderStatus( r.message || '', r.state || '' );
				if ( r.valid ) {
					document.dispatchEvent(
						new CustomEvent( 'storeengine_eu_vat_validated', {
							detail: r,
							bubbles: true,
						} )
					);
					triggerCartRefresh();
				}
			} )
			.catch( () => {
				renderStatus(
					messages.validation_failed || 'Validation failed.',
					'failed'
				);
			} );
	}

	function renderStatus( text, state ) {
		const field = document.querySelector( '#billing_eu_vat_number' );
		if ( ! field ) {
			return;
		}
		const parent = field.parentNode;
		let status = parent
			? parent.querySelector( '.storeengine-eu-vat-status' )
			: null;
		if ( ! status ) {
			status = document.createElement( 'div' );
			status.className = 'storeengine-eu-vat-status';
			field.insertAdjacentElement( 'afterend', status );
		}
		status.classList.remove(
			'is-validating',
			'is-valid',
			'is-invalid',
			'is-failed'
		);
		status.textContent = text || '';
		if ( state ) {
			const cls =
				state === 'valid'
					? 'valid'
					: state === 'invalid_format' || state === 'invalid'
					? 'invalid'
					: state;
			status.classList.add( 'is-' + cls );
		}
	}

	function triggerCartRefresh() {
		// StoreEngine's checkout JS listens for changes to billing fields and
		// re-runs update_checkout. The cleanest way to nudge it is to fire a
		// change on the country field, which it already debounces.
		document.body.dispatchEvent(
			new CustomEvent( 'storeengine_update_checkout', { bubbles: true } )
		);
		// Best-effort fallback for older builds.
		const country = document.querySelector( '#billing_country' );
		if ( country ) {
			country.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}
} )();
