/*!
 * VisualEditor MWIncludesContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for a MWIncludesContextItem.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @constructor
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.MWIncludesContextItem = function VeUiMWIncludesContextItem() {
	// Parent constructor
	ve.ui.MWIncludesContextItem.super.apply( this, arguments );

	// Initialization
	this.$element.addClass( 've-ui-mwIncludesContextItem' );

	this.setLabel( this.getLabelMessage() );

	this.$actions.remove();
};

/* Inheritance */

OO.inheritClass( ve.ui.MWIncludesContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.MWIncludesContextItem.static.editable = false;

ve.ui.MWIncludesContextItem.static.name = 'mwIncludes';

ve.ui.MWIncludesContextItem.static.icon = 'markup';

ve.ui.MWIncludesContextItem.static.modelClasses = [
	ve.dm.MWIncludesNode
];

/* Methods */

ve.ui.MWIncludesContextItem.prototype.getLabelMessage = function () {
	var map = {
		'mw:Includes/NoInclude': mw.message( 'visualeditor-includes-noinclude-start' ).text(),
		'mw:Includes/NoInclude/End': mw.message( 'visualeditor-includes-noinclude-end' ).text(),
		'mw:Includes/OnlyInclude': mw.message( 'visualeditor-includes-onlyinclude-start' ).text(),
		'mw:Includes/OnlyInclude/End': mw.message( 'visualeditor-includes-onlyinclude-end' ).text(),
		'mw:Includes/IncludeOnly': mw.message( 'visualeditor-includes-includeonly' ).text()
	};
	return map[ this.model.getAttribute( 'type' ) ];
};

ve.ui.MWIncludesContextItem.prototype.getDescriptionMessage = function () {
	var map = {
		'mw:Includes/NoInclude': mw.message( 'visualeditor-includes-noinclude-description' ).parseDom(),
		'mw:Includes/OnlyInclude': mw.message( 'visualeditor-includes-onlyinclude-description' ).parseDom(),
		'mw:Includes/IncludeOnly': mw.message( 'visualeditor-includes-includeonly-description' ).parseDom()
	};
	return map[ this.model.getAttribute( 'type' ) ] || '';
};

/**
 * @inheritdoc
 */
ve.ui.MWIncludesContextItem.prototype.renderBody = function () {
	var wikitext;

	this.$body.empty();

	this.$body.append( this.getDescriptionMessage() ).append( mw.msg( 'word-separator' ) );

	if ( this.model.getAttribute( 'mw' ) ) {
		wikitext = this.model.getAttribute( 'mw' ).src;
		// The opening and closing tags are included, eww
		wikitext = wikitext.replace( /^<includeonly>\s*([\s\S]*)\s*<\/includeonly>$/, '$1' );
		this.$body.append( $( '<pre>' )
			// The following classes are used here:
			// * mw-editfont-monospace
			// * mw-editfont-sans-serif
			// * mw-editfont-serif
			.addClass( 'mw-editfont-' + mw.user.options.get( 'editfont' ) )
			.text( wikitext )
		);
	}

	this.$body.append( mw.message( 'visualeditor-includes-documentation' ).parseDom() );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.MWIncludesContextItem );
