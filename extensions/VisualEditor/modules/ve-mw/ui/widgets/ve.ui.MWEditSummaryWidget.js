/*!
 * VisualEditor UserInterface MWEditSummaryWidget class.
 *
 * @copyright 2011-2018 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Multi line text input for edit summary, with auto completion based on
 * the user's previous edit summaries.
 *
 * @class
 * @extends OO.ui.MultilineTextInputWidget
 * @mixins OO.ui.mixin.LookupElement
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {number} [limit=6] Number of suggestions to show
 */
ve.ui.MWEditSummaryWidget = function VeUiMWEditSummaryWidget( config ) {
	config = config || {};

	// Parent method
	ve.ui.MWEditSummaryWidget.super.apply( this, arguments );

	// Mixin method
	OO.ui.mixin.LookupElement.call( this, ve.extendObject( {
		showPendingRequest: false,
		showSuggestionsOnFocus: false,
		allowSuggestionsWhenEmpty: false,
		highlightFirst: false
	}, config ) );

	this.limit = config.limit || 6;
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEditSummaryWidget, OO.ui.MultilineTextInputWidget );

OO.mixinClass( ve.ui.MWEditSummaryWidget, OO.ui.mixin.LookupElement );

/* Static properties */

ve.ui.MWEditSummaryWidget.static.summarySplitter = /^(\/\*.*?\*\/\s*)?(.*)$/;

/* Static methods */

/**
 * Split a summary into the section and the actual summary
 *
 * @param {string} summary Summary
 * @return {Object} Object with section and comment string properties
 */
ve.ui.MWEditSummaryWidget.static.splitSummary = function ( summary ) {
	var result = summary.match( this.summarySplitter );
	return {
		section: result[ 1 ] || '',
		comment: result[ 2 ]
	};
};

/**
 * Filter a list of edit summaries to a specific query stirng
 *
 * @param {string[]} summaries Edit summaries
 * @param {string} query User query
 * @return {string[]} Filtered edit summaries
 */
ve.ui.MWEditSummaryWidget.static.getMatchingSummaries = function ( summaries, query ) {
	var summaryPrefixMatches = [], wordPrefixMatches = [], otherMatches = [],
		lowerQuery = query.toLowerCase();

	if ( !query.trim() ) {
		// Show no results for empty query
		return [];
	}

	summaries.forEach( function ( summary ) {
		var lowerSummary = summary.toLowerCase(),
			index = lowerSummary.indexOf( lowerQuery );
		if ( index === 0 ) {
			// Exclude exact matches
			if ( lowerQuery !== summary ) {
				summaryPrefixMatches.push( summary );
			}
		} else if ( index !== -1 ) {
			if ( lowerSummary[ index - 1 ].match( /\s/ ) ) {
				// Character before match is whitespace
				wordPrefixMatches.push( summary );
			} else {
				otherMatches.push( summary );
			}
		}
	} );
	return summaryPrefixMatches.concat( wordPrefixMatches, otherMatches );
};

/* Methods */

/**
 * Get recent edit summaries for the logged in user
 *
 * @return {jQuery.Promise} Promise which resolves with a list of summaries
 */
ve.ui.MWEditSummaryWidget.prototype.getSummaries = function () {
	var splitSummary = this.constructor.static.splitSummary.bind( this.constructor.static );
	if ( !this.getSummariesPromise ) {
		if ( mw.user.isAnon() ) {
			this.getSummariesPromise = ve.createDeferred().resolve( [] ).promise();
		} else {
			this.getSummariesPromise = ve.init.target.getLocalApi().get( {
				action: 'query',
				list: 'usercontribs',
				ucuser: mw.user.getName(),
				ucprop: 'comment|title',
				uclimit: 500,
				format: 'json'
			} ).then( function ( response ) {
				var usedComments = {},
					changes = ve.getProp( response, 'query', 'usercontribs' ) || [];

				return changes
					// Remove section /* headings */
					.map( function ( change ) {
						return splitSummary( change.comment ).comment.trim();
					} )
					// Filter out duplicates and empty comments
					.filter( function ( comment ) {
						if ( !comment || Object.prototype.hasOwnProperty.call( usedComments, comment ) ) {
							return false;
						}
						usedComments[ comment ] = true;
						return true;
					} )
					.sort();
			} );
		}
	}
	return this.getSummariesPromise;
};

/**
 * @inheritdoc
 */
ve.ui.MWEditSummaryWidget.prototype.getLookupRequest = function () {
	var query = this.constructor.static.splitSummary( this.value ),
		limit = this.limit,
		widget = this;

	return this.getSummaries().then( function ( allSummaries ) {
		var matchingSummaries = widget.constructor.static.getMatchingSummaries( allSummaries, query.comment );
		if ( matchingSummaries.length > limit ) {
			// Quick in-place truncate
			matchingSummaries.length = limit;
		}
		return { summaries: matchingSummaries, section: query.section };
	} ).promise( { abort: function () {} } ); // don't abort, the actual request will be the same anyway
};

/**
 * @inheritdoc
 */
ve.ui.MWEditSummaryWidget.prototype.getLookupCacheDataFromResponse = function ( response ) {
	return response;
};

/**
 * @inheritdoc
 */
ve.ui.MWEditSummaryWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	return data.summaries.map( function ( item ) {
		return new OO.ui.MenuOptionWidget( {
			label: item,
			data: data.section + item
		} );
	} );
};
