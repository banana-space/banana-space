/*!
 * VisualEditor UserInterface LanguageSearchWidget class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Creates an ve.ui.LanguageSearchWidget object.
 *
 * @class
 * @extends OO.ui.SearchWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.LanguageSearchWidget = function VeUiLanguageSearchWidget( config ) {
	var i, l, languageCode, languageCodes;

	// Configuration initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'visualeditor-language-search-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.LanguageSearchWidget.super.call( this, config );

	// Properties
	this.languageResultWidgets = [];
	this.filteredLanguageResultWidgets = [];

	languageCodes = ve.init.platform.getLanguageCodes().sort();

	for ( i = 0, l = languageCodes.length; i < l; i++ ) {
		languageCode = languageCodes[ i ];
		this.languageResultWidgets.push(
			new ve.ui.LanguageResultWidget( {
				data: {
					code: languageCode,
					name: ve.init.platform.getLanguageName( languageCode ),
					autonym: ve.init.platform.getLanguageAutonym( languageCode )
				}
			} )
		);
	}
	this.setAvailableLanguages();

	// Initialization
	this.$element.addClass( 've-ui-languageSearchWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.LanguageSearchWidget, OO.ui.SearchWidget );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.LanguageSearchWidget.prototype.onQueryChange = function () {
	// Parent method
	ve.ui.LanguageSearchWidget.super.prototype.onQueryChange.apply( this, arguments );

	// Populate
	this.addResults();
};

/**
 * Set available languages to show
 *
 * @param {string[]} availableLanguages Available language codes to show, all if undefined
 */
ve.ui.LanguageSearchWidget.prototype.setAvailableLanguages = function ( availableLanguages ) {
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
ve.ui.LanguageSearchWidget.prototype.addResults = function () {
	var i, iLen, j, jLen, languageResult, data, matchedProperty,
		matchProperties = [ 'name', 'autonym', 'code' ],
		query = this.query.getValue().trim(),
		compare = ve.supportsIntl ?
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
