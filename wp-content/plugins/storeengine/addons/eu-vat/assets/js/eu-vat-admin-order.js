/**
 * EU VAT — Admin order editor field.
 *
 * Injects an editable "VAT Number" card into the React order editor sidebar
 * (via the storeengine.order_editor.sidebar filter). Lets an admin set/clear
 * the VAT number on any order — including guest orders that had no VAT at
 * checkout — so it shows on the regenerated invoice.
 *
 * No build step: uses wp.element + wp.hooks from the shared admin bundle.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.hooks ) {
		return;
	}

	const { createElement: el, useState, useEffect, Fragment } = wp.element;
	const { addFilter } = wp.hooks;
	const __ = ( wp.i18n && wp.i18n.__ ) || ( ( s ) => s );

	const g = window.StoreEngineGlobal || {};
	const ajaxUrl = g.ajaxurl;
	const nonce = g.storeengine_nonce;

	function request( action, data ) {
		const body = new URLSearchParams(
			Object.assign( { action: 'storeengine/' + action, security: nonce }, data )
		);
		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} )
			.then( ( r ) => r.json() )
			.then( ( res ) => {
				if ( ! res || ! res.success ) {
					throw new Error(
						( res && res.data && res.data.message ) ||
							__( 'Request failed.', 'storeengine' )
					);
				}
				return res.data || {};
			} );
	}

	function OrderVatField( { orderId } ) {
		const [ value, setValue ] = useState( '' );
		const [ saving, setSaving ] = useState( false );
		const [ status, setStatus ] = useState( '' );

		useEffect( () => {
			let active = true;
			request( 'eu_vat/get_order_vat', { order_id: orderId } )
				.then( ( data ) => {
					if ( active ) {
						setValue( data.vat_number || '' );
					}
				} )
				.catch( () => {} );
			return () => {
				active = false;
			};
		}, [ orderId ] );

		function save() {
			setSaving( true );
			setStatus( '' );
			request( 'eu_vat/save_order_vat', {
				order_id: orderId,
				vat_number: value,
			} )
				.then( ( data ) => {
					setValue( data.vat_number || '' );
					setStatus( __( 'Saved.', 'storeengine' ) );
				} )
				.catch( ( e ) => {
					setStatus( e.message );
				} )
				.finally( () => setSaving( false ) );
		}

		return el(
			'div',
			{ className: 'storeengine-content-section storeengine-eu-vat-order' },
			el(
				'p',
				{ className: 'storeengine-heading' },
				__( 'VAT Number', 'storeengine' )
			),
			el( 'input', {
				type: 'text',
				className: 'storeengine-input',
				value,
				placeholder: __( 'e.g. DE123456789', 'storeengine' ),
				onChange: ( e ) => setValue( e.target.value ),
			} ),
			el(
				'button',
				{
					type: 'button',
					className: 'storeengine-btn storeengine-btn--preset-blue',
					disabled: saving,
					onClick: save,
					style: { marginTop: '8px' },
				},
				saving
					? __( 'Saving…', 'storeengine' )
					: __( 'Save VAT Number', 'storeengine' )
			),
			status
				? el(
						'p',
						{ className: 'storeengine-eu-vat-order__status' },
						status
				  )
				: null
		);
	}

	addFilter(
		'storeengine.order_editor.sidebar',
		'storeengine-eu-vat/order-vat',
		function ( content, ctx ) {
			const orderId = ctx && ctx.orderId;
			if ( ! orderId ) {
				return content;
			}
			return el(
				Fragment,
				null,
				content,
				el( OrderVatField, { key: 'storeengine-eu-vat-order', orderId } )
			);
		}
	);
} )( window.wp );
