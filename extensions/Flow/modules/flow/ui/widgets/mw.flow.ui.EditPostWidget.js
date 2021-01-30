( function () {
	/**
	 * Flow edit post widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {string} topicId The id of the topic
	 * @param {string} postId The id of the post to edit
	 * @param {Object} [config] Configuration object
	 * @cfg {Object} [editor] Config options to pass to mw.flow.ui.EditorWidget
	 */
	mw.flow.ui.EditPostWidget = function mwFlowUiEditPostWidget( topicId, postId, config ) {
		var msgKey;

		config = config || {};

		this.topicId = topicId;
		this.postId = postId;

		// Parent constructor
		mw.flow.ui.EditPostWidget.super.call( this, config );

		if ( mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ) {
			msgKey = mw.user.isAnon() ? 'flow-post-action-edit-post-submit-anonymously-publish' : 'flow-post-action-edit-post-submit-publish';
		} else {
			msgKey = mw.user.isAnon() ?
				'flow-post-action-edit-post-submit-anonymously' :
				'flow-post-action-edit-post-submit';
		}

		this.editor = new mw.flow.ui.EditorWidget( $.extend( {
			saveMsgKey: msgKey,
			classes: [ 'flow-ui-editPostWidget-editor' ],
			id: 'edit/' + postId
		}, config.editor ) );
		this.editor.toggle( true );

		this.anonWarning = new mw.flow.ui.AnonWarningWidget();
		this.anonWarning.toggle( true );

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-editPostWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		this.captcha = new mw.flow.dm.Captcha();
		this.captchaWidget = new mw.flow.ui.CaptchaWidget( this.captcha );

		this.api = new mw.flow.dm.APIHandler(
			'Topic:' + topicId
		);

		// Events
		this.editor.connect( this, {
			saveContent: 'onEditorSaveContent',
			cancel: 'onEditorCancel'
		} );

		this.$messages = $( '<div>' ).addClass( 'flow-ui-editorContainerWidget-messages' );

		this.$element
			.addClass( 'flow-ui-editPostWidget' )
			.append(
				this.$messages.append(
					this.anonWarning.$element,
					this.error.$element,
					this.captchaWidget.$element
				),
				this.editor.$element
			);

	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.EditPostWidget, OO.ui.Widget );

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
	 * Activate the widget.  These needs to be called when it's visible and in the body.
	 */
	mw.flow.ui.EditPostWidget.prototype.activate = function () {
		var widget, contentFormat;

		this.editor.pushPending();
		this.editor.load();

		// Get the post from the API
		widget = this;
		contentFormat = this.editor.getPreferredFormat();

		this.api.getPost( this.topicId, this.postId, contentFormat ).then(
			function ( post ) {
				var content = OO.getProp( post, 'content', 'content' ),
					format = OO.getProp( post, 'content', 'format' );

				if ( content !== undefined && format !== undefined ) {
					// Update revisionId in the API
					widget.api.setCurrentRevision( post.revisionId );

					// Activate the editor
					return widget.editor.activate( { content: content, format: format } );
				}

			},
			// Error fetching description
			function ( error ) {
				// Display error
				widget.error.setLabel( mw.msg( 'flow-error-external', error ) );
				widget.error.toggle( true );
			}
		).always( function () {
			// Unset pending editor
			widget.editor.popPending();
			// Focus again: pending editors are disabled and can't be focused
			widget.editor.focus();
		} );
	};

	/**
	 * Respond to editor cancel
	 */
	mw.flow.ui.EditPostWidget.prototype.onEditorCancel = function () {
		this.emit( 'cancel' );
	};

	/**
	 * Respond to editor save
	 *
	 * @param {string} content Content
	 * @param {string} format Format
	 */
	mw.flow.ui.EditPostWidget.prototype.onEditorSaveContent = function ( content, format ) {
		var widget = this,
			captchaResponse;

		captchaResponse = this.captchaWidget.getResponse();

		this.error.setLabel( '' );
		this.error.toggle( false );

		this.editor.pushPending();
		this.api.savePost( this.topicId, this.postId, content, format, captchaResponse )
			.then( function ( workflow ) {
				widget.captchaWidget.toggle( false );

				widget.emit( 'saveContent', workflow, content, format );
			} )
			.catch( function ( errorCode, errorObj ) {
				widget.captcha.update( errorCode, errorObj );
				if ( !widget.captcha.isRequired() ) {
					widget.error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
					widget.error.toggle( true );
				}
			} )
			.always( function () {
				widget.editor.popPending();
			} );
	};

	/**
	 * Focus the reply widget on the editor
	 */
	mw.flow.ui.EditPostWidget.prototype.focus = function () {
		this.editor.focus();
	};

	/**
	 * Destroy the widget
	 *
	 * @return {jQuery.Promise} Promise which resolves when the widget is destroyed
	 */
	mw.flow.ui.EditPostWidget.prototype.destroy = function () {
		return this.editor.destroy();
	};

}() );
