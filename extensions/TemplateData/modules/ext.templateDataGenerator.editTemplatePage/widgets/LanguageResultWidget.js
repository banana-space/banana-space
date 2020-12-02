/**
 * Creates a TemplateDataLanguageResultWidget object.
 * This is a copy of ve.ui.LanguageResultWidget
 *
 * @class
 * @extends OO.ui.OptionWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
mw.TemplateData.LanguageResultWidget = function mwTemplateDataLanguageResultWidget( config ) {
	// Parent constructor
	mw.TemplateData.LanguageResultWidget.parent.call( this, config );

	// Initialization
	this.$element.addClass( 'tdg-languageResultWidget' );
	this.$name = $( '<div>' ).addClass( 'tdg-languageResultWidget-name' );
	this.$otherMatch = $( '<div>' ).addClass( 'tdg-languageResultWidget-otherMatch' );
	this.setLabel( this.$otherMatch.add( this.$name ) );
};

/* Inheritance */

OO.inheritClass( mw.TemplateData.LanguageResultWidget, OO.ui.OptionWidget );

/* Methods */

/**
 * Update labels based on query
 *
 * @param {string} [query] Query text which matched this result
 * @param {string} [matchedProperty] Data property which matched the query text
 * @chainable
 */
mw.TemplateData.LanguageResultWidget.prototype.updateLabel = function ( query, matchedProperty ) {
	var $highlighted, data = this.getData();

	// Reset text
	this.$name.text( data.name );
	this.$otherMatch.text( data.code );

	// Highlight where applicable
	if ( matchedProperty ) {
		$highlighted = this.constructor.static.highlightQuery( data[ matchedProperty ], query );
		if ( matchedProperty === 'name' ) {
			this.$name.empty().append( $highlighted );
		} else {
			this.$otherMatch.empty().append( $highlighted );
		}
	}

	return this;
};

/**
 * Highlight text where a substring query matches
 *
 * Copied from ve#highlightQuery
 *
 * @param {string} text Text
 * @param {string} query Query to find
 * @return {jQuery} Text with query substring wrapped in highlighted span
 */
mw.TemplateData.LanguageResultWidget.static.highlightQuery = function ( text, query ) {
	var $result = $( '<span>' ),
		offset = text.toLowerCase().indexOf( query.toLowerCase() );

	if ( !query.length || offset === -1 ) {
		return $result.text( text );
	}
	$result.append(
		document.createTextNode( text.slice( 0, offset ) ),
		$( '<span>' )
			.addClass( 'tdg-languageResultWidget-highlight' )
			.text( text.slice( offset, offset + query.length ) ),
		document.createTextNode( text.slice( offset + query.length ) )
	);
	return $result.contents();
};
