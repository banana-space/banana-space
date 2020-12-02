/*!
 * VisualEditor MediaWiki event subscriber.
 *
 * Subscribes to ve.track() events and routes them to mw.track().
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

( function () {
	var timing, editingSessionId,
		actionPrefixMap = {
			firstChange: 'first_change',
			saveIntent: 'save_intent',
			saveAttempt: 'save_attempt',
			saveSuccess: 'save_success',
			saveFailure: 'save_failure'
		},
		trackdebug = !!mw.Uri().query.trackdebug,
		firstInitDone = false;

	function getEditingSessionIdFromRequest() {
		return mw.config.get( 'wgWMESchemaEditAttemptStepSessionId' ) ||
			mw.Uri().query.editingStatsId;
	}

	timing = {};
	editingSessionId = getEditingSessionIdFromRequest() || mw.user.generateRandomSessionId();

	function log() {
		// mw.log is a no-op unless resource loader is in debug mode, so
		// this allows trackdebug to work independently (T211698)
		// eslint-disable-next-line no-console
		console.log.apply( console, arguments );
	}

	function inSample() {
		// Not using mw.eventLog.inSample() because we need to be able to pass our own editingSessionId
		return mw.eventLog.randomTokenMatch(
			1 / mw.config.get( 'wgWMESchemaEditAttemptStepSamplingRate' ),
			editingSessionId
		);
	}

	function computeDuration( action, event, timeStamp ) {
		if ( event.timing !== undefined ) {
			return event.timing;
		}

		switch ( action ) {
			case 'ready':
				return timeStamp - timing.init;
			case 'loaded':
				return timeStamp - timing.init;
			case 'firstChange':
				return timeStamp - timing.ready;
			case 'saveIntent':
				return timeStamp - timing.ready;
			case 'saveAttempt':
				return timeStamp - timing.saveIntent;
			case 'saveSuccess':
			case 'saveFailure':
				// HERE BE DRAGONS: the caller must compute these themselves
				// for sensible results. Deliberately sabotage any attempts to
				// use the default by returning -1
				mw.log.warn( 've.init.mw.trackSubscriber: Do not rely on default timing value for saveSuccess/saveFailure' );
				return -1;
			case 'abort':
				switch ( event.type ) {
					case 'preinit':
						return timeStamp - timing.init;
					case 'nochange':
					case 'switchwith':
					case 'switchwithout':
					case 'switchnochange':
					case 'abandon':
						return timeStamp - timing.ready;
					case 'abandonMidsave':
						return timeStamp - timing.saveAttempt;
				}
		}
		mw.log.warn( 've.init.mw.trackSubscriber: Unrecognized action', action );
		return -1;
	}

	function mwEditHandler( topic, data, timeStamp ) {
		var action = topic.split( '.' )[ 1 ],
			actionPrefix = actionPrefixMap[ action ] || action,
			duration = 0,
			event;

		if ( action === 'init' ) {
			if ( firstInitDone ) {
				// Regenerate editingSessionId
				editingSessionId = mw.user.generateRandomSessionId();
			}
			firstInitDone = true;
		}

		if ( !inSample() && !mw.config.get( 'wgWMESchemaEditAttemptStepOversample' ) && !trackdebug ) {
			return;
		}

		if (
			action === 'abort' &&
			( data.type === 'unknown' || data.type === 'unknown-edited' )
		) {
			if (
				timing.saveAttempt &&
				timing.saveSuccess === undefined &&
				timing.saveFailure === undefined
			) {
				data.type = 'abandonMidsave';
			} else if (
				timing.init &&
				timing.ready === undefined
			) {
				data.type = 'preinit';
			} else if ( data.type === 'unknown' ) {
				data.type = 'nochange';
			} else {
				data.type = 'abandon';
			}
		}

		// Convert mode=source/visual to interface name
		if ( data && data.mode ) {
			// eslint-disable-next-line camelcase
			data.editor_interface = data.mode === 'source' ? 'wikitext-2017' : 'visualeditor';
			delete data.mode;
		}

		if ( !data.platform ) {
			if ( ve.init && ve.init.target && ve.init.target.constructor.static.platformType ) {
				data.platform = ve.init.target.constructor.static.platformType;
			} else {
				data.platform = 'other';
				// TODO: outright abort in this case, once we think we've caught everything
				mw.log.warn( 've.init.mw.trackSubscriber: no target available and no platform specified', action );
			}
		}

		/* eslint-disable camelcase */
		event = $.extend( {
			version: 1,
			action: action,
			is_oversample: !inSample(),
			editor_interface: 'visualeditor',
			integration: ve.init && ve.init.target && ve.init.target.constructor.static.integrationType || 'page',
			page_id: mw.config.get( 'wgArticleId' ),
			page_title: mw.config.get( 'wgPageName' ),
			page_ns: mw.config.get( 'wgNamespaceNumber' ),
			// eslint-disable-next-line no-jquery/no-global-selector
			revision_id: mw.config.get( 'wgRevisionId' ) || $( 'input[name=parentRevId]' ).val(),
			editing_session_id: editingSessionId,
			page_token: mw.user.getPageviewToken(),
			session_token: mw.user.sessionId(),
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			mw_version: mw.config.get( 'wgVersion' )
		}, data );

		if ( mw.user.isAnon() ) {
			event.user_class = 'IP';
		}

		// Schema's kind of a mess of special properties
		if ( action === 'init' || action === 'abort' || action === 'saveFailure' ) {
			event[ actionPrefix + '_type' ] = event.type;
		}
		if ( action === 'init' || action === 'abort' ) {
			event[ actionPrefix + '_mechanism' ] = event.mechanism;
		}
		if ( action !== 'init' ) {
			// Schema actually does have an init_timing field, but we don't want to
			// store it because it's not meaningful.
			duration = Math.round( computeDuration( action, event, timeStamp ) );
			event[ actionPrefix + '_timing' ] = duration;
		}
		if ( action === 'saveFailure' ) {
			event[ actionPrefix + '_message' ] = event.message;
		}
		/* eslint-enable camelcase */

		// Remove renamed properties
		delete event.type;
		delete event.mechanism;
		delete event.timing;
		delete event.message;

		if ( action === 'abort' ) {
			timing = {};
		} else {
			timing[ action ] = timeStamp;
		}

		if ( trackdebug ) {
			log( topic, duration + 'ms', event );
		} else {
			mw.track( 'event.EditAttemptStep', event );
		}
	}

	function mwTimingHandler( topic, data ) {
		// Add type for save errors; not in the topic for stupid historical reasons
		if ( topic === 'mwtiming.performance.user.saveError' ) {
			topic = topic + '.' + data.type;
		}

		// Map mwtiming.foo --> timing.ve.foo.mobile
		topic = topic.replace( /^mwtiming/, 'timing.ve.' + data.targetName );
		if ( trackdebug ) {
			log( topic, Math.round( data.duration ) + 'ms' );
		} else {
			mw.track( topic, data.duration );
		}
	}

	function activityHandler( topic, data ) {
		var feature = topic.split( '.' )[ 1 ],
			event;

		if ( !inSample() && !trackdebug ) {
			return;
		}

		if ( ve.init.target && (
			ve.init.target.constructor.static.platformType !== 'desktop'
		) ) {
			// We want to log activity events when we're also logging to
			// EditAttemptStep. The EAS events are only fired from DesktopArticleTarget
			// in this repo. As such, we suppress this unless the current target is at
			// least inheriting that. (Other tools may fire their own instances of
			// those events, but probably need to reimplement this anyway for
			// session-identification reasons.)
			return;
		}

		/* eslint-disable camelcase */
		event = {
			feature: feature,
			action: data.action,
			editingSessionId: editingSessionId,
			user_id: mw.user.getId(),
			user_editcount: mw.config.get( 'wgUserEditCount', 0 ),
			editor_interface: ve.getProp( ve, 'init', 'target', 'surface', 'mode' ) === 'source' ? 'wikitext-2017' : 'visualeditor',
			integration: ve.getProp( ve, 'init', 'target', 'constructor', 'static', 'integrationType' ) || 'page',
			platform: ve.getProp( ve, 'init', 'target', 'constructor', 'static', 'platformType' ) || 'other'
		};
		/* eslint-enable camelcase */

		if ( trackdebug ) {
			log( topic, event );
		} else {
			mw.track( 'event.VisualEditorFeatureUse', event );
		}
	}

	// Only log events if the WikimediaEvents extension is installed.
	// It provides variables that the above code depends on and registers the schemas.
	if ( mw.config.exists( 'wgWMESchemaEditAttemptStepSamplingRate' ) ) {
		// Ensure 'ext.eventLogging' first, it provides mw.eventLog.randomTokenMatch.
		mw.loader.using( 'ext.eventLogging' ).done( function () {
			ve.trackSubscribe( 'mwedit.', mwEditHandler );
			ve.trackSubscribe( 'mwtiming.', mwTimingHandler );
			ve.trackSubscribe( 'activity.', activityHandler );
		} );
	}

}() );
