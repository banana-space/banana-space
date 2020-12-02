/**
 * @file Temporary tracking to evaluate the impact of Reference Previews on users' interaction with references.
 *
 * The baseline metrics are for a sample of users who don't have ReferencePreviews enabled.
 *
 * Users with the feature enabled are not sampled, and events are logged using the ReferencePreviewsCite schema.
 *
 * @see https://phabricator.wikimedia.org/T214493
 * @see https://phabricator.wikimedia.org/T231529
 * @see https://meta.wikimedia.org/wiki/Schema:ReferencePreviewsBaseline
 * @see https://meta.wikimedia.org/wiki/Schema:ReferencePreviewsCite
 */
( function () {
	'use strict';

	$( function () {
		var isReferencePreviewsEnabled = mw.config.get( 'wgPopupsReferencePreviews', false ),
			loggingTopic = isReferencePreviewsEnabled ?
				'event.ReferencePreviewsCite' :
				'event.ReferencePreviewsBaseline',
			samplingRate = isReferencePreviewsEnabled ? 1 : 1000;

		if ( !navigator.sendBeacon ||
			!mw.config.get( 'wgIsArticle' ) ||
			!mw.eventLog ||
			!mw.eventLog.eventInSample( samplingRate )
		) {
			return;
		}

		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#mw-content-text' ).on(
			'click',
			// Footnote links, references block in VisualEditor, and reference content links.
			'.reference a[ href*="#" ], .mw-reference-text a, .reference-text a',
			function () {
				var isInReferenceBlock = $( this ).parents( '.references' ).length > 0;
				mw.track( loggingTopic, {
					action: ( isInReferenceBlock ?
						'clickedReferenceContentLink' :
						'clickedFootnote' )
				} );
			}
		);

		mw.track( loggingTopic, { action: 'pageview' } );
	} );
}() );
