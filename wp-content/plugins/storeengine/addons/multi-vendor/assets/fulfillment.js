/**
 * Vendor order fulfilment — Save handler for the vendor "Store orders" page.
 *
 * Delegated on document so it works whether the dashboard renders server-side
 * or injects the page content. Reads the REST endpoint + nonce from the
 * fulfilment container's data attributes; i18n strings come from the localized
 * `StoreEngineVendorFulfillment` object.
 */
( function () {
	'use strict';

	var L = window.StoreEngineVendorFulfillment || {};
	var i18n = L.i18n || {};
	var t = function ( key, fallback ) {
		return i18n[ key ] || fallback;
	};

	// Accordion: collapse orders by default, expand the one the vendor clicks.
	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest( '[data-role="toggle"]' );
		if ( ! toggle ) {
			return;
		}
		var order = toggle.closest( '.storeengine-vendor-fulfillment__order' );
		if ( ! order ) {
			return;
		}
		var open = order.classList.toggle( 'is-open' );
		toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-role="save"]' );
		if ( ! btn ) {
			return;
		}

		var root = btn.closest( '.storeengine-vendor-fulfillment' );
		var item = btn.closest( '.storeengine-vendor-fulfillment__item' );
		if ( ! root || ! item ) {
			return;
		}

		var endpoint = root.getAttribute( 'data-se-fulfillment-endpoint' );
		var nonce = root.getAttribute( 'data-se-nonce' );
		var feedback = item.querySelector( '[data-role="feedback"]' );

		var getField = function ( field ) {
			var el = item.querySelector( '[data-field="' + field + '"]' );
			return el ? el.value : '';
		};
		var setFeedback = function ( msg, cls ) {
			if ( feedback ) {
				feedback.textContent = msg;
				feedback.className =
					'storeengine-vendor-fulfillment__feedback' +
					( cls ? ' ' + cls : '' );
			}
		};

		var status = getField( 'shipping_status' );
		if ( ! status ) {
			setFeedback( t( 'pick_status', 'Pick a status first.' ), 'is-error' );
			return;
		}

		btn.disabled = true;
		setFeedback( t( 'saving', 'Saving…' ) );

		fetch( endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce,
			},
			body: JSON.stringify( {
				order_item_id: parseInt(
					item.getAttribute( 'data-order-item-id' ),
					10
				),
				shipping_status: status,
				courier: getField( 'courier' ),
				tracking_number: getField( 'tracking_number' ),
				tracking_url: getField( 'tracking_url' ),
			} ),
		} )
			.then( function ( res ) {
				return res.json().then( function ( data ) {
					return { ok: res.ok, data: data };
				} );
			} )
			.then( function ( r ) {
				btn.disabled = false;
				if ( ! r.ok ) {
					setFeedback(
						( r.data && r.data.message ) ||
							t( 'error', 'Could not update. Please try again.' ),
						'is-error'
					);
					return;
				}

				// Reflect the new status: update the pill + lock backward options.
				var pill = item.querySelector( '[data-role="current-status"]' );
				if ( pill && r.data.status_label ) {
					pill.textContent = r.data.status_label;
					pill.className =
						'storeengine-vendor-fulfillment__pill storeengine-vendor-fulfillment__pill--' +
						r.data.shipping_status;
				}

				var select = item.querySelector(
					'[data-field="shipping_status"]'
				);
				if ( select ) {
					var passed = true;
					for ( var i = 0; i < select.options.length; i++ ) {
						var opt = select.options[ i ];
						opt.disabled = false;
						if ( opt.value === r.data.shipping_status ) {
							passed = false;
							opt.selected = true;
						} else if ( passed && opt.value ) {
							opt.disabled = true;
						}
					}
				}

				setFeedback( t( 'saved', 'Saved.' ), 'is-success' );
			} )
			.catch( function () {
				btn.disabled = false;
				setFeedback(
					t( 'network_error', 'Network error. Please try again.' ),
					'is-error'
				);
			} );
	} );
}() );
