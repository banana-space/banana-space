/*!
 * VisualEditor DiffMatchPatch implementation for linear model
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* global diff_match_patch */

/**
 * DiffMatchPatch implementation
 *
 * @class
 * @extends diff_match_patch
 * @constructor
 * @param {ve.dm.HashValueStore} oldStore
 * @param {ve.dm.HashValueStore} newStore
 */
ve.DiffMatchPatch = function VeDiffMatchPatch( oldStore, newStore ) {
	// Parent constructor
	ve.DiffMatchPatch.super.call( this );

	this.store = oldStore.clone();
	this.store.merge( newStore );
};

/* Inheritance */

OO.inheritClass( ve.DiffMatchPatch, diff_match_patch );

/* Static properties */

ve.DiffMatchPatch.static.DIFF_DELETE = -1;
ve.DiffMatchPatch.static.DIFF_INSERT = 1;
ve.DiffMatchPatch.static.DIFF_EQUAL = 0;
ve.DiffMatchPatch.static.DIFF_CHANGE_DELETE = -2;
ve.DiffMatchPatch.static.DIFF_CHANGE_INSERT = 2;

/* Methods */

ve.DiffMatchPatch.prototype.isEqualChar = function ( a, b ) {
	return a === b || ve.dm.ElementLinearData.static.compareElements( a, b, this.store, this.store );
};

ve.DiffMatchPatch.prototype.isEqualString = function ( a, b ) {
	var i, l;

	if ( a === b ) {
		return true;
	}
	if ( a === null || b === null ) {
		return false;
	}
	if ( a.length !== b.length ) {
		return false;
	}

	for ( i = 0, l = a.length; i < l; i++ ) {
		if ( !this.isEqualChar( a[ i ], b[ i ] ) ) {
			return false;
		}
	}
	return true;
};

ve.DiffMatchPatch.prototype.charsToString = function ( chars ) {
	return chars.slice();
};

ve.DiffMatchPatch.prototype.getEmptyString = function () {
	return [];
};

ve.DiffMatchPatch.prototype.getCleanDiff = function ( oldData, newData, options ) {
	var cleanDiff, i, ilen, j,
		store = this.store,
		DIFF_DELETE = this.constructor.static.DIFF_DELETE,
		DIFF_INSERT = this.constructor.static.DIFF_INSERT,
		DIFF_EQUAL = this.constructor.static.DIFF_EQUAL,
		DIFF_CHANGE_DELETE = this.constructor.static.DIFF_CHANGE_DELETE,
		DIFF_CHANGE_INSERT = this.constructor.static.DIFF_CHANGE_INSERT;

	/**
	 * Remove the close elements from the linear data, to force a balanced diff.
	 *
	 * Otherwise, for example, removing the second of two consecutive comment
	 * nodes could result in a diff where [{type: '/comment'}, {type: 'comment'}]
	 * is removed, which is unbalanced.
	 *
	 * Warning: this step assumes that, within a content branch node, an element
	 * is always immediately followed by its close element.
	 *
	 * @param {Array} data Linear data
	 * @return {Array} Linear data without close elements
	 */
	function removeCloseElements( data ) {
		var i, ilen;
		for ( i = 0, ilen = data.length; i < ilen; i++ ) {
			if ( data[ i ].type && data[ i ].type[ 0 ] === '/' ) {
				data.splice( i, 1 );
				ilen--;
				i--;
			}
		}
		return data;
	}

	/**
	 * Get the index of the first or last wordbreak in a data array
	 *
	 * @param {Array} data Linear data
	 * @param {boolean} reversed Get the index of the last wordbreak
	 * @return {number|null} Index of the first or last wordbreak, or null if no
	 *  wordbreak was found
	 */
	function findWordbreaks( data, reversed ) {
		var offset,
			dataString = new ve.dm.DataString( data );

		offset = unicodeJS.wordbreak.moveBreakOffset(
			reversed ? -1 : 1,
			dataString,
			reversed ? data.length : 0
		);

		if ( ( reversed && offset === 0 ) || ( !reversed && offset === data.length ) ) {
			return null;
		} else {
			return offset;
		}
	}

	/**
	 * Determine whether there is a wordbreak at an offset
	 *
	 * @param {Array} data Linear data
	 * @param {number} offset
	 * @return {boolean} There is a wordbreak at the offset
	 */
	function isBreak( data, offset ) {
		return !!( unicodeJS.wordbreak.isBreak( new ve.dm.DataString( data ), offset ) );
	}

	/**
	 * The perfect diff is not always human-friendly, so clean it up.
	 * Make sure retained content spans whole words (no wordbreaks),
	 * and "de-stripe" any sequences of alternating removes and inserts
	 * (with no retains) to look like one continuous removal and one continuous
	 * insert.
	 *
	 * Additionally clean up mistakes made by the linear differ, such as removing
	 * and inserting identical content (insetead of retaining it) and removing,
	 * inserting or retaining an empty content array.
	 *
	 * @param {Array} diff Linear diff, as arrays of inserted, removed and retained
	 * content
	 * @return {Array} A human-friendlier linear diff
	 */
	function getCleanDiff( diff ) {
		var i, ilen, j, action, data, firstWordbreak, lastWordbreak,
			start, end, aItem, bItem, aAction, bAction, aData, bData,
			aAnnotations, bAnnotations, annotationChanges, attributeChanges,
			notNextStartsWithWordbreak, notPreviousEndsWithWordBreak,
			previousData = null,
			previousAction = null,
			cleanDiff = [],
			remove = [],
			insert = [];

		function equalUnannotated( other, element, index ) {
			return ve.dm.ElementLinearData.static.compareElementsUnannotated( element, other[ index ] );
		}

		function equalElements( other, element, index ) {
			// Non-elements can't be equal to other elements
			if ( !element.type || !other[ index ].type ) {
				return false;
			}
			if ( ve.dm.LinearData.static.isOpenElementData( element ) ) {
				return ve.dm.modelRegistry.lookup( element.type ).static.isDiffComparable( element, other[ index ], store, store );
			} else {
				// Ignore close elements
				return true;
			}
		}

		function isWhitespace( element ) {
			var data = Array.isArray( element ) ? element[ 0 ] : element;
			return typeof data === 'string' && !!data.match( /\s/ );
		}

		// Where the same data is removed and inserted, replace it with a retain
		for ( i = 0; i < diff.length; i++ ) {
			action = diff[ i ][ 0 ];
			data = diff[ i ][ 1 ];
			// Should improve on JSON.stringify
			if ( ( action > 0 || previousAction > 0 ) && action + previousAction === 0 && JSON.stringify( data ) === JSON.stringify( previousData ) ) {
				diff.splice( i - 1, 2, [ DIFF_EQUAL, data ] );
				i++;
			}
			previousAction = action;
			previousData = data;
		}
		previousData = null;
		previousAction = null;

		// Join any consecutive actions that are the same
		for ( i = 0; i < diff.length; i++ ) {
			action = diff[ i ][ 0 ];
			if ( action === previousAction ) {
				diff[ i - 1 ][ 1 ] = diff[ i - 1 ][ 1 ].concat( diff[ i ][ 1 ] );
				diff.splice( i, 1 );
				i--;
			} else if ( diff[ i ][ 1 ].length === 0 ) {
				diff.splice( i, 1 );
				i--;
				continue;
			}
			previousAction = action;
		}

		// Convert any retains that do not end and start with spaces into remove-
		// inserts
		for ( i = 0; i < diff.length; i++ ) {
			action = diff[ i ][ 0 ];
			data = diff[ i ][ 1 ];
			if ( action === DIFF_EQUAL ) {

				start = [];
				end = [];
				firstWordbreak = findWordbreaks( data, false );
				lastWordbreak = firstWordbreak === null ? null : findWordbreaks( data, true );
				notNextStartsWithWordbreak = i !== diff.length - 1 && !isBreak( data.concat( diff[ i + 1 ][ 1 ] ), data.length );
				notPreviousEndsWithWordBreak = i !== 0 && !isBreak( previousData.concat( data ), previousData.length );

				if ( firstWordbreak === null && ( notNextStartsWithWordbreak || notPreviousEndsWithWordBreak ) ) {
					// If there was no wordbreak, and there are no wordbreaks either side,
					// the retain should be replaced with a remove-insert
					diff.splice( i, 1, [ DIFF_DELETE, data ], [ DIFF_INSERT, data ] );
					i++;
				} else {
					if ( notNextStartsWithWordbreak ) {
						// Unless the next item starts with a wordbreak, replace the portion
						// after the last wordbreak.
						end = data.splice( lastWordbreak );
					}
					if ( notPreviousEndsWithWordBreak ) {
						// Unless the previous item ends with a word break,replace the portion
						// before the first wordbreak.
						start = data.splice( 0, firstWordbreak );
					} else {
						// Skip over close tags to ensure a balanced remove/insert
						// Word break logic should ensure that there aren't unbalanced
						// tags on the left of the remove/insert
						j = 0;
						while ( ve.dm.LinearData.static.isCloseElementData( data[ j ] ) ) {
							j++;
						}
						start = data.splice( 0, j );
					}

					// At this point the only portion we want to retain is what's left of
					// data (if anything; if firstWordbreak === lastWordbreak !== null, then
					// data has been spliced away completely).
					if ( start.length > 0 ) {
						diff.splice( i, 0, [ DIFF_DELETE, start ], [ DIFF_INSERT, start ] );
						i += 2;
					}
					if ( end.length > 0 ) {
						diff.splice( i + 1, 0, [ DIFF_DELETE, end ], [ DIFF_INSERT, end ] );
						i += 2;
					}

				}

			}
			previousData = data;
		}

		// In a sequence of -remove-insert-remove-insert- make the removes into a
		// single action and the inserts into a single action
		for ( i = 0, ilen = diff.length; i < ilen; i++ ) {
			action = diff[ i ][ 0 ];
			data = diff[ i ][ 1 ];
			if ( action === DIFF_DELETE ) {
				remove = remove.concat( data );
			} else if ( action === DIFF_INSERT ) {
				insert = insert.concat( data );
			} else if ( action === DIFF_EQUAL && data.length > 0 ) {
				if ( data.every( isWhitespace ) ) {
					remove = remove.concat( data );
					insert = insert.concat( data );
				} else {
					if ( remove.length > 0 ) {
						cleanDiff.push( [ DIFF_DELETE, remove ] );
					}
					remove = [];
					if ( insert.length > 0 ) {
						cleanDiff.push( [ DIFF_INSERT, insert ] );
					}
					insert = [];
					cleanDiff.push( diff[ i ] );
				}
			}
		}

		if ( remove.length > 0 ) {
			cleanDiff.push( [ DIFF_DELETE, remove ] );
		}
		if ( insert.length > 0 ) {
			cleanDiff.push( [ DIFF_INSERT, insert ] );
		}

		// Now go over any consecutive remove-inserts (also insert-removes?) and
		// if they have the same character data, or are modified content nodes,
		// make them changes instead
		for ( i = 0, ilen = cleanDiff.length - 1; i < ilen; i++ ) {
			aItem = cleanDiff[ i ];
			bItem = cleanDiff[ i + 1 ];
			aData = aItem[ 1 ];
			bData = bItem[ 1 ];
			aAction = aItem[ 0 ];
			bAction = bItem[ 0 ];
			// If they have the same length content and they are a consecutive
			// remove and insert, and they have the same content then mark the
			// old one as a change-remove (-2) and the new one as a change-insert
			// (2)
			if (
				aData.length === bData.length &&
				( ( aAction === DIFF_DELETE && bAction === DIFF_INSERT ) || ( aAction === DIFF_INSERT && bAction === DIFF_DELETE ) )
			) {
				if ( aData.every( equalUnannotated.bind( this, bData ) ) ) {
					aAnnotations = new ve.dm.ElementLinearData( store, aData ).getAnnotationsFromRange( new ve.Range( 0, aData.length ), true );
					bAnnotations = new ve.dm.ElementLinearData( store, bData ).getAnnotationsFromRange( new ve.Range( 0, bData.length ), true );

					annotationChanges = [];
					bAnnotations.get().forEach( function ( b ) { // eslint-disable-line no-loop-func
						var sameName = aAnnotations.getAnnotationsByName( b.name );
						if ( sameName.getLength() && !aAnnotations.containsComparable( b ) ) {
							// Annotations which have the same type, but are non-comparable, e.g. link with a different href
							annotationChanges.push( { oldAnnotation: sameName.get( 0 ), newAnnotation: b } );
						}
					} );

					if ( annotationChanges.length ) {
						cleanDiff[ i + 1 ].annotationChanges = annotationChanges;
						cleanDiff[ i ][ 0 ] = aAction === DIFF_DELETE ? DIFF_CHANGE_DELETE : DIFF_CHANGE_INSERT;
						cleanDiff[ i + 1 ][ 0 ] = bAction === DIFF_DELETE ? DIFF_CHANGE_DELETE : DIFF_CHANGE_INSERT;
					}
				}
				if ( aData.every( equalElements.bind( this, bData ) ) ) {
					attributeChanges = [];
					// eslint-disable-next-line no-loop-func
					bData.forEach( function ( element, i ) {
						if ( ve.dm.LinearData.static.isOpenElementData( element ) ) {
							attributeChanges.push( { oldAttributes: aData[ i ].attributes, newAttributes: element.attributes, index: i } );
						}
					} );
					if ( attributeChanges.length ) {
						cleanDiff[ i + 1 ].attributeChanges = attributeChanges;
						cleanDiff[ i ][ 0 ] = aAction === DIFF_DELETE ? DIFF_CHANGE_DELETE : DIFF_CHANGE_INSERT;
						cleanDiff[ i + 1 ][ 0 ] = bAction === DIFF_DELETE ? DIFF_CHANGE_DELETE : DIFF_CHANGE_INSERT;
					}
				}

				// No need to check bItem against the following item
				i += 1;
			}
		}

		return cleanDiff;
	}

	// Remove the close elements
	oldData = removeCloseElements( oldData );
	newData = removeCloseElements( newData );

	// Get the diff
	cleanDiff = getCleanDiff( this.diff_main( oldData, newData, options ) );

	// Re-insert the close elements
	for ( i = 0, ilen = cleanDiff.length; i < ilen; i++ ) {
		for ( j = 0; j < cleanDiff[ i ][ 1 ].length; j++ ) {
			if ( cleanDiff[ i ][ 1 ][ j ].type ) {
				cleanDiff[ i ][ 1 ].splice( j + 1, 0, {
					type: '/' + cleanDiff[ i ][ 1 ][ j ].type
				} );
				j++;
			}
		}
	}

	cleanDiff.timedOut = this.lastDiffTimedOut;

	return cleanDiff;
};
