/*!
 * VisualEditor user interface MWTemplatePage class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki transclusion dialog template page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {ve.dm.MWTemplateModel} template Template model
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @cfg {jQuery} [$overlay] Overlay to render dropdowns in
 * @cfg {boolean} [isReadOnly] Page is read-only
 */
ve.ui.MWTemplatePage = function VeUiMWTemplatePage( template, name, config ) {
	var linkData, messageKey,
		title = template.getTitle() ? mw.Title.newFromText( template.getTitle() ) : null;

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTemplatePage.super.call( this, name, config );

	// Properties
	this.template = template;
	this.spec = template.getSpec();
	this.$more = $( '<div>' );
	this.$description = $( '<div>' );
	this.removeButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'trash',
		title: ve.msg( 'visualeditor-dialog-transclusion-remove-template' ),
		flags: [ 'destructive' ],
		classes: [ 've-ui-mwTransclusionDialog-removeButton' ]
	} )
		.connect( this, { click: 'onRemoveButtonClick' } );
	this.infoFieldset = new OO.ui.FieldsetLayout( {
		label: this.spec.getLabel(),
		icon: 'puzzle'
	} );
	this.addButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'parameter',
		label: ve.msg( 'visualeditor-dialog-transclusion-add-param' ),
		tabIndex: -1
	} )
		.connect( this, { click: 'onAddButtonFocus' } );

	// Initialization
	this.$description.addClass( 've-ui-mwTemplatePage-description' );
	if ( this.spec.getDescription() ) {
		this.$description.text( this.spec.getDescription() );
		if ( title ) {
			this.$description
				.append(
					$( '<hr>' ),
					$( '<span>' )
						.addClass( 've-ui-mwTemplatePage-description-extra' )
						.append( mw.message(
							'visualeditor-dialog-transclusion-more-template-description',
							title.getRelativeText( mw.config.get( 'wgNamespaceIds' ).template )
						).parseDom() )
				);
			ve.targetLinksToNewWindow( this.$description[ 0 ] );
		}
	} else {
		// The transcluded page may be dynamically generated or unspecified in the DOM
		// for other reasons (T68724). In that case we can't tell the user what the
		// template is called, nor link to the template page. However, if we know for
		// certain that the template doesn't exist, be explicit about it (T162694).
		if ( title ) {
			linkData = ve.init.platform.linkCache.getCached( '_missing/' + title );
			messageKey = linkData && linkData.missing ?
				'visualeditor-dialog-transclusion-absent-template' :
				'visualeditor-dialog-transclusion-no-template-description';

			this.$description
				.addClass( 've-ui-mwTemplatePage-description-missing' )
				// The following messages are used here:
				// * visualeditor-dialog-transclusion-absent-template
				// * visualeditor-dialog-transclusion-no-template-description
				.append( mw.message( messageKey, title.getPrefixedText() ).parseDom() );
			ve.targetLinksToNewWindow( this.$description[ 0 ] );
		}
	}

	this.infoFieldset.$element.append( this.$description );
	this.$more
		.addClass( 've-ui-mwTemplatePage-more' )
		.append( this.addButton.$element );
	this.$element
		.addClass( 've-ui-mwTemplatePage' )
		.append( this.infoFieldset.$element );
	if ( !config.isReadOnly ) {
		this.$element.append( this.removeButton.$element, this.$more );
	}
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTemplatePage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWTemplatePage.prototype.setOutlineItem = function () {
	// Parent method
	ve.ui.MWTemplatePage.super.prototype.setOutlineItem.apply( this, arguments );

	if ( this.outlineItem ) {
		this.outlineItem
			.setIcon( 'puzzle' )
			.setMovable( true )
			.setRemovable( true )
			.setLabel( this.spec.getLabel() );
	}
};

ve.ui.MWTemplatePage.prototype.onRemoveButtonClick = function () {
	this.template.remove();
};

ve.ui.MWTemplatePage.prototype.onAddButtonFocus = function () {
	this.template.addParameter( new ve.dm.MWParameterModel( this.template ) );
};
