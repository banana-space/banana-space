/*!
 * VisualEditor plugin for QUnit.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* global difflib,diffview */

( function ( QUnit ) {
	/**
	 * Plugin for QUnit.
	 *
	 * @class ve.QUnit
	 * @extends QUnit
	 */

	/**
	 * Builds a summary of a node tree.
	 *
	 * Generated summaries contain node types, lengths, outer lengths, attributes and summaries for
	 * each child recursively. It's simple and fast to use deepEqual on this.
	 *
	 * @private
	 * @param {ve.Node} node Node tree to summarize
	 * @param {boolean} [shallow] Do not summarize each child recursively
	 * @return {Object} Summary of node tree
	 */
	function getNodeTreeSummary( node, shallow ) {
		var i,
			summary = {
				getType: node.getType(),
				getLength: node.getLength(),
				getOuterLength: node.getOuterLength(),
				element: node.element
			},
			numChildren;

		if ( node.children !== undefined ) {
			numChildren = node.children.length;
			if ( !shallow ) {
				summary.children = [];
				for ( i = 0; i < numChildren; i++ ) {
					summary.children.push( getNodeTreeSummary( node.children[ i ] ) );
				}
			}
		}
		return summary;
	}

	/**
	 * Builds a summary of a node selection.
	 *
	 * Generated summaries contain length of results as well as node summaries, ranges, indexes, indexes
	 * within parent and node ranges for each result. It's simple and fast to use deepEqual on this.
	 *
	 * @private
	 * @param {Object[]} selection Selection to summarize
	 * @return {Object} Summary of selection
	 */
	function getNodeSelectionSummary( selection ) {
		var i,
			summary = {
				length: selection.length
			};

		if ( selection.length ) {
			summary.results = [];
			for ( i = 0; i < selection.length; i++ ) {
				summary.results.push( {
					node: getNodeTreeSummary( selection[ i ].node, true ),
					range: selection[ i ].range,
					index: selection[ i ].index,
					indexInNode: selection[ i ].indexInNode,
					nodeRange: selection[ i ].nodeRange,
					nodeOuterRange: selection[ i ].nodeOuterRange,
					parentOuterRange: selection[ i ].parentOuterRange
				} );
			}
		}
		return summary;
	}

	/**
	 * Callback for ve#copy to convert nodes to a comparable summary.
	 *
	 * @private
	 * @param {ve.dm.Node|Object} value Value in the object/array
	 * @return {Object} Node summary if value is a node, otherwise just the value
	 */
	function convertNodes( value ) {
		return value instanceof ve.dm.Node || value instanceof ve.ce.Node ?
			getNodeTreeSummary( value ) :
			value;
	}

	/**
	 * Undo what QUnit's escapeText does.
	 *
	 * @ignore
	 * @param {string} s
	 * @return {string}
	 */
	function unescapeText( s ) {
		return s.replace( /&(#039|quot|lt|gt|amp);/g, function ( match, seq ) {
			switch ( seq ) {
				case '#039':
					return '\'';
				case 'quot':
					return '"';
				case 'lt':
					return '<';
				case 'gt':
					return '>';
				case 'amp':
					return '&';
			}
		} );
	}

	/**
	 * Assertion helpers for VisualEditor test suite.
	 *
	 * @class ve.QUnit.assert
	 */

	/**
	 * Assert that summaries of two node trees are equal.
	 *
	 * @static
	 * @param {ve.Node} actual
	 * @param {ve.Node} expected
	 * @param {boolean} shallow
	 * @param {string} message
	 */
	QUnit.assert.equalNodeTree = function ( actual, expected, shallow, message ) {
		var actualSummary, expectedSummary;
		if ( typeof shallow === 'string' && arguments.length === 3 ) {
			message = shallow;
			shallow = undefined;
		}
		actualSummary = getNodeTreeSummary( actual, shallow );
		expectedSummary = getNodeTreeSummary( expected, shallow );
		this.pushResult( {
			result: QUnit.equiv( actualSummary, expectedSummary ),
			actual: actualSummary,
			expected: expectedSummary,
			message: message
		} );
	};

	/**
	 * @static
	 * @param {Object[]} actual
	 * @param {Object[]} expected
	 * @param {string} message
	 */
	QUnit.assert.equalNodeSelection = function ( actual, expected, message ) {
		var i,
			actualSummary = getNodeSelectionSummary( actual ),
			expectedSummary = getNodeSelectionSummary( expected );

		for ( i = 0; i < actual.length; i++ ) {
			if ( expected[ i ] && expected[ i ].node !== actual[ i ].node ) {
				this.pushResult( {
					result: false,
					actual: actualSummary,
					expected: expectedSummary,
					message: message + ' (reference equality for selection[' + i + '].node)'
				} );
				return;
			}
		}
		this.pushResult( {
			result: QUnit.equiv( actualSummary, expectedSummary ),
			actual: actualSummary,
			expected: expectedSummary,
			message: message
		} );
	};

	/**
	 * @static
	 * @param {HTMLElement} actual
	 * @param {HTMLElement} expected
	 * @param {string} message
	 */
	QUnit.assert.equalDomElement = function ( actual, expected, message ) {
		var actualSummary = ve.getDomElementSummary( actual ),
			expectedSummary = ve.getDomElementSummary( expected ),
			actualSummaryHtml = ve.getDomElementSummary( actual, true ),
			expectedSummaryHtml = ve.getDomElementSummary( expected, true );

		this.pushResult( {
			result: QUnit.equiv( actualSummary, expectedSummary ),
			actual: actualSummaryHtml,
			expected: expectedSummaryHtml,
			message: message
		} );
	};

	/**
	 * @static
	 * @param {HTMLElement} actual
	 * @param {HTMLElement} expected
	 * @param {string} message
	 */
	QUnit.assert.notEqualDomElement = function ( actual, expected, message ) {
		var actualSummary = ve.getDomElementSummary( actual ),
			expectedSummary = ve.getDomElementSummary( expected ),
			actualSummaryHtml = ve.getDomElementSummary( actual, true ),
			expectedSummaryHtml = ve.getDomElementSummary( expected, true );

		this.pushResult( {
			result: !QUnit.equiv( actualSummary, expectedSummary ),
			actual: actualSummaryHtml,
			expected: 'Not: ' + expectedSummaryHtml,
			message: message
		} );
	};

	QUnit.assert.equalLinearData = function ( actual, expected, message ) {
		function removeOriginalDomElements( val ) {
			if ( val && val.type ) {
				ve.deleteProp( val, 'originalDomElementsHash' );
				ve.deleteProp( val, 'originalDomElements' );
				ve.deleteProp( val, 'internal', 'changesSinceLoad' );
				ve.deleteProp( val, 'internal', 'metaItems' );
			}
		}

		actual = ve.copy( actual );
		expected = ve.copy( expected );
		actual = ve.copy( actual, null, removeOriginalDomElements );
		expected = ve.copy( expected, null, removeOriginalDomElements );

		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	QUnit.assert.equalLinearDataWithDom = function ( store, actual, expected, message ) {
		function addOriginalDomElements( val ) {
			if ( val && val.originalDomElementsHash !== undefined ) {
				val.originalDomElements = store.value( val.originalDomElementsHash );
				delete val.originalDomElementsHash;
			}
		}

		actual = ve.copy( actual );
		expected = ve.copy( expected );
		actual = ve.copy( actual, ve.convertDomElements, addOriginalDomElements );
		expected = ve.copy( expected, ve.convertDomElements, addOriginalDomElements );

		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	/**
	 * Assert that two objects which may contain DOM elements are equal.
	 *
	 * @static
	 * @param {Object} actual
	 * @param {Object} expected
	 * @param {string} message
	 */
	QUnit.assert.deepEqualWithDomElements = function ( actual, expected, message ) {
		// Recursively copy objects or arrays, converting any dom elements found to comparable summaries
		actual = ve.copy( actual, ve.convertDomElements );
		expected = ve.copy( expected, ve.convertDomElements );

		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	/**
	 * Assert that two objects which may contain node trees are equal.
	 *
	 * @static
	 * @param {Object} actual
	 * @param {Object} expected
	 * @param {string} message
	 */
	QUnit.assert.deepEqualWithNodeTree = function ( actual, expected, message ) {
		// Recursively copy objects or arrays, converting any dom elements found to comparable summaries
		actual = ve.copy( actual, convertNodes );
		expected = ve.copy( expected, convertNodes );

		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	QUnit.assert.equalRange = function ( actual, expected, message ) {
		actual = actual && {
			from: actual.from,
			to: actual.to
		};
		expected = expected && {
			from: expected.from,
			to: expected.to
		};
		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	QUnit.assert.equalHash = function ( actual, expected, message ) {
		actual = JSON.parse( JSON.stringify( actual ) );
		expected = JSON.parse( JSON.stringify( expected ) );
		this.pushResult( {
			result: QUnit.equiv( actual, expected ),
			actual: actual,
			expected: expected,
			message: message
		} );
	};

	QUnit.diff = function ( o, n ) {
		// o and n are partially HTML escaped by QUnit. As difflib does
		// its own escaping we should unescape them first.
		var oLines = difflib.stringAsLines( unescapeText( o ) ),
			nLines = difflib.stringAsLines( unescapeText( n ) ),
			sm = new difflib.SequenceMatcher( oLines, nLines ),
			opcodes = sm.get_opcodes(),
			$div = $( '<div>' );

		$div.append( diffview.buildView( {
			baseTextLines: oLines,
			newTextLines: nLines,
			opcodes: opcodes,
			baseTextName: 'Expected',
			newTextName: 'Result',
			contextSize: 10,
			viewType: 0
		} ) );

		return $div.html();
	};
}( QUnit ) );
