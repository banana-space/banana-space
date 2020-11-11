/*!
 * JavaScript for Special:RevisionDelete
 */
( function ( mw, $ ) {
	$( function () {
		var colonSeparator = mw.message( 'colon-separator' ).text(),
			summaryCodePointLimit = mw.config.get( 'wgCommentCodePointLimit' ),
			summaryByteLimit = mw.config.get( 'wgCommentByteLimit' ),
			$wpDeleteReasonList = $( '#wpDeleteReasonList' ),
			$wpReason = $( '#wpReason' ),
			filterFn = function ( input ) {
				// Should be built the same as in SpecialRevisionDelete::submit()
				var comment = $wpDeleteReasonList.val();
				if ( comment === 'other' ) {
					comment = input;
				} else if ( input !== '' ) {
					// Entry from drop down menu + additional comment
					comment += colonSeparator + input;
				}
				return comment;
			};

		// Limit to bytes or UTF-8 codepoints, depending on MediaWiki's configuration
		if ( summaryCodePointLimit ) {
			$wpReason.codePointLimit( summaryCodePointLimit, filterFn );
		} else if ( summaryByteLimit ) {
			$wpReason.byteLimit( summaryByteLimit, filterFn );
		}
	} );

}( mediaWiki, jQuery ) );
