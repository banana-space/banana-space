( function () {
	/**
	 * Topic title widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {string} topicId
	 * @param {Object} [config]
	 */
	mw.flow.ui.TopicTitleWidget = function mwFlowUiTopicTitleWidget( topicId, config ) {
		var widget = this;

		// Parent constructor
		mw.flow.ui.TopicTitleWidget.super.call( this, config );

		this.topicId = topicId;
		this.api = new mw.flow.dm.APIHandler(
			'Topic:' + topicId
		);

		this.id = 'edit-topic/' + this.topicId;

		this.anonWarning = new mw.flow.ui.AnonWarningWidget();
		this.anonWarning.toggle( true );

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-topicTitleWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		this.captcha = new mw.flow.dm.Captcha();
		this.captchaWidget = new mw.flow.ui.CaptchaWidget( this.captcha );

		this.input = new OO.ui.TextInputWidget( {
			classes: [ 'flow-ui-topicTitleWidget-titleInput' ],
			autofocus: true
		} );

		this.termsLabel = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-topicTitleWidget-termsLabel' ],
			label: $( $.parseHTML( mw.message( 'flow-terms-of-use-edit' ).parse() ) )
		} );

		this.$controls = $( '<div>' ).addClass( 'flow-ui-topicTitleWidget-controls' );
		this.$buttons = $( '<div>' ).addClass( 'flow-ui-topicTitleWidget-buttons' );
		this.saveButton = new OO.ui.ButtonWidget( {
			flags: [ 'primary', 'progressive' ],
			label: mw.msg( 'flow-edit-title-submit' ),
			classes: [ 'flow-ui-topicTitleWidget-saveButton' ]
		} );

		this.cancelButton = new OO.ui.ButtonWidget( {
			flags: 'destructive',
			framed: false,
			label: mw.msg( 'flow-cancel' ),
			classes: [ 'flow-ui-topicTitleWidget-cancelButton' ]
		} );
		this.$buttons.append(
			this.cancelButton.$element,
			this.saveButton.$element
		);
		this.$controls.append(
			this.termsLabel.$element,
			this.$buttons,
			$( '<div>' ).css( 'clear', 'both' )
		);

		// Events
		this.saveButton.connect( this, { click: 'onSaveButtonClick' } );
		this.cancelButton.connect( this, { click: 'onCancelButtonClick' } );
		this.input.connect( this, { enter: 'onSaveButtonClick' } );
		this.input.$input.on( 'keydown', this.onInputKeyDown.bind( this ) );

		this.$element
			.addClass( 'flow-ui-topicTitleWidget' )
			.append(
				this.anonWarning.$element,
				this.error.$element,
				this.captchaWidget.$element,
				this.input.$element,
				this.$controls
			);

		this.pushPending();
		this.api.getPost( topicId, topicId, 'wikitext' ).then(
			function ( topic ) {
				var content = OO.getProp( topic, 'content', 'content' ),
					currentRevisionId = topic.revisionId;

				widget.api.setCurrentRevision( currentRevisionId );
				widget.input.setValue( mw.storage.session.get( widget.id + '/title' ) || content );
			},
			function ( error ) {
				widget.error.setLabel( mw.msg( 'flow-error-external', error ) );
				widget.error.toggle( true );
			}
		).always(
			function () {
				widget.popPending();
				widget.input.moveCursorToEnd().focus();
				// Connect change listener after widget has been populated
				widget.input.connect( widget, { change: 'onInputChange' } );
			}
		);

	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.TopicTitleWidget, OO.ui.Widget );

	/* Methods */

	mw.flow.ui.TopicTitleWidget.prototype.onSaveButtonClick = function () {
		var content = this.input.getValue(),
			captcha = this.captchaWidget.getResponse(),
			widget = this;

		widget.pushPending();
		this.api.saveTopicTitle( this.topicId, content, captcha ).then(
			function ( workflowId ) {
				widget.emit( 'saveContent', workflowId );
			},
			function ( errorCode, errorObj ) {
				widget.captcha.update( errorCode, errorObj );
				if ( !widget.captcha.isRequired() ) {
					widget.error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
					widget.error.toggle( true );
				}
			}
		).always(
			function () {
				widget.popPending();
				mw.storage.session.remove( widget.id + '/title' );
			}
		);
	};

	mw.flow.ui.TopicTitleWidget.prototype.onCancelButtonClick = function () {
		mw.storage.session.remove( this.id + '/title' );
		this.emit( 'cancel' );
	};

	mw.flow.ui.TopicTitleWidget.prototype.onInputChange = function () {
		mw.storage.session.set( this.id + '/title', this.input.getValue() );
	};

	mw.flow.ui.TopicTitleWidget.prototype.onInputKeyDown = function ( e ) {
		if ( e.which === OO.ui.Keys.ESCAPE ) {
			this.onCancelButtonClick();
			return false;
		}
	};

	mw.flow.ui.TopicTitleWidget.prototype.isDisabled = function () {
		// Auto-disable when pending
		return ( this.input && this.input.isPending() ) ||
			// Parent method
			mw.flow.ui.TopicTitleWidget.super.prototype.isDisabled.apply( this, arguments );
	};

	mw.flow.ui.TopicTitleWidget.prototype.setDisabled = function () {
		// Parent method
		mw.flow.ui.TopicTitleWidget.super.prototype.setDisabled.apply( this, arguments );

		if ( this.input && this.saveButton && this.cancelButton ) {
			this.input.setDisabled( this.isDisabled() );
			this.saveButton.setDisabled( this.isDisabled() );
			this.cancelButton.setDisabled( this.isDisabled() );
		}
	};

	mw.flow.ui.TopicTitleWidget.prototype.pushPending = function () {
		this.input.pushPending();

		// Disabled state depends on pending state
		this.updateDisabled();
	};

	mw.flow.ui.TopicTitleWidget.prototype.popPending = function () {
		this.input.popPending();

		// Disabled state depends on pending state
		this.updateDisabled();
	};

}() );
