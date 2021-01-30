( function () {
	/**
	 * Flow new topic widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {string} page The page name, including namespace, that the
	 *  board of this topic belongs to.
	 * @param {Object} [config] Configuration object
	 * @cfg {Object} [editor] Config options to pass to mw.flow.ui.EditorWidget
	 */
	mw.flow.ui.NewTopicWidget = function mwFlowUiNewTopicWidget( page, config ) {
		var title,
			widget = this;

		config = config || {};

		// Parent constructor
		mw.flow.ui.NewTopicWidget.super.call( this, config );

		this.isProbablyEditable = mw.config.get( 'wgIsProbablyEditable' );

		title = mw.Title.newFromText( page );
		if ( title !== null ) {
			this.page = title.getPrefixedText();
		} else {
			this.page = page;
		}

		this.expanded = false;

		this.api = new mw.flow.dm.APIHandler( this.page );

		this.anonWarning = new mw.flow.ui.AnonWarningWidget( {
			isProbablyEditable: this.isProbablyEditable
		} );
		this.anonWarning.toggle( false );

		this.canNotEdit = new mw.flow.ui.CanNotEditWidget( this.api, {
			userGroups: mw.config.get( 'wgUserGroups' ),
			restrictionEdit: mw.config.get( 'wgRestrictionEdit' ),
			isProbablyEditable: this.isProbablyEditable
		} );
		this.canNotEdit.toggle( false );

		this.id = 'new-topic/' + mw.flow.system.boardId;

		this.title = new OO.ui.TextInputWidget( {
			placeholder: mw.msg( 'flow-newtopic-start-placeholder' ),
			classes: [ 'flow-ui-newTopicWidget-title' ],
			value: mw.storage.session.get( this.id + '/title' )
		} );
		this.title.$input
			.attr( 'aria-label', mw.msg( 'flow-newtopic-start-placeholder' ) );

		this.editor = new mw.flow.ui.EditorWidget( $.extend( {
			placeholder: mw.msg( 'flow-newtopic-content-placeholder', this.page ),
			saveMsgKey: mw.user.isAnon() ? 'flow-newtopic-save-anonymously' : 'flow-newtopic-save',
			autoFocus: false,
			classes: [ 'flow-ui-newTopicWidget-editor' ],
			saveable: this.isProbablyEditable,
			leaveCallback: function () {
				if ( widget.title.getValue() !== '' ) {
					return false;
				}
			},
			id: this.id
		}, config.editor ) );
		this.editor.toggle( false );

		this.captcha = new mw.flow.dm.Captcha();
		this.captchaWidget = new mw.flow.ui.CaptchaWidget( this.captcha );

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-newTopicWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		// Events
		this.editor.connect( this, {
			change: 'updateFormState',
			saveContent: 'onEditorSaveContent',
			cancel: 'onEditorCancel'
		} );
		this.editor.once( 'switch', function ( promise ) {
			// Listen to editor switch event and re-enable auto-focus.
			// This is done so that in the first construction of the widget the
			// focus stays in the title widget and is not stolen by the editor.
			promise.always( function () {
				widget.editor.toggleAutoFocus( true );
			} );
		} );

		this.title.connect( this, {
			change: 'updateFormState'
		} );
		this.title.$element.on( {
			focusin: this.onTitleFocusIn.bind( this ),
			keydown: this.onTitleKeydown.bind( this )
		} );

		// Initialization
		this.updateFormState();

		this.$messages = $( '<div>' ).addClass( 'flow-ui-editorContainerWidget-messages' );

		this.$element
			.addClass( 'flow-ui-newTopicWidget' )
			.append(
				this.$messages.append(
					this.anonWarning.$element,
					this.canNotEdit.$element,
					this.error.$element,
					this.captchaWidget.$element
				),
				this.title.$element,
				this.editor.$element
			);
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.NewTopicWidget, OO.ui.Widget );

	/**
	 * Update the state of the form.
	 *
	 * @private
	 */
	mw.flow.ui.NewTopicWidget.prototype.updateFormState = function () {
		var isDisabled = this.isExpanded() && !this.isProbablyEditable;

		this.title.setDisabled( isDisabled );
		this.editor.setDisabled( isDisabled );

		mw.storage.session.set( this.id + '/title', this.title.getValue() );

		this.editor.editorControlsWidget.toggleSaveable(
			this.isProbablyEditable &&
			this.title.getValue() &&
			!this.editor.isEmpty()
		);
	};

	/**
	 * Respond to title input focusin event
	 *
	 * @private
	 */
	mw.flow.ui.NewTopicWidget.prototype.onTitleFocusIn = function () {
		this.activate();
	};

	/**
	 * Expand the widget and make it ready to create a new topic
	 *
	 * @param {Object} content Content to preload into the editor
	 * @param {string} content.content Content
	 * @param {string} content.format Format of content ('html' or 'wikitext')
	 */
	mw.flow.ui.NewTopicWidget.prototype.activate = function ( content ) {
		var widget = this;
		if ( !this.isExpanded() ) {
			// Expand the editor
			this.toggleExpanded( true );
			this.editor.toggleAutoFocus( false );
			this.editor.activate( content ).then( function () {
				widget.updateFormState();
				widget.title.focus();
				widget.editor.toggleAutoFocus( true );
			} );
		}
	};

	/**
	 * Preload the widget with title and content.
	 *
	 * @param {string} title
	 * @param {string} content
	 * @param {string} format
	 */
	mw.flow.ui.NewTopicWidget.prototype.preload = function ( title, content, format ) {
		if ( content && format ) {
			this.activate( { content: content, format: format } );
		} else {
			this.activate();
		}
		this.title.setValue( title );
	};

	/**
	 * Respond to keydown events in title input, and cancel when escape is pressed.
	 *
	 * @param {jQuery.Event} e Keydown event
	 */
	mw.flow.ui.NewTopicWidget.prototype.onTitleKeydown = function ( e ) {
		if ( e.which === OO.ui.Keys.ESCAPE ) {
			// Trigger editor cancel, potentially prompting about discarding changes
			this.editor.onEditorControlsWidgetCancel();
			e.preventDefault();
			e.stopPropagation();
		}
	};

	/**
	 * Respond to editor save event
	 *
	 * @param {string} content Content
	 * @param {string} format Content format
	 */
	mw.flow.ui.NewTopicWidget.prototype.onEditorSaveContent = function ( content, format ) {
		var widget = this,
			title = this.title.getValue(),
			captchaResponse;

		this.editor.pushPending();
		this.title.pushPending();
		this.title.setDisabled( true );

		captchaResponse = this.captchaWidget.getResponse();

		this.error.setLabel( '' );
		this.error.toggle( false );

		this.api.saveNewTopic( title, content, format, captchaResponse )
			.then( function ( topicId ) {
				widget.captchaWidget.toggle( false );

				widget.toggleExpanded( false );
				widget.emit( 'save', topicId );
			} )
			.catch( function ( errorCode, errorObj ) {
				widget.captcha.update( errorCode, errorObj );
				if ( !widget.captcha.isRequired() ) {
					widget.error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
					widget.error.toggle( true );
				}
				// Prevent the promise from becoming resolved after this step
				return $.Deferred().reject().promise();
			} )
			.always( function () {
				widget.editor.popPending();
				widget.title.popPending();
				widget.title.setDisabled( false );
			} )
			.done( function () {
				// Clear for next use
				widget.title.setValue( '' );
				widget.editor.clearContent();
				widget.updateFormState();
			} );
	};

	/**
	 * Respond to editor cancel event
	 */
	mw.flow.ui.NewTopicWidget.prototype.onEditorCancel = function () {
		// Hide the editor
		this.toggleExpanded( false );
		// Take focus away from the title input, if it was focused (T109353)
		this.title.blur();

		this.updateFormState();
	};

	/**
	 * Get the expanded state of the widget
	 *
	 * @return {boolean} expanded Widget is expanded
	 */
	mw.flow.ui.NewTopicWidget.prototype.isExpanded = function () {
		return this.expanded;
	};

	/**
	 * Toggle the expanded state of the widget
	 *
	 * @param {boolean} expanded Widget is expanded
	 */
	mw.flow.ui.NewTopicWidget.prototype.toggleExpanded = function ( expanded ) {
		this.expanded = expanded !== undefined ? expanded : !this.expanded;

		this.$element.toggleClass( 'flow-ui-newTopicWidget-expanded', this.expanded );
		this.editor.toggle( this.expanded );
		this.anonWarning.toggle( this.expanded );
		this.canNotEdit.toggle( this.expanded );
		// Hide errors
		this.error.toggle( false );

		if ( !this.expanded ) {
			// Reset the title
			this.title.setValue( '' );
		}
	};
}() );
