( function () {
	// eslint-disable-next-line no-jquery/no-global-selector
	var archiveLinkExists = $( '.flow-link-to-archive' ).length,
		tour = new mw.guidedTour.TourBuilder( {
			name: 'flowOptIn',
			shouldLog: true,
			isSinglePage: true
		} ),
		$firstStepDescription = $( '<div>' )
			.addClass( 'flow-guidedtour-optin-firststep-description' )
			.append(
				$( '<div>' )
					.addClass( 'flow-guidedtour-optin-firststep-description-image' ),
				mw.msg( 'flow-guidedtour-optin-welcome-description' )
			);

	tour
		.firstStep( {
			name: 'newTopic',
			titlemsg: 'flow-guidedtour-optin-welcome',
			description: $firstStepDescription[ 0 ].outerHTML,
			attachTo: '.flow-ui-newTopicWidget .flow-ui-newTopicWidget-title',
			position: 'bottom',
			autoFocus: true,
			closeOnClickOutside: false
		} )
		.next( archiveLinkExists ? 'findOldConversations' : 'feedback' );

	if ( archiveLinkExists ) {
		tour
			.step( {
				name: 'findOldConversations',
				titlemsg: 'flow-guidedtour-optin-find-old-conversations',
				descriptionmsg: 'flow-guidedtour-optin-find-old-conversations-description',
				attachTo: '.flow-ui-boardDescriptionWidget-content',
				position: 'bottom',
				autoFocus: true,
				closeOnClickOutside: false
			} )
			.next( 'feedback' )
			.back( 'newTopic' );
	}

	tour
		.step( {
			name: 'feedback',
			titlemsg: 'flow-guidedtour-optin-feedback',
			descriptionmsg: 'flow-guidedtour-optin-feedback-description',
			attachTo: '#pt-betafeatures',
			position: 'bottom',
			autoFocus: true,
			closeOnClickOutside: false
		} )
		.back( archiveLinkExists ? 'findOldConversations' : 'newTopic' );

}() );
