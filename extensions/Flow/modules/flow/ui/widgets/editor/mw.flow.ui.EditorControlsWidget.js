( function () {
	/**
	 * Flow editor controls widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {string} [termsMsgKey='flow-terms-of-use-edit'] i18n message key for the footer message
	 * @cfg {string} [saveMsgKey='flow-newtopic-save'] i18n message key for the save button
	 * @cfg {string} [cancelMsgKey='flow-cancel'] i18n message key for the cancel button
	 * @cfg {boolean} [saveable=true] Initial state of saveable flag
	 */
	mw.flow.ui.EditorControlsWidget = function mwFlowUiEditorControlsWidget( config ) {
		var $buttons = $( '<div>' ).addClass( 'flow-ui-editorControlsWidget-buttons' ),
			widget = this;

		config = config || {};

		// Parent constructor
		mw.flow.ui.EditorControlsWidget.super.call( this, config );

		this.termsLabel = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-editorControlsWidget-termsLabel' ],
			// eslint-disable-next-line mediawiki/msg-doc
			label: $( $.parseHTML( mw.message( config.termsMsgKey || 'flow-terms-of-use-edit' ).parse() ) )
		} );

		this.saveButton = new OO.ui.ButtonWidget( {
			flags: [ 'primary', 'progressive' ],
			// eslint-disable-next-line mediawiki/msg-doc
			label: mw.msg( config.saveMsgKey || 'flow-newtopic-save' ),
			classes: [ 'flow-ui-editorControlsWidget-saveButton' ]
		} );

		this.cancelButton = new OO.ui.ButtonWidget( {
			flags: 'destructive',
			framed: false,
			// eslint-disable-next-line mediawiki/msg-doc
			label: mw.msg( config.cancelMsgKey || 'flow-cancel' ),
			classes: [ 'flow-ui-editorControlsWidget-cancelButton' ]
		} );

		// Keyboard shortcut messages are provided by VE, so only do this when VE is installed
		if ( mw.loader.getState( 'ext.visualEditor.core' ) ) {
			mw.loader.using( 'ext.visualEditor.core' ).then( function () {
				widget.cancelButton.setTitle(
					widget.cancelButton.getLabel() +
					// eslint-disable-next-line no-undef
					' [' + new ve.ui.Trigger( 'escape' ).getMessage() + ']'
				);
				widget.saveButton.setTitle(
					widget.saveButton.getLabel() +
					// eslint-disable-next-line no-undef
					' [' + ve.ui.triggerRegistry.lookup( 'submit' )[ 0 ].getMessage() + ']'
				);
			} );
		}

		$buttons.append(
			this.cancelButton.$element,
			this.saveButton.$element
		);

		// Events
		this.saveButton.connect( this, { click: [ 'emit', 'save' ] } );
		this.cancelButton.connect( this, { click: [ 'emit', 'cancel' ] } );

		// Initialize
		this.toggleSaveable( config.saveable !== undefined ? config.saveable : true );
		this.$element
			.append(
				this.termsLabel.$element,
				$buttons,
				$( '<div>' )
					.css( 'clear', 'both' )
			)
			.addClass( 'flow-ui-editorControlsWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.EditorControlsWidget, OO.ui.Widget );

	/**
	 * Set the content of the terms label
	 *
	 * @param {string} msg Terms message
	 */
	mw.flow.ui.EditorControlsWidget.prototype.setTermsLabel = function ( msg ) {
		this.termsLabel.setLabel( msg );
	};

	/**
	 * Toggle whether the save button can be used
	 *
	 * @param {boolean} [saveable=!this.saveable] Whether the save button can be used
	 */
	mw.flow.ui.EditorControlsWidget.prototype.toggleSaveable = function ( saveable ) {
		this.saveable = saveable === undefined ? !this.saveable : !!saveable;
		this.saveButton.setDisabled( this.isDisabled() || !this.saveable );
	};

	mw.flow.ui.EditorControlsWidget.prototype.setDisabled = function () {
		// Parent method
		mw.flow.ui.EditorControlsWidget.super.prototype.setDisabled.apply( this, arguments );

		if ( this.cancelButton && this.saveButton ) {
			this.cancelButton.setDisabled( this.isDisabled() );
			this.saveButton.setDisabled( this.isDisabled() || !this.saveable );
		}
	};

}() );
