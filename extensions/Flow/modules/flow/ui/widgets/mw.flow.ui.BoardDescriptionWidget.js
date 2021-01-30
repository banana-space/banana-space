( function () {
	/**
	 * Flow board description widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {mw.flow.dm.Board} boardModel The board model
	 * @param {Object} [config]
	 * @cfg {jQuery} [$existing] A jQuery object of the existing contents of the board description
	 * @cfg {string} [specialPageCategoryLink] Link to the localized Special:Categories page
	 * @cfg {jQuery} [$categories] A jQuery object of the existing board categories
	 * @cfg {Object} [editor] Config options to pass to mw.flow.ui.EditorWidget
	 */
	mw.flow.ui.BoardDescriptionWidget = function mwFlowUiBoardDescriptionWidget( boardModel, config ) {
		var msgKey,
			$content = $();

		config = config || {};

		// Parent constructor
		mw.flow.ui.BoardDescriptionWidget.super.call( this, config );

		this.board = boardModel;
		this.attachModel( this.board.getDescription() );

		// Since the content is already displayed, we will "steal" the already created
		// node to avoid having to render it twice.
		// Upon creation of this widget, this should be the rendering of the data
		// that exists in the model. Take care, however, that if this widget is
		// used elsewhere, the model and rendering must be synchronized.
		if ( config.$existing ) {
			$content = config.$existing;
		}

		this.$content = $( '<div>' )
			.addClass( 'flow-ui-boardDescriptionWidget-content mw-parser-output' )
			.append( $content );

		this.api = new mw.flow.dm.APIHandler(
			this.board.getPageTitle().getPrefixedDb(),
			{
				currentRevision: this.model.getRevisionId()
			}
		);
		if ( mw.config.get( 'wgEditSubmitButtonLabelPublish' ) ) {
			msgKey = mw.user.isAnon() ?
				'flow-edit-header-submit-anonymously-publish' :
				'flow-edit-header-submit-publish';
		} else {
			msgKey = mw.user.isAnon() ?
				'flow-edit-header-submit-anonymously' :
				'flow-edit-header-submit';
		}

		this.id = 'edit-board-desc/' + mw.flow.system.boardId;
		this.editor = new mw.flow.ui.EditorWidget( $.extend( {
			placeholder: mw.msg( 'flow-edit-header-link' ),
			saveMsgKey: msgKey,
			classes: [ 'flow-ui-boardDescriptionWidget-editor' ],
			id: this.id
		}, config.editor ) );
		this.editor.toggle( false );

		this.anonWarning = new mw.flow.ui.AnonWarningWidget();
		this.anonWarning.toggle( false );

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-boardDescriptionWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		this.captcha = new mw.flow.dm.Captcha();
		this.captchaWidget = new mw.flow.ui.CaptchaWidget( this.captcha );

		this.button = new OO.ui.ButtonWidget( {
			label: mw.msg( 'flow-edit-header-link' ),
			framed: false,
			icon: 'edit',
			flags: 'progressive',
			classes: [ 'flow-ui-boardDescriptionWidget-editButton' ]
		} );

		if ( !this.model.isEditable() ) {
			this.button.toggle( false );
		}

		this.categoriesWidget = new mw.flow.ui.CategoriesWidget( this.board, {
			specialPageCategoryLink: config.specialPageCategoryLink
		} );
		if ( config.$categories ) {
			this.addCategoriesFromDom( config.$categories );
		}

		// Events
		this.button.connect( this, { click: 'onEditButtonClick' } );
		this.editor.connect( this, {
			saveContent: 'onEditorSaveContent',
			cancel: 'onEditorCancel'
		} );

		// NOTE: Unlike other widgets, in the board description widget there is
		// no use listening to change events in the content, because:
		// 1. Any time the model changes, the widget must re-request the content
		// in fixed-html format.
		// 2. Due to the above, we initialize the widget already with the content
		// from the DOM, and assume that all other changes to the content happen
		// from the widget itself, which would run its own api request for the
		// content in the proper format.
		//
		// The events below are specific listeners for specific behaviors identified
		// as necessary.
		this.model.connect( this, { editableChange: 'onModelEditableChange' } );

		this.$messages = $( '<div>' ).addClass( 'flow-ui-editorContainerWidget-messages' );

		// Initialize
		this.$element
			.append(
				this.$messages.append(
					this.error.$element,
					this.captchaWidget.$element,
					this.anonWarning.$element,
					// Ensure inline button is on its own line, and is :first-child, T175683
					$( '<div>' ).append( this.button.$element ),
					this.$content
				),
				this.editor.$element,
				this.categoriesWidget.$element
			)
			.addClass( 'flow-ui-boardDescriptionWidget' );
	};

	/* Initialization */

	OO.inheritClass( mw.flow.ui.BoardDescriptionWidget, OO.ui.Widget );

	/* Events */

	/**
	 * @event saveContent
	 * The content of the description was saved
	 */

	/**
	 * @event cancel
	 * The edit operation on the description was canceled
	 */

	/* Methods */

	/**
	 * Respond to changes in the model's editable status
	 *
	 * @param {boolean} editable Description is editable
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.onModelEditableChange = function ( editable ) {
		this.button.toggle( editable && !this.editor.isVisible() );
	};

	/**
	 * Respond to edit button click. Switch to the editor widget
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.onEditButtonClick = function () {
		var widget = this,
			contentFormat = this.editor.getPreferredFormat();

		// Hide the edit button, any errors, and the content
		this.button.toggle( false );
		this.error.toggle( false );
		this.categoriesWidget.toggle( false );
		this.$content.addClass( 'oo-ui-element-hidden' );

		this.editor.toggle( true );
		this.editor.pushPending();
		this.anonWarning.toggle( true );
		this.editor.load();

		// Get the description from the API
		this.api.getDescription( contentFormat )
			.then(
				function ( desc ) {
					var contentToLoad,
						content = OO.getProp( desc, 'content', 'content' ),
						format = OO.getProp( desc, 'content', 'format' );

					if ( content !== undefined && format !== undefined ) {
						// Update revisionId in the API
						widget.api.setCurrentRevision( widget.model.getRevisionId() );

						contentToLoad = { content: content, format: format };
					}

					// Load the editor
					return widget.editor.activate( contentToLoad );
				},
				// Error fetching description
				function ( error ) {
					// Display error
					widget.error.setLabel( mw.msg( 'flow-error-external', error ) );
					widget.error.toggle( true );

					// Return to read mode
					widget.showContent( false );
				}
			)
			.always( function () {
				// Unset pending editor
				widget.editor.popPending();
				// Focus again: pending editors are disabled and can't be focused
				widget.editor.focus();
			} );

	};

	/**
	 * Respond to an editor cancel event
	 *
	 * @fires cancel
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.onEditorCancel = function () {
		this.showContent( true );
		this.emit( 'cancel' );
	};

	/**
	 * Respond to editor save event. Save the content and display the new description.
	 *
	 * @param {string} content Content to save
	 * @param {string} format Format of content
	 * @fires saveContent
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.onEditorSaveContent = function ( content, format ) {
		var widget = this,
			captchaResponse;

		this.editor.pushPending();

		captchaResponse = this.captchaWidget.getResponse();

		this.error.setLabel( '' );
		this.error.toggle( false );

		this.api.saveDescription( content, format, captchaResponse )
			.then( function ( newRevisionId ) {
				widget.captchaWidget.toggle( false );

				// Update revisionId in the API
				widget.api.setCurrentRevision( newRevisionId );
				// Get the new header to update the dm.BoardDescription
				// The widget should update automatically by its events
				return widget.api.getDescription( 'html' );
			} )
			.then( function ( description ) {
				// Update the model
				widget.model.populate( description );
				return widget.api.getDescription( 'fixed-html' );
			} )
			.then( function ( desc ) {
				// Change the actual content
				widget.$content.empty().append( $.parseHTML( desc.content.content ) );
				widget.emit( 'saveContent' );
			} )
			.catch( function ( errorCode, errorObj ) {
				errorObj = errorObj || {};
				if ( errorCode instanceof Error ) {
					errorObj.exception = errorCode.toString();
				}
				widget.captcha.update( errorCode, errorObj );
				if ( !widget.captcha.isRequired() ) {
					widget.error.setLabel( new OO.ui.HtmlSnippet( errorObj.error && errorObj.error.info || errorObj.exception ) );
					widget.error.toggle( true );
				}
				// Prevent the promise from becoming resolved after this step
				return $.Deferred().reject().promise();
			} )
			// Get the new categories
			.then( this.api.getCategories.bind( this.api ) )
			.then( function ( catObject ) {
				var cat, title,
					categories = {};

				for ( cat in catObject ) {
					title = mw.Title.newFromText( catObject[ cat ].title );
					categories[ title.getMain() ] = { exists: catObject[ cat ].missing === undefined };
				}
				// Update the board data model
				widget.board.clearCategories();
				widget.board.setCategoriesFromObject( categories );
			} )
			// Remove the editor and show content
			.then( function () {
				widget.showContent( true );
			} )
			// Always pop pending for the editor
			.always( function () {
				widget.editor.popPending();
			} );
	};

	/**
	 * Add categories from a jQuery object. This is so that we can feed categories from the
	 * nojs rendering of the page without having the widget to ask the API for the categories
	 * when it just loads.
	 *
	 * @param {jQuery} [$categoriesWrapper] Categories div wrapper
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.addCategoriesFromDom = function ( $categoriesWrapper ) {
		var categories = {};

		$categoriesWrapper.find( '.flow-board-header-category-item a' ).each( function () {
			categories[ $( this ).text() ] = {
				// eslint-disable-next-line no-jquery/no-class-state
				exists: !$( this ).hasClass( 'new' )
			};
		} );

		this.board.setCategoriesFromObject( categories );
		this.categoriesWidget.toggle( this.board.hasCategories() );
	};

	/**
	 * Show the content instead of the editor
	 *
	 * @param {boolean} [hideErrors] Hide error bar
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.showContent = function ( hideErrors ) {
		// Hide the editor
		this.editor.toggle( false );
		this.anonWarning.toggle( false );

		if ( !hideErrors ) {
			// Hide errors
			this.error.toggle( false );
		}

		// Display the edit button and the content
		this.button.toggle( true );
		this.$content.removeClass( 'oo-ui-element-hidden' );
		this.categoriesWidget.toggle( this.board.hasCategories() );
	};

	/**
	 * Attach a model to the widget
	 *
	 * @param {mw.flow.dm.BoardDescription} model Board model
	 */
	mw.flow.ui.BoardDescriptionWidget.prototype.attachModel = function ( model ) {
		if ( this.model ) {
			this.model.disconnect( this );
		}

		this.model = model;
	};

}() );
