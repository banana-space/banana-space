( function () {
	/**
	 * Flow reply widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {string} topicId The id of the topic this reply belongs to
	 * @param {string} replyTo The id this reply is a child of
	 * @param {Object} [config] Configuration object
	 * @cfg {boolean} [expandable=true] Initialize the widget with a trigger input. Otherwise,
	 *   the widget will be initialized with the editor already open.
	 * @cfg {Object} [editor] Config options to pass to mw.flow.ui.EditorWidget
	 */
	mw.flow.ui.ReplyWidget = function mwFlowUiReplyWidget( topicId, replyTo, config ) {
		config = config || {};

		this.replyTo = replyTo;
		this.topicId = topicId;
		this.expandable = config.expandable === undefined ? true : config.expandable;
		this.expanded = !this.expandable;
		this.placeholder = config.placeholder;
		this.editorOptions = config.editor;

		this.isProbablyEditable = mw.config.get( 'wgIsProbablyEditable' );

		// Parent constructor
		mw.flow.ui.ReplyWidget.super.call( this, config );

		this.api = new mw.flow.dm.APIHandler();

		this.anonWarning = new mw.flow.ui.AnonWarningWidget( {
			isProbablyEditable: this.isProbablyEditable
		} );
		this.anonWarning.toggle( !this.expandable );

		this.canNotEdit = new mw.flow.ui.CanNotEditWidget( this.api, {
			userGroups: mw.config.get( 'wgUserGroups' ),
			restrictionEdit: mw.config.get( 'wgRestrictionEdit' ),
			isProbablyEditable: this.isProbablyEditable
		} );
		this.canNotEdit.toggle( !this.expandable );

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-replyWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		this.captcha = new mw.flow.dm.Captcha();
		this.captchaWidget = new mw.flow.ui.CaptchaWidget( this.captcha );

		this.$messages = $( '<div>' ).addClass( 'flow-ui-editorContainerWidget-messages' );
		this.$editorContainer = $( '<div>' ).addClass( 'flow-ui-replyWidget-editor-container' );

		this.$element
			.addClass( 'flow-ui-replyWidget' )
			.append(
				this.$messages.append(
					this.anonWarning.$element,
					this.canNotEdit.$element,
					this.error.$element,
					this.captchaWidget.$element
				),
				this.$editorContainer
			);

		if ( this.expandable ) {
			this.triggerInput = new OO.ui.TextInputWidget( {
				classes: [ 'flow-ui-replyWidget-trigger-input' ],
				placeholder: config.placeholder
			} );
			this.triggerInput.$element.on( 'focusin', this.onTriggerFocusIn.bind( this ) );
			this.triggerInput.$input.attr( 'aria-label', config.placeholder );
			this.$element.append( this.triggerInput.$element );
		} else {
			// Only initialize the editor if we are not in 'expandable' mode
			// Otherwise, the editor is lazy-loaded
			this.initializeEditor();
			this.editor.toggle( true );
		}
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.ReplyWidget, OO.ui.Widget );

	/* Events */

	/**
	 * Save the content of the reply
	 *
	 * @event saveContent
	 * @param {string} workflow The workflow this reply was saved under
	 * @param {string} content The content of the reply
	 * @param {string} contentFormat The format of the content of this reply
	 */

	/* Methods */

	/**
	 * Respond to trigger input focusin
	 */
	mw.flow.ui.ReplyWidget.prototype.onTriggerFocusIn = function () {
		this.activateEditor();
	};

	/**
	 * Repond to editor content change
	 */
	mw.flow.ui.ReplyWidget.prototype.onEditorChange = function () {
		this.editor.editorControlsWidget.toggleSaveable( !this.editor.isEmpty() );
	};

	/**
	 * Respond to editor cancel
	 */
	mw.flow.ui.ReplyWidget.prototype.onEditorCancel = function () {
		if ( this.expandable ) {
			this.error.toggle( false );
			this.editor.toggle( false );
			this.anonWarning.toggle( false );
			this.canNotEdit.toggle( false );
			this.triggerInput.toggle( true );
			this.expanded = false;
		} else {
			this.toggle( false );
		}
	};

	/**
	 * Respond to editor save
	 *
	 * @param {string} content Content
	 * @param {string} format Format
	 */
	mw.flow.ui.ReplyWidget.prototype.onEditorSaveContent = function ( content, format ) {
		var widget = this,
			captchaResponse;

		captchaResponse = this.captchaWidget.getResponse();

		this.error.setLabel( '' );
		this.error.toggle( false );
		this.editor.pushPending();
		this.api.saveReply( this.topicId, this.replyTo, content, format, captchaResponse )
			.then( function ( workflow ) {
				widget.captchaWidget.toggle( false );

				if ( widget.expandable ) {
					widget.triggerInput.toggle( true );
					widget.editor.toggle( false );
					widget.anonWarning.toggle( false );
					widget.canNotEdit.toggle( false );
					widget.expanded = false;
				}

				// Make sure the widget is no longer pending when we emit the event,
				// otherwise destroying it breaks (T166634)
				widget.editor.popPending();
				widget.emit( 'saveContent', workflow, content, format );
			}, function ( errorCode, errorObj ) {
				widget.captcha.update( errorCode, errorObj );
				if ( !widget.captcha.isRequired() ) {
					widget.error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
					widget.error.toggle( true );
				}
				widget.editor.popPending();
			} );
	};

	/**
	 * Initialize the editor
	 */
	mw.flow.ui.ReplyWidget.prototype.initializeEditor = function () {
		if ( !this.editor ) {
			this.editor = new mw.flow.ui.EditorWidget( $.extend( {
				placeholder: this.placeholder,
				saveMsgKey: mw.user.isAnon() ? 'flow-reply-link-anonymously' : 'flow-reply-link',
				classes: [ 'flow-ui-replyWidget-editor' ],
				saveable: this.isProbablyEditable,
				id: 'reply/' + this.replyTo
			}, this.editorOptions ) );

			this.onEditorChange();

			this.$editorContainer.append( this.editor.$element );

			// Events
			this.editor.connect( this, {
				change: 'onEditorChange',
				saveContent: 'onEditorSaveContent',
				cancel: 'onEditorCancel'
			} );
		}
	};

	/**
	 * Check if the widget is expandable
	 *
	 * @return {boolean}
	 */
	mw.flow.ui.ReplyWidget.prototype.isExpandable = function () {
		return this.expandable;
	};

	/**
	 * Check if the widget is expanded
	 *
	 * @return {boolean}
	 */
	mw.flow.ui.ReplyWidget.prototype.isExpanded = function () {
		return this.expanded;
	};

	/**
	 * Force activation of the editor
	 */
	mw.flow.ui.ReplyWidget.prototype.activateEditor = function () {
		if ( this.triggerInput ) {
			this.triggerInput.setValue( '' );
			this.triggerInput.toggle( false );
		}
		this.toggle( true );
		this.anonWarning.toggle( true );
		this.canNotEdit.toggle( true );
		this.initializeEditor();
		this.editor.toggle( true );
		this.editor.activate();
		this.expanded = true;
	};

	/**
	 * Focus the reply widget on the editor
	 */
	mw.flow.ui.ReplyWidget.prototype.focus = function () {
		if ( this.isExpanded() ) {
			this.editor.focus();
		} else {
			// Trigger the focusin event
			this.activateEditor();
		}
	};

	/**
	 * Destroy the widget
	 *
	 * @return {jQuery.Promise} Promise which resolves when the widget is destroyed
	 */
	mw.flow.ui.ReplyWidget.prototype.destroy = function () {
		return this.editor.destroy();
	};

}() );
