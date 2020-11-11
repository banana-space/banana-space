/*
 * JavaScript for WikiEditor
 */

( function ( $, mw ) {
	var editingSessionId;

	function logEditEvent( action, data ) {
		if ( mw.loader.getState( 'schema.Edit' ) === null ) {
			return;
		}

		// Sample 6.25% (via hex digit)
		// We have to do this on the client too because the unload handler
		// can cause an editingSessionId to be generated on the client
		if ( editingSessionId.charAt( 0 ) > '0' ) {
			return;
		}

		mw.loader.using( 'schema.Edit' ).done( function () {
			data = $.extend( {
				version: 1,
				action: action,
				editor: 'wikitext',
				platform: 'desktop', // FIXME
				integration: 'page',
				'page.id': mw.config.get( 'wgArticleId' ),
				'page.title': mw.config.get( 'wgPageName' ),
				'page.ns': mw.config.get( 'wgNamespaceNumber' ),
				'page.revid': mw.config.get( 'wgRevisionId' ),
				'user.id': mw.user.getId(),
				'user.editCount': mw.config.get( 'wgUserEditCount', 0 ),
				'mediawiki.version': mw.config.get( 'wgVersion' )
			}, data );

			if ( mw.user.isAnon() ) {
				data[ 'user.class' ] = 'IP';
			}

			data[ 'action.' + action + '.type' ] = data.type;
			data[ 'action.' + action + '.mechanism' ] = data.mechanism;
			data[ 'action.' + action + '.timing' ] = data.timing === undefined ?
				0 : Math.floor( data.timing );
			// Remove renamed properties
			delete data.type;
			delete data.mechanism;
			delete data.timing;

			mw.eventLog.logEvent( 'Edit', data );
		} );
	}

	$( function () {
		var $textarea = $( '#wpTextbox1' ),
			$editingSessionIdInput = $( '#editingStatsId' ),
			origText = $textarea.val(),
			submitting, onUnloadFallback;

		if ( $editingSessionIdInput.length ) {
			editingSessionId = $editingSessionIdInput.val();
			if ( window.performance && window.performance.timing ) {
				// We want to track from the time the user started to try to
				// launch the editor which navigationStart approximates. All
				// of our supported browsers *should* allow this. Rather than
				// fall back to the timestamp when the page loaded for those
				// that don't, we just ignore them, so as to not skew the
				// results towards better-performance in those cases.
				logEditEvent( 'ready', {
					editingSessionId: editingSessionId,
					timing: Date.now() - window.performance.timing.navigationStart
				} );
				$textarea.on( 'wikiEditor-toolbar-doneInitialSections', function () {
					logEditEvent( 'loaded', {
						editingSessionId: editingSessionId,
						timing: Date.now() - window.performance.timing.navigationStart
					} );
				} );
			}
			$textarea.closest( 'form' ).submit( function () {
				submitting = true;
			} );
			onUnloadFallback = window.onunload;
			window.onunload = function () {
				var fallbackResult, abortType,
					caVeEdit = $( '#ca-ve-edit' )[ 0 ],
					switchingToVE = caVeEdit && (
						document.activeElement === caVeEdit ||
						$.contains( caVeEdit, document.activeElement )
					),
					unmodified = mw.config.get( 'wgAction' ) !== 'submit' && origText === $textarea.val();

				if ( onUnloadFallback ) {
					fallbackResult = onUnloadFallback();
				}

				if ( switchingToVE && unmodified ) {
					abortType = 'switchnochange';
				} else if ( switchingToVE ) {
					abortType = 'switchwithout';
				} else if ( unmodified ) {
					abortType = 'nochange';
				} else {
					abortType = 'abandon';
				}

				if ( !submitting ) {
					logEditEvent( 'abort', {
						editingSessionId: editingSessionId,
						type: abortType
					} );
				}

				// If/when the user uses the back button to go back to the edit form
				// and the browser serves this from bfcache, regenerate the session ID
				// so we don't use the same ID twice. Ideally we'd do this by listening to the pageshow
				// event and checking e.originalEvent.persisted, but that doesn't work in Chrome:
				// https://code.google.com/p/chromium/issues/detail?id=344507
				// So instead we modify the DOM here, after sending the abort event.
				editingSessionId = mw.user.generateRandomSessionId();
				$editingSessionIdInput.val( editingSessionId );

				return fallbackResult;
			};
		}
	} );
}( jQuery, mediaWiki ) );
