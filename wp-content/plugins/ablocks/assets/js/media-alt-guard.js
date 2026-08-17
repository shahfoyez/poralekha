/**
 * Require alt text before an image can be used.
 *
 * Alt text cannot be demanded while a file is uploading — it is post meta
 * written after the attachment exists, and the field to type it into does not
 * render until the upload has already finished. So the gate is on *use*: the
 * modal's insert button stays disabled while a selected image has no alt text.
 *
 * This is a convenience, not the enforcement. Anything that bypasses the modal —
 * a pasted block, an import, a REST call — bypasses this too, which is why the
 * server also refuses to publish a post containing images without alt text. That
 * check is the one that actually holds.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.media || ! wp.media.view ) {
		return;
	}

	var strings = window.aBlocksAltGuard || {};
	var MESSAGE = strings.message || 'Add alt text before using this image.';
	var HINT = strings.hint || '';

	/**
	 * Does this attachment still need alt text?
	 *
	 * Only images: audio, video and documents have no alt text to give, and
	 * blocking them would be nonsense.
	 *
	 * @param {Object} model Attachment model.
	 * @return {boolean} True when alt text is missing.
	 */
	function needsAlt( model ) {
		if ( ! model || 'image' !== model.get( 'type' ) ) {
			return false;
		}
		var alt = model.get( 'alt' );
		return ! alt || ! String( alt ).trim();
	}

	/**
	 * Which of the selected attachments are missing alt text.
	 *
	 * @param {Object} selection Backbone collection.
	 * @return {Array} Offending models.
	 */
	function offenders( selection ) {
		if ( ! selection || ! selection.models ) {
			return [];
		}
		return selection.models.filter( needsAlt );
	}

	/**
	 * Enable or disable a toolbar's primary button to match the selection.
	 *
	 * @param {Object} toolbar Media toolbar view.
	 */
	function syncToolbar( toolbar ) {
		if ( ! toolbar || ! toolbar.get ) {
			return;
		}

		var controller = toolbar.controller;
		var state = controller && controller.state && controller.state();
		var selection = state && state.get && state.get( 'selection' );
		var blocked = offenders( selection );

		[ 'select', 'insert' ].forEach( function ( id ) {
			var button = toolbar.get( id );
			if ( ! button || ! button.$el ) {
				return;
			}
			button.model.set( 'disabled', blocked.length > 0 );
			button.$el.attr(
				'title',
				blocked.length > 0 ? MESSAGE : ''
			);
		} );

		var $notice = toolbar.$el.find( '.ablocks-alt-required' );
		if ( blocked.length > 0 ) {
			if ( ! $notice.length ) {
				toolbar.$el.prepend(
					'<div class="ablocks-alt-required" style="flex:1 1 100%;color:#8a1f1f;font-size:12px;padding:4px 0;">' +
						MESSAGE +
						'</div>'
				);
			}
		} else {
			$notice.remove();
		}
	}

	// The toolbar is rebuilt whenever the state changes, so the hook is on the
	// view rather than on a one-time DOM query.
	var Toolbar = wp.media.view.Toolbar;
	wp.media.view.Toolbar = Toolbar.extend( {
		initialize: function () {
			Toolbar.prototype.initialize.apply( this, arguments );

			var self = this;
			var controller = this.controller;
			if ( ! controller || ! controller.state ) {
				return;
			}

			var state = controller.state();
			var selection = state && state.get && state.get( 'selection' );

			if ( selection ) {
				// 'change:alt' fires as soon as the field is edited, so the
				// button unlocks while the user is still looking at it.
				this.listenTo( selection, 'add remove reset change:alt', function () {
					syncToolbar( self );
				} );
			}

			this.listenTo( controller, 'content:render', function () {
				syncToolbar( self );
			} );

			window.setTimeout( function () {
				syncToolbar( self );
			}, 0 );
		},
	} );

	// Mark the field itself, so the reason is visible where the fix happens
	// rather than only at the button that refuses.
	if ( wp.media.view.Attachment && wp.media.view.Attachment.Details ) {
		var Details = wp.media.view.Attachment.Details;
		wp.media.view.Attachment.Details = Details.extend( {
			render: function () {
				Details.prototype.render.apply( this, arguments );

				if ( 'image' !== this.model.get( 'type' ) ) {
					return this;
				}

				var $alt = this.$el.find( '.setting[data-setting="alt"]' );
				if ( ! $alt.length ) {
					return this;
				}

				$alt.find( 'input' ).attr( 'required', 'required' );

				if ( HINT && ! $alt.next( '.ablocks-alt-hint' ).length ) {
					$alt.after(
						'<span class="ablocks-alt-hint" style="display:block;padding:2px 0 8px;font-size:11px;color:#6c7781;">' +
							HINT +
							'</span>'
					);
				}

				return this;
			},
		} );
	}
} )( window.wp );
