/**
 * Creates a TemplateDataLanguageSearchWidget object.
 * This is a copy of ve.ui.LanguageSearchWidget.
 *
 * @class
 * @extends OO.ui.SearchWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
mw.TemplateData.LanguageSearchWidget = function mwTemplateDataLanguageSearchWidget( config ) {
	var i, l, languageCode, languageCodes;

	// Configuration initialization
	config = $.extend( {
		placeholder: mw.msg( 'templatedata-modal-search-input-placeholder' )
	}, config );

	// Parent constructor
	mw.TemplateData.LanguageSearchWidget.parent.call( this, config );

	// Properties
	this.languageResultWidgets = [];
	this.filteredLanguageResultWidgets = [];

	languageCodes = Object.keys( $.uls.data.getAutonyms() ).sort();

	for ( i = 0, l = languageCodes.length; i < l; i++ ) {
		languageCode = languageCodes[ i ];
		this.languageResultWidgets.push(
			new mw.TemplateData.LanguageResultWidget( {
				data: {
					code: languageCode,
					name: $.uls.data.getAutonym( languageCode ),
					autonym: $.uls.data.getAutonym( languageCode )
				}
			} )
		);
	}
	this.setAvailableLanguages();

	// Initialization
	this.$element.addClass( 'tdg-languageSearchWidget' );
};

/* Inheritance */

OO.inheritClass( mw.TemplateData.LanguageSearchWidget, OO.ui.SearchWidget );

/* Methods */

/**
 * FIXME: this should be inheritdoc
 */
mw.TemplateData.LanguageSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	mw.TemplateData.LanguageSearchWidget.parent.prototype.onQueryChange.apply( this, arguments );

	// Populate
	this.addResults();
};

/**
 * Set available languages to show
 *
 * @param {string[]} availableLanguages Available language codes to show, all if undefined
 */
mw.TemplateData.LanguageSearchWidget.prototype.setAvailableLanguages = function ( availableLanguages ) {
	var i, iLen, languageResult, data;

	if ( !availableLanguages ) {
		this.filteredLanguageResultWidgets = this.languageResultWidgets.slice();
		return;
	}

	this.filteredLanguageResultWidgets = [];

	for ( i = 0, iLen = this.languageResultWidgets.length; i < iLen; i++ ) {
		languageResult = this.languageResultWidgets[ i ];
		data = languageResult.getData();
		if ( availableLanguages.indexOf( data.code ) !== -1 ) {
			this.filteredLanguageResultWidgets.push( languageResult );
		}
	}
};

/**
 * Update search results from current query
 */
mw.TemplateData.LanguageSearchWidget.prototype.addResults = function () {
	var i, iLen, j, jLen, languageResult, data, matchedProperty,
		matchProperties = [ 'name', 'autonym', 'code' ],
		query = this.query.getValue().trim(),
		compare = window.Intl && Intl.Collator ?
			new Intl.Collator( this.lang, { sensitivity: 'base' } ).compare :
			function ( a, b ) { return a.toLowerCase() === b.toLowerCase() ? 0 : 1; },
		hasQuery = !!query.length,
		items = [];

	this.results.clearItems();

	for ( i = 0, iLen = this.filteredLanguageResultWidgets.length; i < iLen; i++ ) {
		languageResult = this.filteredLanguageResultWidgets[ i ];
		data = languageResult.getData();
		matchedProperty = null;

		for ( j = 0, jLen = matchProperties.length; j < jLen; j++ ) {
			if ( data[ matchProperties[ j ] ] && compare( data[ matchProperties[ j ] ].slice( 0, query.length ), query ) === 0 ) {
				matchedProperty = matchProperties[ j ];
				break;
			}
		}

		if ( query === '' || matchedProperty ) {
			items.push(
				languageResult
					.updateLabel( query, matchedProperty, compare )
					.setSelected( false )
					.setHighlighted( false )
			);
		}
	}

	this.results.addItems( items );
	if ( hasQuery ) {
		this.results.highlightItem( this.results.findFirstSelectableItem() );
	}
};
