/*!
 * VisualEditor user interface MWTransclusionDialog class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Dialog for inserting and editing MediaWiki transclusions.
 *
 * @class
 * @extends ve.ui.MWTemplateDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWTransclusionDialog = function VeUiMWTransclusionDialog( config ) {
	// Parent constructor
	ve.ui.MWTransclusionDialog.super.call( this, config );

	// Properties
	this.mode = null;
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionDialog, ve.ui.MWTemplateDialog );

/* Static Properties */

ve.ui.MWTransclusionDialog.static.name = 'transclusion';

ve.ui.MWTransclusionDialog.static.title =
	OO.ui.deferMsg( 'visualeditor-dialog-transclusion-title' );

ve.ui.MWTransclusionDialog.static.actions = ve.ui.MWTemplateDialog.static.actions.concat( [
	{
		action: 'mode',
		modes: [ 'edit', 'insert' ],
		// HACK: Will be set later, but we want measurements to be accurate in the mean time, this
		// will not be needed when T93290 is resolved
		label: $( document.createTextNode( '\u00a0' ) )
	}
] );

/**
 * Map of symbolic mode names and CSS classes.
 *
 * @static
 * @property {Object}
 * @inheritable
 */
ve.ui.MWTransclusionDialog.static.modeCssClasses = {
	single: 've-ui-mwTransclusionDialog-single',
	multiple: 've-ui-mwTransclusionDialog-multiple'
};

ve.ui.MWTransclusionDialog.static.bookletLayoutConfig = ve.extendObject(
	{},
	ve.ui.MWTemplateDialog.static.bookletLayoutConfig,
	{ outlined: true, editable: true }
);

/* Methods */

/**
 * Handle outline controls move events.
 *
 * @param {number} places Number of places to move the selected item
 */
ve.ui.MWTransclusionDialog.prototype.onOutlineControlsMove = function ( places ) {
	var part, promise,
		parts = this.transclusionModel.getParts(),
		item = this.bookletLayout.getOutline().findSelectedItem();

	if ( item ) {
		part = this.transclusionModel.getPartFromId( item.getData() );
		// Move part to new location, and if dialog is loaded switch to new part page
		promise = this.transclusionModel.addPart( part, parts.indexOf( part ) + places );
		if ( this.loaded && !this.preventReselection ) {
			promise.done( this.setPageByName.bind( this, part.getId() ) );
		}
	}
};

/**
 * Handle outline controls remove events.
 */
ve.ui.MWTransclusionDialog.prototype.onOutlineControlsRemove = function () {
	var id, part, param,
		item = this.bookletLayout.getOutline().findSelectedItem();

	if ( item ) {
		id = item.getData();
		part = this.transclusionModel.getPartFromId( id );
		// Check if the part is the actual template, or one of its parameters
		if ( part instanceof ve.dm.MWTemplateModel && id !== part.getId() ) {
			param = part.getParameterFromId( id );
			if ( param instanceof ve.dm.MWParameterModel ) {
				part.removeParameter( param );
			}
		} else if ( part instanceof ve.dm.MWTransclusionPartModel ) {
			this.transclusionModel.removePart( part );
		}
	}
};

/**
 * Handle add template button click events.
 */
ve.ui.MWTransclusionDialog.prototype.onAddTemplateButtonClick = function () {
	this.addPart( new ve.dm.MWTemplatePlaceholderModel( this.transclusionModel ) );
};

/**
 * Handle add content button click events.
 */
ve.ui.MWTransclusionDialog.prototype.onAddContentButtonClick = function () {
	this.addPart( new ve.dm.MWTransclusionContentModel( this.transclusionModel, '' ) );
};

/**
 * Handle add parameter button click events.
 */
ve.ui.MWTransclusionDialog.prototype.onAddParameterButtonClick = function () {
	var part, param,
		item = this.bookletLayout.getOutline().findSelectedItem();

	if ( item ) {
		part = this.transclusionModel.getPartFromId( item.getData() );
		if ( part instanceof ve.dm.MWTemplateModel ) {
			param = new ve.dm.MWParameterModel( part, '', null );
			part.addParameter( param );
		}
	}
};

/**
 * Handle booklet layout page set events.
 *
 * @param {OO.ui.PageLayout} page Active page
 */
ve.ui.MWTransclusionDialog.prototype.onBookletLayoutSet = function ( page ) {
	this.addParameterButton.setDisabled(
		!( page instanceof ve.ui.MWTemplatePage || page instanceof ve.ui.MWParameterPage ) ||
		this.isReadOnly()
	);
	this.bookletLayout.getOutlineControls().removeButton.toggle( !(
		(
			page instanceof ve.ui.MWParameterPage &&
			page.parameter.isRequired()
		) || (
			this.transclusionModel.getParts().length === 1 &&
			page instanceof ve.ui.MWTemplatePlaceholderPage
		)
	) );
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.onReplacePart = function ( removed, added ) {
	var single;

	ve.ui.MWTransclusionDialog.super.prototype.onReplacePart.call( this, removed, added );

	if ( this.transclusionModel.getParts().length === 0 ) {
		this.addParameterButton.setDisabled( true );
		this.addPart( new ve.dm.MWTemplatePlaceholderModel( this.transclusionModel ) );
	}

	single = this.isSingleTemplateTransclusion();
	this.actions.setAbilities( { mode: single } );
};

/**
 * Checks if transclusion only contains a single template or template placeholder.
 *
 * @return {boolean} Transclusion only contains a single template or template placeholder
 */
ve.ui.MWTransclusionDialog.prototype.isSingleTemplateTransclusion = function () {
	var parts = this.transclusionModel && this.transclusionModel.getParts();

	return parts && parts.length === 1 && (
		parts[ 0 ] instanceof ve.dm.MWTemplateModel ||
		parts[ 0 ] instanceof ve.dm.MWTemplatePlaceholderModel
	);
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getPageFromPart = function ( part ) {
	var page = ve.ui.MWTransclusionDialog.super.prototype.getPageFromPart.call( this, part );
	if ( !page && part instanceof ve.dm.MWTransclusionContentModel ) {
		return new ve.ui.MWTransclusionContentPage( part, part.getId(), { $overlay: this.$overlay, isReadOnly: this.isReadOnly() } );
	}
	return page;
};

/**
 * Set dialog mode.
 *
 * Auto mode will choose single if possible.
 *
 * @param {string} [mode='multiple'] Symbolic name of dialog mode, `multiple`, `single` or 'auto'
 */
ve.ui.MWTransclusionDialog.prototype.setMode = function ( mode ) {
	var name, single,
		modeCssClasses = ve.ui.MWTransclusionDialog.static.modeCssClasses;

	if ( this.transclusionModel ) {
		if ( mode === 'auto' ) {
			mode = this.isSingleTemplateTransclusion() ? 'single' : 'multiple';
		}
	}
	if ( !modeCssClasses[ mode ] ) {
		mode = 'multiple';
	}

	if ( this.mode !== mode ) {
		this.mode = mode;
		single = mode === 'single';
		if ( this.$content ) {
			for ( name in modeCssClasses ) {
				// See static.modeCssClasses
				// eslint-disable-next-line mediawiki/class-doc
				this.$content.toggleClass( modeCssClasses[ name ], name === mode );
			}
		}
		this.setSize( single ? 'medium' : 'large' );
		this.bookletLayout.toggleOutline( !single );
		this.updateTitle();
		this.updateModeActionState();

		// HACK blur any active input so that its dropdown will be hidden and won't end
		// up being mispositioned
		this.$content.find( 'input:focus' ).trigger( 'blur' );
	}
};

/**
 * Update the dialog title.
 */
ve.ui.MWTransclusionDialog.prototype.updateTitle = function () {
	if ( this.mode === 'multiple' ) {
		this.title.setLabel( this.constructor.static.title );
	} else {
		// Parent method
		ve.ui.MWTransclusionDialog.super.prototype.updateTitle.call( this );
	}
};

/**
 * Update the state of the 'mode' action
 */
ve.ui.MWTransclusionDialog.prototype.updateModeActionState = function () {
	var parts = this.transclusionModel && this.transclusionModel.getParts(),
		mode = this.mode;
	this.actions.forEach( { actions: [ 'mode' ] }, function ( action ) {
		var label, expanded;
		if ( mode === 'single' ) {
			label = ve.msg( 'visualeditor-dialog-transclusion-multiple-mode' );
			expanded = false;
		} else {
			label = ve.msg( 'visualeditor-dialog-transclusion-single-mode' );
			expanded = true;
		}
		action.setLabel( label );
		action.$button.attr( 'aria-expanded', expanded.toString() );
	} );

	// Decide whether the button should be enabled or not. It needs to be:
	// * disabled when we're in the initial add-new-template phase, because it's
	//   meaningless
	// * disabled if we're in a multi-part transclusion, because the sidebar's
	//   forced open
	// * enabled if we're in a single-part transclusion, because the sidebar's
	//   closed but can be opened to add more parts
	if ( parts ) {
		if ( parts.length === 1 && parts[ 0 ] instanceof ve.dm.MWTemplatePlaceholderModel ) {
			// Initial new-template phase: button is meaningless
			this.actions.setAbilities( { mode: false } );
		} else if ( !this.isSingleTemplateTransclusion() ) {
			// Multi-part transclusion: button disabled because sidebar forced-open
			this.actions.setAbilities( { mode: false } );
		} else {
			// Single-part transclusion: button enabled because sidebar is optional
			this.actions.setAbilities( { mode: true } );
		}
	}
};

/**
 * Add a part to the transclusion.
 *
 * @param {ve.dm.MWTransclusionPartModel} part Part to add
 */
ve.ui.MWTransclusionDialog.prototype.addPart = function ( part ) {
	var index, promise,
		parts = this.transclusionModel.getParts(),
		item = this.bookletLayout.getOutline().findSelectedItem();

	if ( part ) {
		// Insert after selected part, or at the end if nothing is selected
		index = item ?
			parts.indexOf( this.transclusionModel.getPartFromId( item.getData() ) ) + 1 :
			parts.length;
		// Add the part, and if dialog is loaded switch to part page
		promise = this.transclusionModel.addPart( part, index );
		if ( this.loaded && !this.preventReselection ) {
			promise.done( this.setPageByName.bind( this, part.getId() ) );
		}
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'mode' ) {
		return new OO.ui.Process( function () {
			this.setMode( this.mode === 'single' ? 'multiple' : 'single' );
		}, this );
	}

	return ve.ui.MWTransclusionDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWTransclusionDialog.super.prototype.initialize.call( this );

	// Properties
	this.addTemplateButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'puzzle',
		title: ve.msg( 'visualeditor-dialog-transclusion-add-template' )
	} );
	this.addContentButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'wikiText',
		title: ve.msg( 'visualeditor-dialog-transclusion-add-content' )
	} );
	this.addParameterButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'parameter',
		title: ve.msg( 'visualeditor-dialog-transclusion-add-param' )
	} );

	// Events
	this.bookletLayout.connect( this, { set: 'onBookletLayoutSet' } );
	this.bookletLayout.$menu.find( '[ role="listbox" ]' ).first().attr( 'aria-label', ve.msg( 'visualeditor-dialog-transclusion-templates-menu-aria-label' ) );
	this.addTemplateButton.connect( this, { click: 'onAddTemplateButtonClick' } );
	this.addContentButton.connect( this, { click: 'onAddContentButtonClick' } );
	this.addParameterButton.connect( this, { click: 'onAddParameterButtonClick' } );
	this.bookletLayout.getOutlineControls()
		.addItems( [ this.addTemplateButton, this.addContentButton, this.addParameterButton ] )
		.connect( this, {
			move: 'onOutlineControlsMove',
			remove: 'onOutlineControlsRemove'
		} );
};

/**
 * @inheritdoc
 */
ve.ui.MWTransclusionDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWTransclusionDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var isReadOnly = this.isReadOnly();
			this.addTemplateButton.setDisabled( isReadOnly );
			this.addContentButton.setDisabled( isReadOnly );
			this.addParameterButton.setDisabled( isReadOnly );
			this.bookletLayout.getOutlineControls().setAbilities( {
				move: !isReadOnly,
				remove: !isReadOnly
			} );

			this.updateModeActionState();
			this.setMode( 'auto' );
		}, this );
};

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWTransclusionDialog );
