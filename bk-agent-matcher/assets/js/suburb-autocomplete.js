/**
 * BK Pools — Suburb Autocomplete
 *
 * Vanilla JS autocomplete for the suburb search field on the BK Pools
 * estimate request form. No jQuery UI dependency.
 *
 * Targets any input with:
 *   data-bk-suburb-autocomplete="true"
 *   OR name="suburb_search"
 *
 * Configuration is injected by PHP via wp_localize_script() as bk_suburb_params:
 *   { ajax_url, nonce, min_chars }
 *
 * @package BK_Agent_Matcher
 * @since   1.0.0
 */

/* global bk_suburb_params */

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Initialise on DOM ready
	// -------------------------------------------------------------------------

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}

	/**
	 * Finds all suburb search inputs on the page and attaches an autocomplete
	 * instance to each one.
	 *
	 * @return {void}
	 */
	function initAll() {
		var inputs = document.querySelectorAll(
			'input[data-bk-suburb-autocomplete="true"], input[name="suburb_search"]'
		);

		inputs.forEach( function ( input ) {
			new SuburbAutocomplete( input );
		} );
	}

	// -------------------------------------------------------------------------
	// SuburbAutocomplete class
	// -------------------------------------------------------------------------

	/**
	 * Creates and manages a suburb autocomplete on a single input element.
	 *
	 * @param {HTMLInputElement} input The search input element.
	 * @constructor
	 */
	function SuburbAutocomplete( input ) {
		this.input        = input;
		this.form         = input.closest( 'form' );
		this.dropdown     = null;
		this.results      = [];
		this.activeIndex  = -1;
		this.debounceTimer = null;
		this.currentTerm  = '';
		this.isSelected   = false;

		// Read target field names from data attributes, with sensible defaults.
		this.targetId       = input.getAttribute( 'data-bk-target-id' )       || 'suburb_id';
		this.targetSuburb   = input.getAttribute( 'data-bk-target-suburb' )   || 'suburb_name';
		this.targetArea     = input.getAttribute( 'data-bk-target-area' )     || 'area_name';
		this.targetProvince = input.getAttribute( 'data-bk-target-province' ) || 'province';

		this._buildDropdown();
		this._bindEvents();
	}

	// -------------------------------------------------------------------------
	// DOM setup
	// -------------------------------------------------------------------------

	/**
	 * Creates and inserts the dropdown container element.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._buildDropdown = function () {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'bk-suburb-autocomplete';

		// Wrap the input.
		this.input.parentNode.insertBefore( wrapper, this.input );
		wrapper.appendChild( this.input );

		// Create dropdown.
		this.dropdown = document.createElement( 'div' );
		this.dropdown.className = 'bk-suburb-autocomplete__dropdown';
		this.dropdown.setAttribute( 'role', 'listbox' );
		this.dropdown.setAttribute( 'aria-label', 'Suburb suggestions' );
		this.dropdown.style.display = 'none';
		wrapper.appendChild( this.dropdown );

		// Accessibility attributes on the input.
		this.input.setAttribute( 'autocomplete', 'off' );
		this.input.setAttribute( 'aria-autocomplete', 'list' );
		this.input.setAttribute( 'aria-expanded', 'false' );
		this.input.setAttribute( 'aria-haspopup', 'listbox' );
	};

	// -------------------------------------------------------------------------
	// Event binding
	// -------------------------------------------------------------------------

	/**
	 * Binds all event listeners to the input and document.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._bindEvents = function () {
		var self = this;

		this.input.addEventListener( 'input', function () {
			self._onInput();
		} );

		this.input.addEventListener( 'keydown', function ( e ) {
			self._onKeydown( e );
		} );

		this.input.addEventListener( 'blur', function () {
			// Delay closing so a click on a result registers first.
			setTimeout( function () {
				self._closeDropdown();
			}, 200 );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! self.input.parentNode.contains( e.target ) ) {
				self._closeDropdown();
			}
		} );
	};

	// -------------------------------------------------------------------------
	// Input handler (debounced)
	// -------------------------------------------------------------------------

	/**
	 * Handles input events with debouncing.
	 * Clears hidden field values if the user types after making a selection.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._onInput = function () {
		var self  = this;
		var value = this.input.value.trim();
		var minChars = ( bk_suburb_params && bk_suburb_params.min_chars )
			? parseInt( bk_suburb_params.min_chars, 10 )
			: 2;

		// If the user edits after selecting, clear the hidden fields.
		if ( this.isSelected ) {
			this._clearHiddenFields();
			this.isSelected = false;
		}

		clearTimeout( this.debounceTimer );

		if ( value.length < minChars ) {
			this._closeDropdown();
			return;
		}

		this.debounceTimer = setTimeout( function () {
			self._search( value );
		}, 300 );
	};

	// -------------------------------------------------------------------------
	// Keyboard navigation
	// -------------------------------------------------------------------------

	/**
	 * Handles keyboard navigation within the dropdown.
	 *
	 * @param {KeyboardEvent} e
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._onKeydown = function ( e ) {
		var items = this.dropdown.querySelectorAll( '.bk-suburb-autocomplete__item' );

		if ( ! items.length ) {
			return;
		}

		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				this.activeIndex = Math.min( this.activeIndex + 1, items.length - 1 );
				this._updateActiveItem( items );
				break;

			case 'ArrowUp':
				e.preventDefault();
				this.activeIndex = Math.max( this.activeIndex - 1, 0 );
				this._updateActiveItem( items );
				break;

			case 'Enter':
				e.preventDefault();
				if ( this.activeIndex >= 0 && items[ this.activeIndex ] ) {
					items[ this.activeIndex ].click();
				}
				break;

			case 'Escape':
				this._closeDropdown();
				break;
		}
	};

	// -------------------------------------------------------------------------
	// AJAX search
	// -------------------------------------------------------------------------

	/**
	 * Fires an AJAX GET request to the suburb search endpoint.
	 *
	 * @param {string} term The search term.
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._search = function ( term ) {
		var self = this;

		if ( ! bk_suburb_params || ! bk_suburb_params.ajax_url ) {
			return;
		}

		this.currentTerm = term;
		this._showLoading();

		var url = bk_suburb_params.ajax_url
			+ '?action=bk_suburb_search'
			+ '&nonce=' + encodeURIComponent( bk_suburb_params.nonce )
			+ '&term='  + encodeURIComponent( term );

		var xhr = new XMLHttpRequest();
		xhr.open( 'GET', url, true );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}
			if ( xhr.status === 200 ) {
				try {
					var data = JSON.parse( xhr.responseText );
					if ( data.success ) {
						self._renderResults( data.data, term );
					} else {
						self._renderNoResults();
					}
				} catch ( err ) {
					self._renderNoResults();
				}
			} else {
				self._renderNoResults();
			}
		};
		xhr.send();
	};

	// -------------------------------------------------------------------------
	// Dropdown rendering
	// -------------------------------------------------------------------------

	/**
	 * Renders search results into the dropdown.
	 *
	 * @param {Array}  results Array of suburb objects from the AJAX response.
	 * @param {string} term    The search term used (for highlighting).
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._renderResults = function ( results, term ) {
		var self = this;

		this.results     = results;
		this.activeIndex = -1;
		this.dropdown.innerHTML = '';

		if ( ! results || ! results.length ) {
			this._renderNoResults();
			return;
		}

		var fragment = document.createDocumentFragment();

		results.forEach( function ( result, index ) {
			var item = document.createElement( 'div' );
			item.className   = 'bk-suburb-autocomplete__item';
			item.setAttribute( 'role', 'option' );
			item.setAttribute( 'aria-selected', 'false' );
			item.setAttribute( 'data-index', String( index ) );
			item.innerHTML   = highlightMatch( result.label, term );

			item.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault(); // Prevent input blur before click registers.
			} );
			item.addEventListener( 'click', function () {
				self._selectResult( result );
			} );

			fragment.appendChild( item );
		} );

		this.dropdown.appendChild( fragment );
		this._openDropdown();
	};

	/**
	 * Renders a "no results" message in the dropdown.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._renderNoResults = function () {
		this.dropdown.innerHTML = '<div class="bk-suburb-autocomplete__no-results">No suburbs found</div>';
		this._openDropdown();
	};

	/**
	 * Shows a loading indicator in the dropdown while the AJAX request is in flight.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._showLoading = function () {
		this.dropdown.innerHTML = '<div class="bk-suburb-autocomplete__loading">Searching\u2026</div>';
		this._openDropdown();
	};

	// -------------------------------------------------------------------------
	// Selection
	// -------------------------------------------------------------------------

	/**
	 * Handles a result selection: populates the input and hidden fields.
	 *
	 * @param {Object} result The selected suburb data object.
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._selectResult = function ( result ) {
		this.input.value = result.label;
		this.isSelected  = true;

		this._setHiddenField( this.targetId,       String( result.id ) );
		this._setHiddenField( this.targetSuburb,   result.suburb );
		this._setHiddenField( this.targetArea,     result.area );
		this._setHiddenField( this.targetProvince, result.province );

		this._closeDropdown();
		this.input.focus();
	};

	// -------------------------------------------------------------------------
	// Hidden field helpers
	// -------------------------------------------------------------------------

	/**
	 * Sets the value of a hidden input within the same form.
	 *
	 * @param {string} name  The input's name attribute.
	 * @param {string} value The value to set.
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._setHiddenField = function ( name, value ) {
		var scope = this.form || document;
		var field = scope.querySelector( 'input[name="' + name + '"]' );
		if ( field ) {
			field.value = value;
		}
	};

	/**
	 * Clears all hidden fields associated with this autocomplete.
	 *
	 * Called when the user edits the input after making a selection.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._clearHiddenFields = function () {
		var fields = [ this.targetId, this.targetSuburb, this.targetArea, this.targetProvince ];
		var self   = this;

		fields.forEach( function ( name ) {
			self._setHiddenField( name, '' );
		} );
	};

	// -------------------------------------------------------------------------
	// Dropdown open / close / active item
	// -------------------------------------------------------------------------

	/**
	 * Opens the dropdown.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._openDropdown = function () {
		this.dropdown.style.display = 'block';
		this.input.setAttribute( 'aria-expanded', 'true' );
	};

	/**
	 * Closes and clears the dropdown.
	 *
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._closeDropdown = function () {
		this.dropdown.style.display = 'none';
		this.dropdown.innerHTML = '';
		this.input.setAttribute( 'aria-expanded', 'false' );
		this.activeIndex = -1;
		this.results     = [];
	};

	/**
	 * Applies the keyboard-active class to the focused item.
	 *
	 * @param {NodeList} items All result items in the dropdown.
	 * @return {void}
	 */
	SuburbAutocomplete.prototype._updateActiveItem = function ( items ) {
		items.forEach( function ( item, i ) {
			if ( i === this.activeIndex ) {
				item.classList.add( 'bk-suburb-autocomplete__item--active' );
				item.setAttribute( 'aria-selected', 'true' );
				item.scrollIntoView( { block: 'nearest' } );
			} else {
				item.classList.remove( 'bk-suburb-autocomplete__item--active' );
				item.setAttribute( 'aria-selected', 'false' );
			}
		}, this );
	};

	// -------------------------------------------------------------------------
	// Utility — highlight matching text
	// -------------------------------------------------------------------------

	/**
	 * Wraps the matching portion of a string in a <strong> tag.
	 *
	 * @param {string} label The full result label.
	 * @param {string} term  The search term to highlight.
	 * @return {string} HTML string with match wrapped in <strong>.
	 */
	function highlightMatch( label, term ) {
		if ( ! term ) {
			return escapeHtml( label );
		}

		var escapedTerm = term.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		var regex       = new RegExp( '(' + escapedTerm + ')', 'gi' );

		return escapeHtml( label ).replace( regex, '<strong>$1</strong>' );
	}

	/**
	 * Escapes HTML special characters in a string.
	 *
	 * @param {string} str The raw string.
	 * @return {string} HTML-escaped string.
	 */
	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

} )();
