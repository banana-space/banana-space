( function () {
	'use strict';

	// Based partly on ve.ui.MWTemplateDialog
	/**
	 * Inspector for editing Flow mentions.  This is a friendly
	 * UI for a transclusion (e.g. {{ping}}, template varies by wiki).
	 *
	 * @class
	 * @extends ve.ui.NodeInspector
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 */
	mw.flow.ve.ui.MentionInspector = function FlowVeMentionInspector() {
		// Parent constructor
		mw.flow.ve.ui.MentionInspector.super.apply( this, arguments );

		// this.selectedNode is the ve.dm.MWTransclusionNode, which we inherit
		// from ve.ui.NodeInspector.
		//
		// The templateModel (used locally some places) is a sub-part of the transclusion
		// model.
		this.transclusionModel = null;
		this.loaded = false;
		this.altered = false;

		this.targetInput = null;
		this.errorWidget = null;
		this.errorFieldsetLayout = null;
		this.selectedAt = false;
	};

	OO.inheritClass( mw.flow.ve.ui.MentionInspector, ve.ui.NodeInspector );

	// Static

	mw.flow.ve.ui.MentionInspector.static.name = 'flowMention';
	mw.flow.ve.ui.MentionInspector.static.size = 'medium';
	mw.flow.ve.ui.MentionInspector.static.title = OO.ui.deferMsg( 'flow-ve-mention-inspector-title' );
	mw.flow.ve.ui.MentionInspector.static.modelClasses = [ ve.dm.MWTransclusionNode ];

	mw.flow.ve.ui.MentionInspector.static.template = mw.config.get( 'wgFlowMentionTemplate' );
	mw.flow.ve.ui.MentionInspector.static.templateParameterKey = '1'; // 1-indexed positional parameter

	// Buttons
	mw.flow.ve.ui.MentionInspector.static.actions = [
		{
			action: 'remove',
			label: OO.ui.deferMsg( 'flow-ve-mention-inspector-remove-label' ),
			flags: [ 'destructive' ],
			modes: 'edit'
		}
	].concat( mw.flow.ve.ui.MentionInspector.super.static.actions );

	// Instance Methods

	/**
	 * Handle changes to the input widget
	 */
	mw.flow.ve.ui.MentionInspector.prototype.onTargetInputChange = function () {
		var templateModel, parameterModel, key, value, inspector;

		this.hideErrors();

		key = mw.flow.ve.ui.MentionInspector.static.templateParameterKey;
		value = this.targetInput.getValue();
		inspector = this;

		this.pushPending();
		this.targetInput.isValid().done( function ( isValid ) {
			if ( isValid ) {
				// After the updates are done, we'll get onTransclusionModelChange
				templateModel = inspector.transclusionModel.getParts()[ 0 ];
				if ( templateModel.hasParameter( key ) ) {
					parameterModel = templateModel.getParameter( key );
					parameterModel.setValue( value );
				} else {
					parameterModel = new ve.dm.MWParameterModel(
						templateModel,
						key,
						value
					);
					templateModel.addParameter( parameterModel );
				}
			} else {
				// Disable save button
				inspector.setApplicableStatus();
			}
		} ).always( function () {
			inspector.popPending();
		} );
	};

	/**
	 * Handle the transclusion becoming ready
	 */
	mw.flow.ve.ui.MentionInspector.prototype.onTransclusionReady = function () {
		var templateModel, key;

		key = mw.flow.ve.ui.MentionInspector.static.templateParameterKey;

		this.loaded = true;
		this.$element.addClass( 'flow-ve-ui-mentionInspector-ready' );
		this.popPending();

		templateModel = this.transclusionModel.getParts()[ 0 ];
		if ( templateModel.hasParameter( key ) ) {
			this.targetInput.setValue( templateModel.getParameter( key ).getValue() );
		}
	};

	/**
	 * Handles the transclusion model changing.  This should only happen when we change
	 * the parameter, then get a callback.
	 */
	mw.flow.ve.ui.MentionInspector.prototype.onTransclusionModelChange = function () {
		if ( this.loaded ) {
			this.altered = true;
			this.setApplicableStatus();
		}
	};

	/**
	 * Sets the abilities based on the current status
	 *
	 * If it's empty or invalid, it can not be inserted or updated.
	 */
	mw.flow.ve.ui.MentionInspector.prototype.setApplicableStatus = function () {
		var parts = this.transclusionModel.getParts(),
			templateModel = parts[ 0 ],
			key = mw.flow.ve.ui.MentionInspector.static.templateParameterKey,
			inspector = this;

		// The template should always be there; the question is whether the first/only
		// positional parameter is.
		//
		// If they edit an existing mention, and make it invalid, they should be able
		// to cancel, but not save.
		if ( templateModel.hasParameter( key ) ) {
			this.pushPending();
			this.targetInput.isValid().done( function ( isValid ) {
				inspector.actions.setAbilities( { done: isValid } );
			} ).always( function () {
				inspector.popPending();
			} );
		} else {
			inspector.actions.setAbilities( { done: false } );
		}
	};

	/**
	 * Initialize UI of inspector
	 */
	mw.flow.ve.ui.MentionInspector.prototype.initialize = function () {
		var flowBoard, overlay, iconWidget;

		// Parent method
		mw.flow.ve.ui.MentionInspector.super.prototype.initialize.apply( this, arguments );

		// I would much prefer to use dependency injection to get the list of topic posters
		// into the inspector, but I haven't been able to figure out how to pass it through
		// yet.

		// TODO: This will return false for suface widgets in the global overlay.
		// Fix this so that it always finds the board associated with the root surface.
		flowBoard = mw.flow.getPrototypeMethod( 'board', 'getInstanceByElement' )(
			this.$element
		);

		// Properties
		overlay = this.manager.getOverlay();
		this.targetInput = new mw.flow.ve.ui.MentionTargetInputWidget( {
			$overlay: overlay ? overlay.$element : this.$frame,
			topicPosters: flowBoard ? flowBoard.getTopicPosters( this.$element ) : []
		} );
		iconWidget = new OO.ui.IconWidget( {
			icon: 'notice'
		} );
		this.errorWidget = new OO.ui.FieldLayout( iconWidget, {
			align: 'inline'
		} );
		this.errorFieldsetLayout = new OO.ui.FieldsetLayout( {
			items: [
				this.errorWidget
			]
		} );

		// Initialization
		this.$content.addClass( 'flow-ve-ui-mentionInspector-content' );
		this.errorFieldsetLayout.toggle( false );
		this.form.addItems( [
			this.errorFieldsetLayout
		] );
		this.form.$element.append( this.targetInput.$element );
	};

	mw.flow.ve.ui.MentionInspector.prototype.getActionProcess = function ( action ) {
		var deferred, inspector,
			surfaceModel = this.getFragment().getSurface();

		if ( action === 'done' ) {
			deferred = $.Deferred();
			inspector = this;

			this.targetInput.isValid().done( function ( isValid ) {
				var transclusionModelPlain;

				if ( isValid ) {
					transclusionModelPlain = inspector.transclusionModel.getPlainObject();

					// Should be either null or the right template
					if ( inspector.selectedNode instanceof ve.dm.MWTransclusionNode ) {
						inspector.transclusionModel.updateTransclusionNode( surfaceModel, inspector.selectedNode );
					} else if ( transclusionModelPlain !== null ) {
						// Insert at the end of the fragment, unless we have an '@' selected, in which
						// case leave it selected so it gets removed.
						if ( !inspector.selectedAt ) {
							inspector.fragment = inspector.getFragment().collapseToEnd();
						}
						inspector.transclusionModel.insertTransclusionNode( inspector.getFragment(), 'inline' );
						// After insertion move cursor to end of template
						inspector.fragment.collapseToEnd().select();
					}

					inspector.close( { action: action } );
					deferred.resolve();
				} else {
					deferred.reject( new OO.ui.Error( OO.ui.msg( 'flow-ve-mention-inspector-invalid-user', inspector.targetInput.getValue() ) ) );
				}
			} );

			return new OO.ui.Process( deferred.promise() );
		} else if ( action === 'remove' ) {
			return new OO.ui.Process( function () {
				this.getFragment().removeContent();

				this.close( { action: action } );
			}, this );
		}

		// Parent method
		return mw.flow.ve.ui.MentionInspector.super.prototype.getActionProcess.apply( this, arguments );
	};

	// Technically, these are private.  However, it's necessary to override them (and not call
	// the parent), since otherwise this UI (which was probably designed for dialogs) does not fit the inspector.
	// Only handles on error at a time for now.
	//
	// It would be nice to implement a general solution for this that covers all inspectors (or
	// maybe a mixin for inline errors next to form elements).

	mw.flow.ve.ui.MentionInspector.prototype.showErrors = function ( errors ) {
		var errorText;

		if ( errors instanceof OO.ui.Error ) {
			errors = [ errors ];
		}

		errorText = errors[ 0 ].getMessageText();
		this.errorWidget.setLabel( errorText );
		this.errorFieldsetLayout.toggle( true );
		this.setSize( 'large' );
	};

	mw.flow.ve.ui.MentionInspector.prototype.hideErrors = function () {
		this.errorFieldsetLayout.toggle( false );
		this.errorWidget.setLabel( '' );
		this.setSize( 'medium' );
	};

	/**
	 * Pre-populate the username based on the node
	 *
	 * @param {Object} [data] Inspector initial data
	 * @param {boolean} [data.selectAt] Select the '@' symbol to the left of the fragment
	 * @return {OO.ui.Process}
	 */
	mw.flow.ve.ui.MentionInspector.prototype.getSetupProcess = function ( data ) {
		// Parent method
		return mw.flow.ve.ui.MentionInspector.super.prototype.getSetupProcess.apply( this, arguments )
			.next( function () {
				var templateModel, promise, atFragment;

				this.loaded = false;
				this.altered = false;
				// MWTransclusionModel has some unnecessary behavior for our use
				// case, mainly templatedata lookups.
				this.transclusionModel = new ve.dm.MWTransclusionModel();

				// Events
				this.transclusionModel.connect( this, {
					change: 'onTransclusionModelChange'
				} );

				this.targetInput.connect( this, {
					change: 'onTargetInputChange'
				} );

				// Initialization
				if ( !this.selectedNode ) {
					this.actions.setMode( 'insert' );
					templateModel = ve.dm.MWTemplateModel.newFromName(
						this.transclusionModel,
						mw.flow.ve.ui.MentionInspector.static.template
					);
					promise = this.transclusionModel.addPart( templateModel );
				} else {
					this.actions.setMode( 'edit' );

					// Load existing ping
					promise = this.transclusionModel
						.load( ve.copy( this.selectedNode.getAttribute( 'mw' ) ) );
				}

				if ( data.selectAt ) {
					atFragment = this.getFragment().adjustLinearSelection( -1, 0 );
					if ( atFragment.getText() === '@' ) {
						this.fragment = atFragment.select();
						this.selectedAt = true;
					}
				}

				// Don't allow saving until we're sure it's valid.
				this.actions.setAbilities( { done: false } );
				this.pushPending();
				promise.always( this.onTransclusionReady.bind( this ) );
			}, this );
	};

	mw.flow.ve.ui.MentionInspector.prototype.getReadyProcess = function () {
		// Parent method
		return mw.flow.ve.ui.MentionInspector.super.prototype.getReadyProcess.apply( this, arguments )
			.next( function () {
				this.targetInput.focus();
			}, this );
	};

	mw.flow.ve.ui.MentionInspector.prototype.getTeardownProcess = function () {
		// Parent method
		return mw.flow.ve.ui.MentionInspector.super.prototype.getTeardownProcess.apply( this, arguments )
			.first( function () {
				// Cleanup
				this.$element.removeClass( 'flow-ve-ui-mentionInspector-ready' );
				this.transclusionModel.disconnect( this );
				this.transclusionModel.abortRequests();
				this.transclusionModel = null;

				this.targetInput.disconnect( this );

				this.targetInput.setValue( '' );
				if ( this.selectedAt ) {
					this.fragment.collapseToEnd().select();
				}
				this.selectedAt = false;

			}, this );
	};

	/**
	 * Gets the transclusion node representing this mention
	 *
	 * @return {ve.dm.Node|null} Selected node
	 */
	mw.flow.ve.ui.MentionInspector.prototype.getSelectedNode = function () {
		// Parent method
		var node = mw.flow.ve.ui.MentionInspector.super.prototype.getSelectedNode.apply( this, arguments );
		// Checks the model class
		if ( node && node.isSingleTemplate( mw.flow.ve.ui.MentionInspector.static.template ) ) {
			return node;
		}

		return null;
	};

	ve.ui.windowFactory.register( mw.flow.ve.ui.MentionInspector );
}() );
