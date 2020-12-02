/**
 * Plugin that highlights matched word partials in a given element.
 * TODO: Add a function for restoring the previous text.
 * TODO: Accept mappings for converting shortcuts like WP: to Wikipedia:.
 */
( function () {

	$.highlightText = {

		// Split our pattern string at spaces and run our highlight function on the results
		splitAndHighlight: function ( node, text ) {
			var i,
				words = text.split( ' ' );
			for ( i = 0; i < words.length; i++ ) {
				if ( words[ i ].length === 0 ) {
					continue;
				}
				$.highlightText.innerHighlight(
					node,
					new RegExp( '(^|\\s)' + mw.util.escapeRegExp( words[ i ] ), 'i' )
				);
			}
			return node;
		},

		prefixHighlight: function ( node, prefix ) {
			$.highlightText.innerHighlight(
				node,
				new RegExp( '(^)' + mw.util.escapeRegExp( prefix ), 'i' )
			);
		},

		// match prefix plus any combining characters to prevent ugly rendering (see T35242)
		prefixPlusComboHighlight: function ( node, prefix ) {

			// Equivalent to \p{Mark} (which is not currently available in JavaScript)
			var comboMarks = '[\u0300-\u036F\u0483-\u0489\u0591-\u05BD\u05BF\u05C1\u05C2\u05C4\u05C5\u05C7\u0610-\u061A\u064B-\u065F\u0670\u06D6-\u06DC\u06DF-\u06E4\u06E7\u06E8\u06EA-\u06ED\u0711\u0730-\u074A\u07A6-\u07B0\u07EB-\u07F3\u07FD\u0816-\u0819\u081B-\u0823\u0825-\u0827\u0829-\u082D\u0859-\u085B\u08D3-\u08E1\u08E3-\u0903\u093A-\u093C\u093E-\u094F\u0951-\u0957\u0962\u0963\u0981-\u0983\u09BC\u09BE-\u09C4\u09C7\u09C8\u09CB-\u09CD\u09D7\u09E2\u09E3\u09FE\u0A01-\u0A03\u0A3C\u0A3E-\u0A42\u0A47\u0A48\u0A4B-\u0A4D\u0A51\u0A70\u0A71\u0A75\u0A81-\u0A83\u0ABC\u0ABE-\u0AC5\u0AC7-\u0AC9\u0ACB-\u0ACD\u0AE2\u0AE3\u0AFA-\u0AFF\u0B01-\u0B03\u0B3C\u0B3E-\u0B44\u0B47\u0B48\u0B4B-\u0B4D\u0B56\u0B57\u0B62\u0B63\u0B82\u0BBE-\u0BC2\u0BC6-\u0BC8\u0BCA-\u0BCD\u0BD7\u0C00-\u0C04\u0C3E-\u0C44\u0C46-\u0C48\u0C4A-\u0C4D\u0C55\u0C56\u0C62\u0C63\u0C81-\u0C83\u0CBC\u0CBE-\u0CC4\u0CC6-\u0CC8\u0CCA-\u0CCD\u0CD5\u0CD6\u0CE2\u0CE3\u0D00-\u0D03\u0D3B\u0D3C\u0D3E-\u0D44\u0D46-\u0D48\u0D4A-\u0D4D\u0D57\u0D62\u0D63\u0D82\u0D83\u0DCA\u0DCF-\u0DD4\u0DD6\u0DD8-\u0DDF\u0DF2\u0DF3\u0E31\u0E34-\u0E3A\u0E47-\u0E4E\u0EB1\u0EB4-\u0EB9\u0EBB\u0EBC\u0EC8-\u0ECD\u0F18\u0F19\u0F35\u0F37\u0F39\u0F3E\u0F3F\u0F71-\u0F84\u0F86\u0F87\u0F8D-\u0F97\u0F99-\u0FBC\u0FC6\u102B-\u103E\u1056-\u1059\u105E-\u1060\u1062-\u1064\u1067-\u106D\u1071-\u1074\u1082-\u108D\u108F\u109A-\u109D\u135D-\u135F\u1712-\u1714\u1732-\u1734\u1752\u1753\u1772\u1773\u17B4-\u17D3\u17DD\u180B-\u180D\u1885\u1886\u18A9\u1920-\u192B\u1930-\u193B\u1A17-\u1A1B\u1A55-\u1A5E\u1A60-\u1A7C\u1A7F\u1AB0-\u1ABE\u1B00-\u1B04\u1B34-\u1B44\u1B6B-\u1B73\u1B80-\u1B82\u1BA1-\u1BAD\u1BE6-\u1BF3\u1C24-\u1C37\u1CD0-\u1CD2\u1CD4-\u1CE8\u1CED\u1CF2-\u1CF4\u1CF7-\u1CF9\u1DC0-\u1DF9\u1DFB-\u1DFF\u20D0-\u20F0\u2CEF-\u2CF1\u2D7F\u2DE0-\u2DFF\u302A-\u302F\u3099\u309A\uA66F-\uA672\uA674-\uA67D\uA69E\uA69F\uA6F0\uA6F1\uA802\uA806\uA80B\uA823-\uA827\uA880\uA881\uA8B4-\uA8C5\uA8E0-\uA8F1\uA8FF\uA926-\uA92D\uA947-\uA953\uA980-\uA983\uA9B3-\uA9C0\uA9E5\uAA29-\uAA36\uAA43\uAA4C\uAA4D\uAA7B-\uAA7D\uAAB0\uAAB2-\uAAB4\uAAB7\uAAB8\uAABE\uAABF\uAAC1\uAAEB-\uAAEF\uAAF5\uAAF6\uABE3-\uABEA\uABEC\uABED\uFB1E\uFE00-\uFE0F\uFE20-\uFE2F]';

			$.highlightText.innerHighlight(
				node,
				new RegExp( '(^)' + mw.util.escapeRegExp( prefix ) + comboMarks + '*', 'i' )
			);
		},

		// scans a node looking for the pattern and wraps a span around each match
		innerHighlight: function ( node, pat ) {
			var i, match, pos, spannode, middlebit, middleclone;
			if ( node.nodeType === Node.TEXT_NODE ) {
				// TODO - need to be smarter about the character matching here.
				// non Latin characters can make regex think a new word has begun: do not use \b
				// http://stackoverflow.com/questions/3787072/regex-wordwrap-with-utf8-characters-in-js
				// look for an occurrence of our pattern and store the starting position
				match = node.data.match( pat );
				if ( match ) {
					pos = match.index + match[ 1 ].length; // include length of any matched spaces
					// create the span wrapper for the matched text
					spannode = document.createElement( 'span' );
					spannode.className = 'highlight';
					// shave off the characters preceding the matched text
					middlebit = node.splitText( pos );
					// shave off any unmatched text off the end
					middlebit.splitText( match[ 0 ].length - match[ 1 ].length );
					// clone for appending to our span
					middleclone = middlebit.cloneNode( true );
					// append the matched text node to the span
					spannode.appendChild( middleclone );
					// replace the matched node, with our span-wrapped clone of the matched node
					middlebit.parentNode.replaceChild( spannode, middlebit );
				}
			} else if (
				node.nodeType === Node.ELEMENT_NODE &&
				// element with childnodes, and not a script, style or an element we created
				node.childNodes &&
				!/(script|style)/i.test( node.tagName ) &&
				!(
					node.tagName.toLowerCase() === 'span' &&
					node.className.match( /\bhighlight/ )
				)
			) {
				for ( i = 0; i < node.childNodes.length; ++i ) {
					// call the highlight function for each child node
					$.highlightText.innerHighlight( node.childNodes[ i ], pat );
				}
			}
		}
	};

	/**
	 * Highlight certain text in current nodes (by wrapping it in `<span class="highlight">...</span>`).
	 *
	 * @param {string} matchString String to match
	 * @param {Object} [options]
	 * @param {string} [options.method='splitAndHighlight'] Method of matching to use, one of:
	 *   - 'splitAndHighlight': Split `matchString` on spaces, then match each word separately.
	 *   - 'prefixHighlight': Match `matchString` at the beginning of text only.
	 *   - 'prefixPlusComboHighlight': Match `matchString` plus any combining characters at
	 *     the beginning of text only.
	 * @return {jQuery}
	 * @chainable
	 */
	$.fn.highlightText = function ( matchString, options ) {
		options = options || {};
		options.method = options.method || 'splitAndHighlight';
		return this.each( function () {
			var $el = $( this );
			$el.data( 'highlightText', { originalText: $el.text() } );
			$.highlightText[ options.method ]( this, matchString );
		} );
	};

}() );
