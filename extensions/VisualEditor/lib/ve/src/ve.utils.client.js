/*!
 * VisualEditor client (browser) specific utilities that interact with a rendered DOM.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * @method
 * @inheritdoc OO.ui.Element#scrollIntoView
 */
ve.scrollIntoView = OO.ui.Element.static.scrollIntoView.bind( OO.ui.Element.static );

/**
 * Select the contents of an element
 *
 * @param {HTMLElement} element Element
 */
ve.selectElement = function ( element ) {
	var win = OO.ui.Element.static.getWindow( element ),
		nativeRange = win.document.createRange(),
		nativeSelection = win.getSelection();
	nativeRange.setStart( element, 0 );
	nativeRange.setEnd( element, element.childNodes.length );
	nativeSelection.removeAllRanges();
	nativeSelection.addRange( nativeRange );
};

/**
 * Feature detect if the browser supports extending selections
 *
 * Should work everywhere except IE
 *
 * @private
 * @property {boolean}
 */
ve.supportsSelectionExtend = !!window.getSelection().extend;

/**
 * Translate rect by some fixed vector and return a new offset object
 *
 * @param {Object} rect Offset object containing all or any of top, left, bottom, right, width & height
 * @param {number} x Horizontal translation
 * @param {number} y Vertical translation
 * @return {Object} Translated rect
 */
ve.translateRect = function ( rect, x, y ) {
	var translatedRect = {};
	if ( rect.top !== undefined ) {
		translatedRect.top = rect.top + y;
	}
	if ( rect.bottom !== undefined ) {
		translatedRect.bottom = rect.bottom + y;
	}
	if ( rect.left !== undefined ) {
		translatedRect.left = rect.left + x;
	}
	if ( rect.right !== undefined ) {
		translatedRect.right = rect.right + x;
	}
	if ( rect.width !== undefined ) {
		translatedRect.width = rect.width;
	}
	if ( rect.height !== undefined ) {
		translatedRect.height = rect.height;
	}
	return translatedRect;
};

/**
 * Get the start and end rectangles (in a text flow sense) from a list of rectangles
 *
 * The start rectangle is the top-most, and the end rectangle is the bottom-most.
 *
 * @param {Array} rects Full list of rectangles
 * @return {Object|null} Object containing two rectangles: start and end, or null if there are no rectangles
 */
ve.getStartAndEndRects = function ( rects ) {
	var i, l, startRect, endRect;
	if ( !rects || !rects.length ) {
		return null;
	}
	for ( i = 0, l = rects.length; i < l; i++ ) {
		if ( !startRect || rects[ i ].top < startRect.top ) {
			// Use ve.extendObject as ve.copy copies non-plain objects by reference
			startRect = ve.extendObject( {}, rects[ i ] );
		} else if ( rects[ i ].top === startRect.top ) {
			// Merge rects with the same top coordinate
			startRect.left = Math.min( startRect.left, rects[ i ].left );
			startRect.right = Math.max( startRect.right, rects[ i ].right );
			startRect.width = startRect.right - startRect.left;
		}
		if ( !endRect || rects[ i ].bottom > endRect.bottom ) {
			// Use ve.extendObject as ve.copy copies non-plain objects by reference
			endRect = ve.extendObject( {}, rects[ i ] );
		} else if ( rects[ i ].bottom === endRect.bottom ) {
			// Merge rects with the same bottom coordinate
			endRect.left = Math.min( endRect.left, rects[ i ].left );
			endRect.right = Math.max( endRect.right, rects[ i ].right );
			endRect.width = startRect.right - startRect.left;
		}
	}
	return {
		start: startRect,
		end: endRect
	};
};

/**
 * Get the client platform string from the browser.
 *
 * FIXME T126036: This is a wrapper for calling getSystemPlatform() on the current
 * platform except that if the platform hasn't been constructed yet, it falls back
 * to using the base class implementation in {ve.init.Platform}. A proper solution
 * would be not to need this information before the platform is constructed.
 *
 * @see ve.init.Platform#getSystemPlatform
 * @return {string} Client platform string
 */
ve.getSystemPlatform = function () {
	return ( ve.init.platform && ve.init.platform.constructor || ve.init.Platform ).static.getSystemPlatform();
};

/**
 * Check whether a jQuery event represents a plain left click, without any modifiers
 *
 * @param {jQuery.Event} e The jQuery event object
 * @return {boolean} Whether it was an unmodified left click
 */
ve.isUnmodifiedLeftClick = function ( e ) {
	return e && e.which && e.which === OO.ui.MouseButtons.LEFT && !( e.shiftKey || e.altKey || e.ctrlKey || e.metaKey );
};

/**
 * Are multiple formats for clipboardData items supported?
 *
 * If you want to use unknown formats, an additional check for whether we're
 * on MS Edge needs to be made, as that only supports standard plain text / HTML.
 *
 * @param {jQuery.Event} e A jQuery event object for a copy/paste event
 * @param {boolean} [customTypes] Check whether non-standard formats are supported
 * @return {boolean} Whether multiple clipboardData item formats are supported
 */
ve.isClipboardDataFormatsSupported = function ( e, customTypes ) {
	var profile, clipboardData,
		cacheKey = customTypes ? 'cachedCustom' : 'cached';

	if ( ve.isClipboardDataFormatsSupported[ cacheKey ] === undefined ) {
		profile = $.client.profile();
		clipboardData = e.originalEvent.clipboardData || e.originalEvent.dataTransfer;
		ve.isClipboardDataFormatsSupported[ cacheKey ] = !!(
			clipboardData &&
			( !customTypes || profile.name !== 'edge' ) && (
				// Support: Chrome
				clipboardData.items ||
				// Support: Firefox >= 48
				// (but not Firefox Android, which has name='android' and doesn't support this feature)
				( profile.name === 'firefox' && profile.versionNumber >= 48 )
			)
		);
	}

	return ve.isClipboardDataFormatsSupported[ cacheKey ];
};

/**
 * Workaround for catastrophic Firefox bug (T209646)
 *
 * anchorNode and focusNode return unusable 'Restricted' object
 * when focus is in a number input:
 * https://bugzilla.mozilla.org/show_bug.cgi?id=1495482
 *
 * @param {Selection} selection Native selection
 */
ve.fixSelectionNodes = function ( selection ) {
	var profile = $.client.profile();

	if ( profile.layout !== 'gecko' ) {
		return;
	}

	function fixNodeProperty( prop ) {
		Object.defineProperty( selection, prop, {
			get: function () {
				var node = Object.getOwnPropertyDescriptor( Selection.prototype, prop ).get.call( this );
				try {
					// Try to read a property out of node if it not null
					// Throws an exception in FF
					// eslint-disable-next-line no-unused-expressions
					node && node.prop;
				} catch ( e ) {
					// When an input is focused, the selection becomes the input itself,
					// so the anchor/focusNode is the input's parent.
					// Fall back to null if that doesn't exist.
					ve.log( 'Worked around Firefox bug with getSelection().anchorNode/focusNode. See https://phabricator.wikimedia.org/T209646.' );
					return ( document.activeElement && document.activeElement.parentNode ) || null;
				}
				return node;
			}
		} );
	}

	if ( !Object.getOwnPropertyDescriptor( selection, 'anchorNode' ) ) {
		fixNodeProperty( 'anchorNode' );
		fixNodeProperty( 'focusNode' );
	}
};

/**
 * Register a passive event listener
 *
 * @param {HTMLElement} elem Element to register event on
 * @param {string} event Name of event to register
 * @param {Function} handler Event handler (which cannot call event.preventDefault)
 */
ve.addPassiveEventListener = function ( elem, event, handler ) {
	elem.addEventListener( event, handler, ve.isPassiveEventsSupported() ? { passive: true } : false );
};

/**
 * Remove a passive event listener
 *
 * @param {HTMLElement} elem Element to remove event from
 * @param {string} event Name of event to remove
 * @param {Function} handler Event handler to remove
 */
ve.removePassiveEventListener = function ( elem, event, handler ) {
	elem.removeEventListener( event, handler, ve.isPassiveEventsSupported() ? { passive: true } : false );
};

/**
 * Test whether passive event listeners are supported
 *
 * @return {boolean} Whether passive event listeners are supported
 */
ve.isPassiveEventsSupported = function () {
	var opts;
	if ( ve.isPassiveEventsSupported.supported === undefined ) {
		try {
			opts = Object.defineProperty( {}, 'passive', {
				// eslint-disable-next-line getter-return
				get: function () {
					ve.isPassiveEventsSupported.supported = true;
				}
			} );
			window.addEventListener( 'testPassive', null, opts );
			window.removeEventListener( 'testPassive', null, opts );
		} catch ( e ) {}
		if ( ve.isPassiveEventsSupported.supported !== true ) {
			ve.isPassiveEventsSupported.supported = false;
		}
	}
	return ve.isPassiveEventsSupported.supported;
};
