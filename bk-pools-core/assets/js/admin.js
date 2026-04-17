/**
 * BK Pools Core — Admin Scripts
 *
 * Handles the WordPress media picker for the company logo field on the
 * BK Pools Settings page.
 *
 * @package BK_Pools_Core
 * @since   1.0.0
 */

/* global wp, jQuery */

( function ( $ ) {
	'use strict';

	/**
	 * Initialises the media uploader buttons on the settings page.
	 *
	 * @since 1.0.0
	 * @return {void}
	 */
	function initMediaUploader() {
		var mediaUploader;

		// -- Upload / select button -------------------------------------------

		$( document ).on( 'click', '.bk-media-upload', function ( e ) {
			e.preventDefault();

			var $button  = $( this );
			var targetId = $button.data( 'target' );
			var $field   = $( '#' + targetId );
			var $preview = $button.closest( '.bk-media-field' ).find( '.bk-media-preview' );

			if ( mediaUploader ) {
				mediaUploader.open();
				return;
			}

			mediaUploader = wp.media( {
				title:    'Select Company Logo',
				button:   { text: 'Use this image' },
				multiple: false,
				library:  { type: 'image' },
			} );

			mediaUploader.on( 'select', function () {
				var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();

				$field.val( attachment.id );

				var imgUrl = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$preview.html( '<img src="' + imgUrl + '" alt="" style="max-width:150px;height:auto;display:block;" />' );

				// Show remove button if not already present.
				if ( ! $button.siblings( '.bk-media-remove' ).length ) {
					$button.after(
						$( '<button>' )
							.attr( 'type', 'button' )
							.addClass( 'button bk-media-remove' )
							.attr( 'data-target', targetId )
							.css( 'margin-left', '4px' )
							.text( 'Remove' )
					);
				}
			} );

			mediaUploader.open();
		} );

		// -- Remove button ----------------------------------------------------

		$( document ).on( 'click', '.bk-media-remove', function ( e ) {
			e.preventDefault();

			var $button  = $( this );
			var targetId = $button.data( 'target' );
			var $field   = $( '#' + targetId );
			var $preview = $button.closest( '.bk-media-field' ).find( '.bk-media-preview' );

			$field.val( '' );
			$preview.html( '' );
			$button.remove();

			// Reset the cached uploader so a fresh instance opens next time.
			mediaUploader = null;
		} );
	}

	// Initialise on DOM ready.
	$( initMediaUploader );

} )( jQuery );
