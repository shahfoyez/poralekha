/**
 * StoreEngine Membership — block-level visibility control.
 *
 * Build-free: uses the global `wp` object (no JSX / webpack). Adds two
 * attributes to every block and a "StoreEngine Visibility" panel to the block
 * inspector. Enforcement is server-side via the render_block filter
 * (see block-restriction.php); this file only handles the editor UX.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var addFilter = wp.hooks.addFilter;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var FormTokenField = wp.components.FormTokenField;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var config = window.StoreEngineMembershipBlocks || {
		groups: [],
		membershipActive: false,
		activateUrl: '',
	};
	var GROUPS = config.groups || [];
	var MEMBERSHIP_ACTIVE = !! config.membershipActive;
	var ACTIVATE_URL = config.activateUrl || '';

	// FormTokenField keys tokens by their display string, so same-named groups
	// would collide. Build a display label that only appends "(#id)" when a name
	// is shared, keeping unique names clean while disambiguating duplicates.
	var GROUP_LABEL_BY_ID = {};
	var GROUP_ID_BY_LABEL = {};
	( function () {
		var counts = {};
		GROUPS.forEach( function ( g ) {
			counts[ g.label ] = ( counts[ g.label ] || 0 ) + 1;
		} );
		GROUPS.forEach( function ( g ) {
			var display =
				counts[ g.label ] > 1 ? g.label + ' (#' + g.value + ')' : g.label;
			GROUP_LABEL_BY_ID[ g.value ] = display;
			GROUP_ID_BY_LABEL[ display ] = g.value;
		} );
	} )();
	var GROUP_SUGGESTIONS = GROUPS.map( function ( g ) {
		return GROUP_LABEL_BY_ID[ g.value ];
	} );

	var PANEL_DESCRIPTION = __(
		'Choose who can see this block: everyone, logged-in / logged-out visitors, or members of specific groups.',
		'storeengine'
	);

	/**
	 * Compact access-group picker: a token/tag field with built-in search and
	 * multi-select. Selections are stored as group ids; display strings are
	 * mapped back to ids on change so duplicate names never clash.
	 */
	function GroupSelector( props ) {
		var selected = props.selected || [];
		var onChange = props.onChange;

		if ( ! GROUPS.length ) {
			return el(
				'p',
				{ style: { fontStyle: 'italic', opacity: 0.7 } },
				__( 'No access groups created yet.', 'storeengine' )
			);
		}

		return el( FormTokenField, {
			label: __( 'Access groups', 'storeengine' ),
			value: selected
				.map( function ( id ) {
					return GROUP_LABEL_BY_ID[ id ];
				} )
				.filter( Boolean ),
			suggestions: GROUP_SUGGESTIONS,
			onChange: function ( tokens ) {
				var ids = [];
				tokens.forEach( function ( token ) {
					if (
						Object.prototype.hasOwnProperty.call(
							GROUP_ID_BY_LABEL,
							token
						)
					) {
						ids.push( GROUP_ID_BY_LABEL[ token ] );
					}
				} );
				onChange( ids );
			},
			// Only known groups can be added; free-typed text is rejected.
			__experimentalValidateInput: function ( token ) {
				return Object.prototype.hasOwnProperty.call(
					GROUP_ID_BY_LABEL,
					token
				);
			},
			__experimentalExpandOnFocus: true,
			__nextHasNoMarginBottom: true,
		} );
	}

	var VISIBILITY_OPTIONS = [
		{ label: __( 'Everyone', 'storeengine' ), value: 'everyone' },
		{ label: __( 'Logged-in users', 'storeengine' ), value: 'logged_in' },
		{ label: __( 'Logged-out users', 'storeengine' ), value: 'logged_out' },
		{ label: __( 'Members of selected groups', 'storeengine' ), value: 'members' },
	];

	// 1. Register the two attributes on every block type.
	addFilter(
		'blocks.registerBlockType',
		'storeengine/membership-block-attributes',
		function ( settings ) {
			if ( ! settings.attributes ) {
				settings.attributes = {};
			}
			settings.attributes.storeengineVisibility = {
				type: 'string',
				default: '',
			};
			settings.attributes.storeengineGroups = {
				type: 'array',
				default: [],
			};

			return settings;
		}
	);

	// 2. Add the inspector panel to every block.
	var withInspector = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			if ( ! props.isSelected ) {
				return el( BlockEdit, props );
			}

			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var visibility = attributes.storeengineVisibility || 'everyone';
			var selectedGroups = attributes.storeengineGroups || [];

			var description = el(
				'p',
				{
					style: {
						margin: '0 0 12px',
						fontSize: '12px',
						color: '#6b7280',
					},
				},
				PANEL_DESCRIPTION
			);

			// Membership addon inactive → promo instead of controls.
			if ( ! MEMBERSHIP_ACTIVE ) {
				var promo = el(
					'div',
					{
						style: {
							background:
								'linear-gradient(135deg, #2d2f88 0%, #5a3fb5 100%)',
							color: '#fff',
							borderRadius: '8px',
							padding: '14px',
							fontSize: '13px',
							lineHeight: 1.5,
						},
					},
					el(
						'strong',
						{ style: { display: 'block', marginBottom: '6px' } },
						__( 'Unlock block restrictions', 'storeengine' )
					),
					el(
						'span',
						{ style: { display: 'block', marginBottom: '10px', opacity: 0.9 } },
						__(
							'Block-level access control is part of the StoreEngine Membership addon. Activate it to gate any block by login state or access group.',
							'storeengine'
						)
					),
					ACTIVATE_URL
						? el(
								'a',
								{
									href: ACTIVATE_URL,
									className: 'components-button is-primary',
									style: {
										background: '#fff',
										color: '#2d2f88',
										fontWeight: 600,
									},
								},
								__( 'Activate Membership', 'storeengine' )
						  )
						: null
				);

				return el(
					Fragment,
					{},
					el( BlockEdit, props ),
					el(
						InspectorControls,
						{},
						el(
							PanelBody,
							{
								title: __( 'Restrict This Block', 'storeengine' ),
								initialOpen: false,
							},
							description,
							promo
						)
					)
				);
			}

			var groupSelector =
				'members' === visibility
					? el( GroupSelector, {
							selected: selectedGroups,
							onChange: function ( next ) {
								setAttributes( { storeengineGroups: next } );
							},
					  } )
					: null;

			return el(
				Fragment,
				{},
				el( BlockEdit, props ),
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Restrict This Block', 'storeengine' ),
							initialOpen: false,
						},
						description,
						el( SelectControl, {
							label: __( 'Who can see this block?', 'storeengine' ),
							value: visibility,
							options: VISIBILITY_OPTIONS,
							onChange: function ( value ) {
								setAttributes( {
									storeengineVisibility: 'everyone' === value ? '' : value,
								} );
							},
						} ),
						groupSelector
					)
				)
			);
		};
	}, 'withStoreEngineMembershipInspector' );

	addFilter(
		'editor.BlockEdit',
		'storeengine/membership-block-inspector',
		withInspector
	);
} )( window.wp );
