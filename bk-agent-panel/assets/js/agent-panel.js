/**
 * BK Agent Panel — front-end JS.
 *
 * Vanilla JS only (no jQuery). All AJAX calls use fetch().
 * Depends on bkPanel global injected via wp_localize_script.
 *
 * @package BK_Agent_Panel
 * @since   1.0.0
 */

( function () {
	'use strict';

	// -------------------------------------------------------
	// Guard — bkPanel must exist.
	// -------------------------------------------------------
	if ( typeof bkPanel === 'undefined' ) {
		return;
	}

	const { ajax_url, nonce } = bkPanel;

	// -------------------------------------------------------
	// Helpers
	// -------------------------------------------------------

	/**
	 * POST to WP AJAX endpoint using application/x-www-form-urlencoded.
	 *
	 * @param {string} action WP AJAX action name.
	 * @param {Object} data   Key/value payload.
	 * @returns {Promise<Object>} Parsed JSON response.
	 */
	function ajaxPost( action, data ) {
		const body = new URLSearchParams( { action, nonce, ...data } );
		return fetch( ajax_url, { method: 'POST', body } )
			.then( function ( r ) { return r.json(); } );
	}

	/**
	 * POST using FormData (for file uploads or form serialisation).
	 *
	 * @param {string}   action WP AJAX action name.
	 * @param {FormData} fd     FormData instance.
	 * @returns {Promise<Object>} Parsed JSON response.
	 */
	function ajaxFormData( action, fd ) {
		fd.append( 'action', action );
		fd.append( 'nonce', nonce );
		return fetch( ajax_url, { method: 'POST', body: fd } )
			.then( function ( r ) { return r.json(); } );
	}

	/**
	 * Extract the error message string from a WP AJAX error response.
	 *
	 * wp_send_json_error() wraps messages in {success:false, data:{message:'...'}}
	 * so we check data.message first, then fall back to the data value itself.
	 *
	 * @param {*}      data     res.data from a failed AJAX response.
	 * @param {string} fallback Fallback string if no message found.
	 * @returns {string}
	 */
	function errorMsg( data, fallback ) {
		if ( data && typeof data === 'object' && data.message ) {
			return data.message;
		}
		if ( typeof data === 'string' && data.length ) {
			return data;
		}
		return fallback;
	}

	/**
	 * Show a brief toast notification at the bottom-right of the screen.
	 *
	 * @param {string}              msg  Message text.
	 * @param {'success'|'error'} [type] Visual variant. Default 'success'.
	 */
	function toast( msg, type ) {
		var el = document.getElementById( 'bk-toast' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = 'bk-toast';
			el.className = 'bk-toast';
			document.body.appendChild( el );
		}
		el.textContent = msg;
		el.className = 'bk-toast bk-toast--' + ( type || 'success' );
		el.classList.add( 'bk-toast--visible' );
		clearTimeout( el._timer );
		el._timer = setTimeout( function () {
			el.classList.remove( 'bk-toast--visible' );
		}, 3000 );
	}

	// -------------------------------------------------------
	// Mobile nav toggle
	// -------------------------------------------------------

	var navToggle = document.querySelector( '[data-bk-nav-toggle]' );
	var nav       = document.querySelector( '[data-bk-nav]' );

	if ( navToggle && nav ) {
		navToggle.addEventListener( 'click', function () {
			nav.classList.toggle( 'bk-panel-nav--open' );
			var expanded = nav.classList.contains( 'bk-panel-nav--open' );
			navToggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		} );
	}

	// -------------------------------------------------------
	// Lead row expand/collapse
	//
	// IMPORTANT: only the dedicated expand button carries [data-bk-lead-toggle].
	// The <tr> itself does NOT have that attribute, so clicking stars or the
	// status select inside a row will not trigger this handler.
	// -------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var toggleEl = e.target.closest( '[data-bk-lead-toggle]' );
		if ( ! toggleEl ) {
			return;
		}

		var detailId  = toggleEl.getAttribute( 'data-bk-lead-toggle' );
		var detailRow = document.getElementById( detailId );
		if ( ! detailRow ) {
			return;
		}

		var isOpen = ! detailRow.classList.contains( 'bk-hidden' );

		// Close all open detail rows first.
		document.querySelectorAll( '.bk-leads-table__detail' ).forEach( function ( row ) {
			row.classList.add( 'bk-hidden' );
		} );
		document.querySelectorAll( '.bk-leads-table__row--open' ).forEach( function ( row ) {
			row.classList.remove( 'bk-leads-table__row--open' );
		} );
		document.querySelectorAll( '[data-bk-lead-toggle]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-expanded', 'false' );
			var icon = btn.querySelector( '.bk-lead-toggle-btn__icon' );
			if ( icon ) { icon.innerHTML = '&#9660;'; }
		} );

		if ( ! isOpen ) {
			detailRow.classList.remove( 'bk-hidden' );

			var parentRow = detailRow.previousElementSibling;
			if ( parentRow ) {
				parentRow.classList.add( 'bk-leads-table__row--open' );
			}

			toggleEl.setAttribute( 'aria-expanded', 'true' );
			var icon = toggleEl.querySelector( '.bk-lead-toggle-btn__icon' );
			if ( icon ) { icon.innerHTML = '&#9650;'; }
		}
	} );

	// -------------------------------------------------------
	// Lead status update
	// -------------------------------------------------------

	document.addEventListener( 'change', function ( e ) {
		var select = e.target.closest( '[data-bk-status-select]' );
		if ( ! select ) {
			return;
		}

		var leadAgentId = select.getAttribute( 'data-lead-agent-id' );
		var newStatus   = select.value;

		// Swap CSS colour class immediately (optimistic UI).
		select.className = select.className.replace( /bk-status-select--\S+/g, '' ).trim();
		select.classList.add( 'bk-status-select--' + newStatus );

		ajaxPost( 'bk_update_lead_status', { lead_agent_id: leadAgentId, status: newStatus } )
			.then( function ( res ) {
				if ( res.success ) {
					toast( res.data.message || 'Status updated.' );
				} else {
					toast( errorMsg( res.data, 'Could not update status.' ), 'error' );
				}
			} )
			.catch( function () {
				toast( 'Request failed.', 'error' );
			} );
	} );

	// -------------------------------------------------------
	// Star rating
	//
	// e.stopPropagation() prevents the click from bubbling to any ancestor
	// that might trigger row expand (though the TR no longer has that attr).
	// -------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var star = e.target.closest( '[data-bk-stars] .bk-star' );
		if ( ! star ) {
			return;
		}

		// Prevent row-expand and any other ancestor handlers firing.
		e.stopPropagation();

		var widget      = star.closest( '[data-bk-stars]' );
		var leadAgentId = widget.getAttribute( 'data-lead-agent-id' );
		var value       = parseInt( star.getAttribute( 'data-value' ), 10 );

		// Toggle off: clicking the same star again sends 0 (unrate).
		var currentRating = parseInt( widget.getAttribute( 'data-rating' ), 10 ) || 0;
		var newRating     = ( value === currentRating ) ? 0 : value;

		// Optimistic UI.
		widget.setAttribute( 'data-rating', newRating );
		widget.querySelectorAll( '.bk-star' ).forEach( function ( s, idx ) {
			s.classList.toggle( 'bk-star--filled', idx < newRating );
		} );

		ajaxPost( 'bk_update_lead_rating', { lead_agent_id: leadAgentId, rating: newRating } )
			.then( function ( res ) {
				if ( ! res.success ) {
					// Revert on failure.
					widget.setAttribute( 'data-rating', currentRating );
					widget.querySelectorAll( '.bk-star' ).forEach( function ( s, idx ) {
						s.classList.toggle( 'bk-star--filled', idx < currentRating );
					} );
					toast( errorMsg( res.data, 'Could not save rating.' ), 'error' );
				}
			} )
			.catch( function () {
				toast( 'Request failed.', 'error' );
			} );
	} );

	// Hover preview — highlight stars up to the hovered index.
	document.addEventListener( 'mouseover', function ( e ) {
		var star = e.target.closest( '[data-bk-stars] .bk-star' );
		if ( ! star ) { return; }
		var widget = star.closest( '[data-bk-stars]' );
		var value  = parseInt( star.getAttribute( 'data-value' ), 10 );
		widget.querySelectorAll( '.bk-star' ).forEach( function ( s, idx ) {
			s.classList.toggle( 'bk-star--filled', idx < value );
		} );
	} );

	document.addEventListener( 'mouseout', function ( e ) {
		var star = e.target.closest( '[data-bk-stars] .bk-star' );
		if ( ! star ) { return; }
		var widget = star.closest( '[data-bk-stars]' );
		var rating = parseInt( widget.getAttribute( 'data-rating' ), 10 ) || 0;
		widget.querySelectorAll( '.bk-star' ).forEach( function ( s, idx ) {
			s.classList.toggle( 'bk-star--filled', idx < rating );
		} );
	} );

	// -------------------------------------------------------
	// Notes save
	// -------------------------------------------------------

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-bk-save-notes]' );
		if ( ! btn ) { return; }

		var leadAgentId = btn.getAttribute( 'data-bk-save-notes' );
		var textarea    = document.querySelector( '[data-bk-notes][data-lead-agent-id="' + leadAgentId + '"]' );
		var msgEl       = document.querySelector( '[data-bk-notes-msg="' + leadAgentId + '"]' );

		if ( ! textarea ) { return; }

		btn.disabled = true;

		// Action name must match the PHP wp_ajax_ hook: bk_update_lead_notes.
		ajaxPost( 'bk_update_lead_notes', { lead_agent_id: leadAgentId, notes: textarea.value } )
			.then( function ( res ) {
				if ( res.success ) {
					if ( msgEl ) {
						msgEl.classList.remove( 'bk-hidden' );
						setTimeout( function () { msgEl.classList.add( 'bk-hidden' ); }, 2500 );
					}
				} else {
					toast( errorMsg( res.data, 'Could not save notes.' ), 'error' );
				}
			} )
			.catch( function () {
				toast( 'Request failed.', 'error' );
			} )
			.finally( function () {
				btn.disabled = false;
			} );
	} );

	// -------------------------------------------------------
	// Inline price input (pricing table)
	//
	// Sends price_incl (agent's all-inclusive VAT-inclusive price) to
	// bk_update_agent_pricing which upserts (insert or update) so new
	// rows are created automatically.
	// -------------------------------------------------------

	var priceDebounce = {};

	document.addEventListener( 'change', function ( e ) {
		var input = e.target.closest( '[data-bk-price-input]' );
		if ( input ) { savePriceInput( input ); }
	} );

	document.addEventListener( 'keydown', function ( e ) {
		var input = e.target.closest( '[data-bk-price-input]' );
		if ( ! input || e.key !== 'Enter' ) { return; }
		e.preventDefault();
		savePriceInput( input );
		input.blur();
	} );

	function savePriceInput( input ) {
		var shapeId   = input.getAttribute( 'data-shape-id' );
		var pricingId = input.getAttribute( 'data-pricing-id' ) || '0';
		var price     = parseFloat( input.value );

		if ( isNaN( price ) || price < 0 ) { return; }

		clearTimeout( priceDebounce[ shapeId ] );
		priceDebounce[ shapeId ] = setTimeout( function () {
			ajaxPost( 'bk_update_agent_pricing', {
				pricing_id:    pricingId,
				pool_shape_id: shapeId,
				price_incl:    price,   // PHP reads $_POST['price_incl'] (all-inclusive incl. VAT)
			} ).then( function ( res ) {
				if ( res.success ) {
					// Update the pricing_id attribute so next save does an UPDATE not INSERT.
					if ( res.data.pricing_id ) {
						input.setAttribute( 'data-pricing-id', res.data.pricing_id );
						var row = input.closest( 'tr' );
						if ( row ) {
							row.setAttribute( 'data-pricing-id', res.data.pricing_id );
							row.classList.remove( 'bk-pricing-table__row--no-price' );
						}
					}
					// Show availability toggle for newly created pricing rows.
					if ( res.data.pricing_id && pricingId === '0' ) {
						refreshAvailabilityToggle( shapeId, res.data.pricing_id );
					}
					toast( res.data.message || 'Price saved.' );
				} else {
					toast( errorMsg( res.data, 'Could not save price.' ), 'error' );
				}
			} ).catch( function () {
				toast( 'Request failed.', 'error' );
			} );
		}, 400 );
	}

	function refreshAvailabilityToggle( shapeId, pricingId ) {
		var row = document.querySelector( 'tr[data-shape-id="' + shapeId + '"]' );
		if ( ! row ) { return; }
		var cell = row.querySelector( 'td:last-child' );
		if ( ! cell || cell.querySelector( '.bk-toggle' ) ) { return; }

		var label = document.createElement( 'label' );
		label.className = 'bk-toggle';
		label.innerHTML =
			'<input type="checkbox" class="bk-availability-toggle"' +
			' data-pricing-id="' + pricingId + '" data-bk-availability checked>' +
			'<span class="bk-toggle__slider"></span>';
		cell.innerHTML = '';
		cell.appendChild( label );
	}

	// -------------------------------------------------------
	// Availability toggle
	//
	// PHP action: bk_toggle_shape_availability (matches CRM class).
	// -------------------------------------------------------

	document.addEventListener( 'change', function ( e ) {
		var toggle = e.target.closest( '[data-bk-availability]' );
		if ( ! toggle ) { return; }

		var pricingId = toggle.getAttribute( 'data-pricing-id' );
		var available = toggle.checked ? '1' : '0';

		ajaxPost( 'bk_toggle_shape_availability', {
			pricing_id: pricingId,
			available:  available,
		} ).then( function ( res ) {
			if ( ! res.success ) {
				toggle.checked = ! toggle.checked; // revert
				toast( errorMsg( res.data, 'Could not update availability.' ), 'error' );
			} else {
				toast( res.data.message || ( toggle.checked ? 'Available.' : 'Unavailable.' ) );
			}
		} ).catch( function () {
			toggle.checked = ! toggle.checked;
			toast( 'Request failed.', 'error' );
		} );
	} );

	// -------------------------------------------------------
	// Profile form — save via AJAX
	// -------------------------------------------------------

	var profileForm = document.getElementById( 'bk-profile-form' );

	if ( profileForm ) {
		profileForm.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			// Sync TinyMCE editors to their underlying textareas before serialising.
			if ( typeof tinyMCE !== 'undefined' ) {
				tinyMCE.triggerSave();
			}

			var btn      = profileForm.querySelector( '[data-bk-profile-save]' );
			var feedback = document.getElementById( 'bk-profile-feedback' );
			btn.disabled = true;

			var fd = new FormData( profileForm );
			// Remove the file input — logo is uploaded separately on file select.
			fd.delete( 'logo' );

			// Unchecked checkboxes are absent from FormData — normalise to '0'.
			var travelToggle = profileForm.querySelector( '[name="travel_fee_enabled"]' );
			if ( travelToggle && ! travelToggle.checked ) {
				fd.set( 'travel_fee_enabled', '0' );
			}

			ajaxFormData( 'bk_save_agent_profile', fd )
				.then( function ( res ) {
					var msg  = res.success
						? ( res.data.message || 'Profile saved.' )
						: errorMsg( res.data, 'Could not save profile.' );
					var type = res.success ? 'success' : 'error';

					if ( feedback ) {
						feedback.textContent = msg;
						feedback.className = 'bk-save-feedback' + ( res.success ? '' : ' bk-save-feedback--error' );
						feedback.classList.remove( 'bk-hidden' );
						setTimeout( function () { feedback.classList.add( 'bk-hidden' ); }, 3000 );
					}
					toast( msg, type );
				} )
				.catch( function () {
					toast( 'Request failed.', 'error' );
				} )
				.finally( function () {
					btn.disabled = false;
				} );
		} );
	}

	// -------------------------------------------------------
	// Logo upload — fires instantly on file select
	// -------------------------------------------------------

	var logoFileInput = document.querySelector( '[data-bk-logo-upload]' );

	if ( logoFileInput ) {
		logoFileInput.addEventListener( 'change', function () {
			if ( ! logoFileInput.files.length ) { return; }

			var fd = new FormData();
			fd.append( 'logo', logoFileInput.files[0] );

			ajaxFormData( 'bk_upload_agent_logo', fd )
				.then( function ( res ) {
					if ( res.success && res.data.url ) {
						var preview = document.getElementById( 'bk-logo-preview' );
						if ( preview ) {
							var img;
							if ( preview.tagName === 'IMG' ) {
								img = preview;
							} else {
								img = document.createElement( 'img' );
								img.id        = 'bk-logo-preview';
								img.className = 'bk-logo-preview';
								img.alt       = 'Company logo';
								preview.parentNode.replaceChild( img, preview );
							}
							img.src = res.data.url;
						}
						toast( res.data.message || 'Logo uploaded.' );
					} else {
						toast( errorMsg( res.data, 'Logo upload failed.' ), 'error' );
					}
				} )
				.catch( function () {
					toast( 'Request failed.', 'error' );
				} );
		} );
	}

	// Remove logo button.
	var removeLogoBtn = document.querySelector( '[data-bk-remove-logo]' );

	if ( removeLogoBtn ) {
		removeLogoBtn.addEventListener( 'click', function () {
			ajaxPost( 'bk_remove_agent_logo', {} )
				.then( function ( res ) {
					if ( res.success ) {
						var preview = document.getElementById( 'bk-logo-preview' );
						if ( preview ) {
							var placeholder = document.createElement( 'div' );
							placeholder.id        = 'bk-logo-preview';
							placeholder.className = 'bk-logo-placeholder';
							placeholder.textContent = 'No logo uploaded';
							preview.parentNode.replaceChild( placeholder, preview );
						}
						removeLogoBtn.remove();
						toast( res.data.message || 'Logo removed.' );
					} else {
						toast( errorMsg( res.data, 'Could not remove logo.' ), 'error' );
					}
				} )
				.catch( function () {
					toast( 'Request failed.', 'error' );
				} );
		} );
	}

	// -------------------------------------------------------
	// Travel fee toggle — show/hide sub-fields
	// -------------------------------------------------------

	var travelToggleEl = document.getElementById( 'bk-travel-fee-enabled' );
	var travelFields   = document.getElementById( 'bk-travel-fee-fields' );

	if ( travelToggleEl && travelFields ) {
		travelToggleEl.addEventListener( 'change', function () {
			travelFields.classList.toggle( 'bk-hidden', ! travelToggleEl.checked );
		} );
	}

	// Fee type select — swap the rate-field label.
	var feeTypeSelect = document.getElementById( 'bk-fee-type' );
	var feeRateLabel  = document.getElementById( 'bk-fee-rate-label' );

	if ( feeTypeSelect && feeRateLabel ) {
		feeTypeSelect.addEventListener( 'change', function () {
			feeRateLabel.textContent = ( feeTypeSelect.value === 'percentage' )
				? 'Percentage (%)'
				: 'Rate per km (R)';
		} );
	}

	// -------------------------------------------------------
	// Scroll-to-lead on page load
	//
	// When the dashboard "View Lead" button links to ?section=leads&lead_id=N,
	// find the matching row and scroll it into view once leads have rendered.
	// -------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		var params = new URLSearchParams( window.location.search );
		var leadId = params.get( 'lead_id' );
		if ( ! leadId ) { return; }

		// Give the DOM a moment in case the leads section renders slightly async.
		setTimeout( function () {
			var leadRow = document.querySelector( '[data-lead-id="' + leadId + '"]' );
			if ( leadRow ) {
				leadRow.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		}, 300 );
	} );

	// -------------------------------------------------------
	// Shape image modal (pricing section)
	// -------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		var modal = document.querySelector( '[data-bk-modal]' );
		if ( ! modal ) { return; }

		var modalImage = modal.querySelector( '[data-bk-modal-image]' );
		var modalTitle = modal.querySelector( '.bk-modal__title' );

		function openModal( url, title ) {
			modalImage.src = url;
			modalImage.alt = title || '';
			if ( modalTitle ) { modalTitle.textContent = title || ''; }
			modal.hidden = false;
			document.body.classList.add( 'bk-modal-open' );
		}

		function closeModal() {
			modal.hidden = true;
			modalImage.src = '';
			document.body.classList.remove( 'bk-modal-open' );
		}

		document.addEventListener( 'click', function ( e ) {
			var trigger = e.target.closest( '[data-bk-shape-image]' );
			if ( trigger ) {
				e.preventDefault();
				openModal( trigger.dataset.imageUrl, trigger.dataset.imageTitle );
				return;
			}
			if ( e.target.closest( '[data-bk-modal-close]' ) ) {
				e.preventDefault();
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && ! modal.hidden ) {
				closeModal();
			}
		} );
	} );

}() );
