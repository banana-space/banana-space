/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

( function () {
	var EP,
		cachedRTL;

	/**
	 * Represents a UI element.
	 *
	 * @class mw.mmv.ui.Element
	 * @abstract
	 * @constructor
	 * @param {jQuery} $container
	 */
	function Element( $container ) {
		OO.EventEmitter.call( this );

		/** @property {jQuery} $container The element that contains the UI element. */
		this.$container = $container;

		/** @property {Object.<string, string[]>} eventsRegistered Events that this element has registered with the DOM. */
		this.eventsRegistered = {};

		/**
		 * @property {Object.<string, jQuery>} $inlineStyles a list of `<style>` elements in the head
		 *  which we use to manipulate pseudo-classes and pseudo-elements.
		 */
		this.$inlineStyles = [];

		/**
		 * Stores named timeouts. See setTimer().
		 *
		 * @private
		 * @property {Object.<string, {timeout: Object, handler: function(), delay: number}>}
		 */
		this.timers = {};
	}

	OO.mixinClass( Element, OO.EventEmitter );

	EP = Element.prototype;

	/**
	 * Checks whether the document is RTL. Assumes it doesn't change.
	 *
	 * @return {boolean}
	 */
	EP.isRTL = function () {
		if ( cachedRTL === undefined ) {
			cachedRTL = $( document.body ).hasClass( 'rtl' );
		}

		return cachedRTL;
	};

	/**
	 * Sets the data for the element.
	 *
	 * @abstract
	 */
	EP.set = function () {};

	/**
	 * Empties the element.
	 *
	 * @abstract
	 */
	EP.empty = function () {};

	/**
	 * Registers listeners.
	 *
	 * @abstract
	 */
	EP.attach = function () {};

	/**
	 * Clears listeners.
	 *
	 * @abstract
	 */
	EP.unattach = function () {
		this.clearEvents();
	};

	/**
	 * Add event handler in a way that will be auto-cleared on lightbox close
	 *
	 * TODO: Unit tests
	 *
	 * @param {string} name Name of event, like 'keydown'
	 * @param {Function} handler Callback for the event
	 */
	EP.handleEvent = function ( name, handler ) {
		if ( this.eventsRegistered[ name ] === undefined ) {
			this.eventsRegistered[ name ] = [];
		}
		this.eventsRegistered[ name ].push( handler );
		$( document ).on( name, handler );
	};

	/**
	 * Remove all events that have been registered on this element.
	 *
	 * TODO: Unit tests
	 */
	EP.clearEvents = function () {
		var i, handlers, thisevent,
			events = Object.keys( this.eventsRegistered );

		for ( i = 0; i < events.length; i++ ) {
			thisevent = events[ i ];
			handlers = this.eventsRegistered[ thisevent ];
			while ( handlers.length > 0 ) {
				$( document ).off( thisevent, handlers.pop() );
			}
		}
	};

	/**
	 * Manipulate CSS directly. This is needed to set styles for pseudo-classes and pseudo-elements.
	 *
	 * @param {string} key some name to identify the style
	 * @param {string|null} style a CSS snippet (set to null to delete the given style)
	 */
	EP.setInlineStyle = function ( key, style ) {

		if ( !this.$inlineStyles ) {
			this.$inlineStyles = [];
		}

		if ( !this.$inlineStyles[ key ] ) {
			if ( !style ) {
				return;
			}

			this.$inlineStyles[ key ] = $( '<style>' ).attr( 'type', 'text/css' ).appendTo( 'head' );
		}

		this.$inlineStyles[ key ].html( style || '' );
	};

	/**
	 * Sets a timer. This is a shortcut to using the native setTimout and then storing
	 * the reference, with some small differences for convenience:
	 * - setting the same timer again clears the old one
	 * - callbacks have the element as their context
	 * Timers are local to the element.
	 * See also clearTimer() and resetTimer().
	 *
	 * @param {string} name
	 * @param {function()} callback
	 * @param {number} delay delay in milliseconds
	 */
	EP.setTimer = function ( name, callback, delay ) {
		var element = this;

		this.clearTimer( name );
		this.timers[ name ] = {
			timeout: null,
			handler: callback,
			delay: delay
		};
		this.timers[ name ].timeout = setTimeout( function () {
			delete element.timers[ name ];
			callback.call( element );
		}, delay );
	};

	/**
	 * Clears a timer. See setTimer().
	 *
	 * @param {string} name
	 */
	EP.clearTimer = function ( name ) {
		if ( name in this.timers ) {
			clearTimeout( this.timers[ name ].timeout );
			delete this.timers[ name ];
		}
	};

	/**
	 * Resets a timer, so that its delay will be relative to when resetTimer() was called, not when
	 * the timer was created. Optionally changes the delay as well.
	 * Resetting a timer that does not exist or has already fired has no effect.
	 * See setTimer().
	 *
	 * @param {string} name
	 * @param {number} [delay] delay in milliseconds
	 */
	EP.resetTimer = function ( name, delay ) {
		if ( name in this.timers ) {
			if ( delay === undefined ) {
				delay = this.timers[ name ].delay;
			}
			this.setTimer( name, this.timers[ name ].handler, delay );
		}
	};

	/**
	 * Flips E (east) and W (west) directions in RTL documents.
	 *
	 * @param {string} keyword a keyword where the first 'e' or 'w' character means a direction (such as a
	 *  tipsy gravity parameter)
	 * @return {string}
	 */
	EP.correctEW = function ( keyword ) {
		if ( this.isRTL() ) {
			keyword = keyword.replace( /[ew]/i, function ( dir ) {
				if ( dir === 'e' ) {
					return 'w';
				} else if ( dir === 'E' ) {
					return 'W';
				} else if ( dir === 'w' ) {
					return 'e';
				} else if ( dir === 'W' ) {
					return 'E';
				}
			} );
		}
		return keyword;
	};

	mw.mmv.ui = {};
	mw.mmv.ui.reuse = {};
	mw.mmv.ui.Element = Element;
}() );
