/*
 * VisualEditor user interface MWCitationDialog class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Dialog for inserting and editing MediaWiki citations.
 *
 * @class
 * @extends ve.ui.MWTemplateDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWCitationDialog = function VeUiMWCitationDialog( config ) {
	// Parent constructor
	ve.ui.MWCitationDialog.super.call( this, config );

	// Properties
	this.referenceModel = null;
	this.referenceNode = null;
	this.inDialog = '';
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCitationDialog, ve.ui.MWTemplateDialog );

/* Static Properties */

ve.ui.MWCitationDialog.static.name = 'citation';

/* Methods */

/**
 * Get the reference node to be edited.
 *
 * @return {ve.dm.MWReferenceNode|null} Reference node to be edited, null if none exists
 */
ve.ui.MWCitationDialog.prototype.getReferenceNode = function () {
	var selectedNode = this.getFragment().getSelectedNode();

	if ( selectedNode instanceof ve.dm.MWReferenceNode ) {
		return selectedNode;
	}

	return null;
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.getSelectedNode = function () {
	var branches, leaves, transclusionNode,
		referenceNode = this.getReferenceNode();

	if ( referenceNode ) {
		branches = referenceNode.getInternalItem().getChildren();
		leaves = branches &&
			branches.length === 1 &&
			branches[ 0 ].canContainContent() &&
			branches[ 0 ].getChildren();
		transclusionNode = leaves &&
			leaves.length === 1 &&
			leaves[ 0 ] instanceof ve.dm.MWTransclusionNode &&
			leaves[ 0 ];
	}

	return transclusionNode || null;
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.initialize = function ( data ) {
	// Parent method
	ve.ui.MWCitationDialog.super.prototype.initialize.call( this, data );

	// HACK: Use the same styling as single-mode transclusion dialog - this should be generalized
	this.$content.addClass( 've-ui-mwTransclusionDialog-single' );
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWCitationDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			data = data || {};
			this.inDialog = data.inDialog;

			// Initialization
			if ( this.selectedNode ) {
				this.referenceNode = this.getReferenceNode();
				if ( this.referenceNode ) {
					this.referenceModel = ve.dm.MWReferenceModel.static.newFromReferenceNode(
						this.referenceNode
					);
				}
			}
			this.actions.forEach( { actions: 'insert' }, function ( action ) {
				action.setLabel( ve.msg( 'visualeditor-dialog-action-insert' ) );
			} );
		}, this );
};

ve.ui.MWCitationDialog.prototype.onTransclusionReady = function () {
	// Parent method
	ve.ui.MWCitationDialog.super.prototype.onTransclusionReady.call( this );

	if ( !this.hasUsefulParameter() ) {
		this.actions.setAbilities( { apply: false, insert: false } );
	}
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.setPageByName = function ( param ) {
	var hasUsefulParameter = this.hasUsefulParameter();

	// Parent method
	ve.ui.MWCitationDialog.super.prototype.setPageByName.call( this, param );

	this.actions.setAbilities( { apply: hasUsefulParameter, insert: hasUsefulParameter } );
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.onAddParameterBeforeLoad = function ( page ) {
	var dialog = this,
		hasUsefulParameter = this.hasUsefulParameter();

	page.preLoad = true;
	page.valueInput.on( 'change', function () {
		dialog.actions.setAbilities( { apply: hasUsefulParameter, insert: hasUsefulParameter } );
	} );
};

/**
 * Works out whether there are any set parameters that aren't just placeholders
 *
 * @return {boolean}
 */
ve.ui.MWCitationDialog.prototype.hasUsefulParameter = function () {
	var foundUseful = false;
	$.each( this.bookletLayout.pages, function () {
		if (
			this instanceof ve.ui.MWParameterPage &&
			( !this.preLoad || this.valueInput.getValue() !== '' )
		) {
			foundUseful = true;
			return false;
		}
	} );
	return foundUseful;
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.getActionProcess = function ( action ) {
	var dialog = this;
	if (
		this.inDialog !== 'reference' &&
		( action === 'apply' || action === 'insert' )
	) {
		return new OO.ui.Process( function () {
			var deferred = $.Deferred();
			dialog.checkRequiredParameters().done( function () {
				var item,
					surfaceModel = dialog.getFragment().getSurface(),
					doc = surfaceModel.getDocument(),
					internalList = doc.getInternalList(),
					obj = dialog.transclusionModel.getPlainObject();

				if ( !dialog.referenceModel ) {
					// Collapse returns a new fragment, so update dialog.fragment
					dialog.fragment = dialog.getFragment().collapseToEnd();
					dialog.referenceModel = new ve.dm.MWReferenceModel( doc );
					dialog.referenceModel.insertInternalItem( surfaceModel );
					dialog.referenceModel.insertReferenceNode( dialog.getFragment() );
				}

				item = dialog.referenceModel.findInternalItem( surfaceModel );
				if ( item ) {
					if ( dialog.selectedNode ) {
						dialog.transclusionModel.updateTransclusionNode(
							surfaceModel, dialog.selectedNode
						);
					} else if ( obj !== null ) {
						dialog.transclusionModel.insertTransclusionNode(
							// HACK: This is trying to place the cursor inside the first content branch
							// node but this theoretically not a safe assumption - in practice, the
							// citation dialog will only reach this code if we are inserting (not
							// updating) a transclusion, so the referenceModel will have already
							// initialized the internal node with a paragraph - getting the range of the
							// item covers the entire paragraph so we have to get the range of it's
							// first (and empty) child
							dialog.getFragment().clone(
								new ve.dm.LinearSelection( doc, item.getChildren()[ 0 ].getRange() )
							),
							'inline'
						);
					}
				}

				// HACK: Scorch the earth - this is only needed because without it, the references list
				// won't re-render properly, and can be removed once someone fixes that
				dialog.referenceModel.setDocument(
					doc.cloneFromRange(
						internalList.getItemNode( dialog.referenceModel.getListIndex() ).getRange()
					)
				);
				dialog.referenceModel.updateInternalItem( surfaceModel );

				dialog.close( { action: action } );
			} ).always( deferred.resolve );

			return deferred;
		} );
	}

	// Parent method
	return ve.ui.MWCitationDialog.super.prototype.getActionProcess.call( this, action );
};

/**
 * @inheritdoc
 */
ve.ui.MWCitationDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWCitationDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			// Cleanup
			this.referenceModel = null;
			this.referenceNode = null;
		}, this );
};
