/*!
 * VisualEditor MWDefinedTransclusionContextItem class.
 *
 * @copyright 2011-2018 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a defined MWTransclusion.
 *
 * Templates are defined on-wiki using the message page
 * [[MediaWiki:Visualeditor-template-tools-definition.json]]
 *
 * Example:
 * {
 *   // This key is the static name of the ve.ui.ContextItem
 *   "citationNeeded": [
 *     {
 *       // Normalized title string, or list of strings (for redirects)
 *       "title": [ "Citation needed", "cn" ],
 *       // Extra params. This whole object can be accessed
 *       // via #getMatchedTool
 *       "params": {
 *         "reason": "reason",
 *         "date": "date"
 *       }
 *     },
 *     {
 *       "title": "Cite quote",
 *       "params": {
 *         "date": "date"
 *       }
 *     }
 *   ]
 * }
 *
 * @class
 * @abstract
 * @extends ve.ui.MWTransclusionContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWDefinedTransclusionContextItem = function VeUiMWDefinedTransclusionContextItem() {
	// Parent constructor
	ve.ui.MWDefinedTransclusionContextItem.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWDefinedTransclusionContextItem, ve.ui.MWTransclusionContextItem );

/* Static Properties */

ve.ui.MWDefinedTransclusionContextItem.static.name = null;

ve.ui.MWDefinedTransclusionContextItem.static.toolDefinitions = ( function () {
	var tools;

	try {
		// Must use mw.message to avoid JSON being parsed as Wikitext
		tools = JSON.parse( mw.message( 'visualeditor-template-tools-definition.json' ).plain() );
	} catch ( e ) {}

	return tools || {};
}() );

/**
 * Only display item for single-template transclusions of these templates.
 *
 * @property {string|string[]|null}
 * @static
 * @inheritable
 */
ve.ui.MWDefinedTransclusionContextItem.static.template = null;

/* Static Methods */

/**
 * @inheritdoc
 */
ve.ui.MWDefinedTransclusionContextItem.static.isCompatibleWith = function ( model ) {
	// Parent method
	return ve.ui.MWDefinedTransclusionContextItem.super.static.isCompatibleWith.apply( this, arguments ) &&
		!!this.getMatchedTool( model );
};

/**
 * Get tool definitions, indexed by normalized title
 *
 * @return {Object} Collection of tool definitions
 */
ve.ui.MWDefinedTransclusionContextItem.static.getToolsByTitle = function () {
	var toolsByTitle;
	if ( !this.toolsByTitle ) {
		this.toolsByTitle = toolsByTitle = {};
		( this.toolDefinitions[ this.name ] || [] ).forEach( function ( template ) {
			var titles = Array.isArray( template.title ) ? template.title : [ template.title ];
			// 'title' can be a single title, or list of titles (including redirects)
			titles.forEach( function ( title ) {
				toolsByTitle[ mw.Title.newFromText( title, mw.config.get( 'wgNamespaceIds' ).template ).getPrefixedText() ] = template;
			} );
		} );
	}
	return this.toolsByTitle;
};

/**
 * Get the tool definition that matches a specific model, if any
 *
 * @param {ve.dm.Model} model Model
 * @return {Object|null} Tool definition, or null if no match
 */
ve.ui.MWDefinedTransclusionContextItem.static.getMatchedTool = function ( model ) {
	var resource, title;
	resource = ve.getProp( model.getAttribute( 'mw' ), 'parts', 0, 'template', 'target', 'href' );
	if ( resource ) {
		title = mw.Title.newFromText( mw.libs.ve.normalizeParsoidResourceName( resource ) ).getPrefixedText();
		return this.getToolsByTitle()[ title ] || null;
	}
	return null;
};
