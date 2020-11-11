/**
 * Main JavaScript for the Cite extension. The main purpose of this file
 * is to add accessibility attributes to the citation links as that can
 * hardly be done server side (bug 38141).
 *
 * @author Marius Hoch <hoo@online.de>
 */
( function ( mw, $ ) {
	'use strict';

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var accessibilityLabelOne = mw.msg( 'cite_references_link_accessibility_label' ),
			accessibilityLabelMany = mw.msg( 'cite_references_link_many_accessibility_label' );

		$content.find( '.mw-cite-backlink' ).each( function () {
			var $links = $( this ).find( 'a' ),
				label;

			if ( $links.length > 1 ) {
				// This citation is used multiple times. Let's only set the accessibility label on the first link, the
				// following ones should then be self-explaining. This is needed to make sure this isn't getting
				// too wordy.
				label = accessibilityLabelMany;
			} else {
				label = accessibilityLabelOne;
			}

			// We can't use aria-label over here as that's not supported consistently across all screen reader / browser
			// combinations. We have to use visually hidden spans for the accessibility labels instead.
			$links.eq( 0 ).prepend(
				$( '<span>' )
					.addClass( 'cite-accessibility-label' )
					// Also make sure we have at least one space between the accessibility label and the visual one
					.text( label + ' ' )
			);
		} );
	} );
}( mediaWiki, jQuery ) );
