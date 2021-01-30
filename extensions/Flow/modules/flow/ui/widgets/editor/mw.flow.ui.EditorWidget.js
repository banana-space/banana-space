/* global ve */
( function () {
	/**
	 * Flow editor widget
	 *
	 * @class
	 * @extends OO.ui.Widget
	 *
	 * @constructor
	 * @param {Object} [config] Configuration options
	 * @cfg {string} [placeholder] Placeholder text to use for the editor when empty
	 * @cfg {string} [termsMsgKey='flow-terms-of-use-edit'] i18n message key for the footer message
	 * @cfg {string} [saveMsgKey='flow-newtopic-save'] i18n message key for the save button
	 * @cfg {string} [cancelMsgKey='flow-cancel'] i18n message key for the cancel button
	 * @cfg {boolean} [autoFocus=true] Automatically focus after switching editors
	 * @cfg {boolean} [cancelOnEscape=true] Emit 'cancel' when Esc is pressed
	 * @cfg {boolean} [confirmCancel=true] Pop up a confirmation dialog if the user attempts
	 *  to cancel when there are changes in the editor.
	 * @cfg {boolean} [confirmLeave=true] Pop up a confirmation dialog if the user attempts
	 *  to navigate away when there are changes in the editor.
	 * @cfg {Function} [leaveCallback] Function to call when the user attempts to navigate away.
	 *  If this function returns false, a confirmation dialog will be popped up.
	 * @cfg {boolean} [saveable=true] Initial state of whether editor is saveable
	 */
	mw.flow.ui.EditorWidget = function mwFlowUiEditorWidget( config ) {
		var widget = this;

		config = config || {};

		// Parent constructor
		mw.flow.ui.EditorWidget.super.call( this, config );

		// Mixin constructors
		OO.ui.mixin.PendingElement.call( this, config );

		this.useVE = this.constructor.static.isVisualEditorSupported();

		this.placeholder = config.placeholder || '';
		this.confirmCancel = !!config.confirmCancel || config.cancelOnEscape === undefined;
		this.confirmLeave = !!config.confirmLeave || config.confirmLeave === undefined;
		this.leaveCallback = config.leaveCallback;
		this.id = config.id;

		this.loadPromise = null;

		this.error = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-editorWidget-error flow-errors errorbox' ]
		} );
		this.error.toggle( false );

		this.editorControlsWidget = new mw.flow.ui.EditorControlsWidget( {
			termsMsgKey: config.termsMsgKey || 'flow-terms-of-use-edit',
			saveMsgKey: config.saveMsgKey || 'flow-newtopic-save',
			cancelMsgKey: config.cancelMsgKey || 'flow-cancel',
			saveable: this.saveable
		} );

		this.wikitextHelpLabel = new OO.ui.LabelWidget( {
			classes: [ 'flow-ui-editorWidget-wikitextHelpLabel' ],
			label: $( '<span>' ).append(
				mw.message( 'flow-wikitext-editor-help-and-preview' ).params( [
					// Link to help page
					$( '<span>' )
						.html( mw.message( 'flow-wikitext-editor-help-uses-wikitext' ).parse() )
						.find( 'a' )
						.attr( 'target', '_blank' )
						.end(),
					// Preview link
					$( '<a>' )
						.attr( 'href', '#' )
						.addClass( 'flow-ui-editorWidget-label-preview' )
						.text( mw.message( 'flow-wikitext-editor-help-preview-the-result' ).text() )
				] ).parse() )
				.find( '.flow-ui-editorWidget-label-preview' )
				.on( 'click', this.onPreviewLinkClick.bind( this ) )
				.end()
		} );
		this.wikitextHelpLabel.toggle( false );

		this.$editorWrapper = $( '<div>' )
			.addClass( 'flow-ui-editorWidget-editor' )
			.append( this.wikitextHelpLabel.$element );
		this.setPendingElement( this.$editorWrapper );
		if ( !this.useVE ) {
			this.input = new OO.ui.MultilineTextInputWidget( {
				autosize: true,
				maxRows: 999,
				placeholder: this.placeholder,
				// The following classes can be used here:
				// * mw-editfont-default
				// * mw-editfont-monospace
				// * mw-editfont-sans-serif
				// * mw-editfont-serif
				classes: [ 'flow-ui-editorWidget-input', 'mw-editfont-' + mw.user.options.get( 'editfont' ) ]
			} );
			this.input.toggle( false );
			this.input.connect( this, {
				change: [ 'emit', 'change' ],
				enter: 'onTargetSubmit'
			} );
			this.$editorWrapper.append( this.input.$element );
			// VE focus listeners are bound in #onTargetSurfaceReady
			this.$element
				.on( 'focusin', this.onEditorFocusIn.bind( this ) )
				.on( 'focusout', this.onEditorFocusOut.bind( this ) );
		}

		this.toggleAutoFocus( config.autoFocus === undefined ? true : !!config.autoFocus );
		this.toggleSaveable( config.saveable !== undefined ? config.saveable : true );

		// Events
		this.editorControlsWidget.connect( this, {
			cancel: 'onEditorControlsWidgetCancel',
			save: 'onEditorControlsWidgetSave'
		} );

		if ( config.cancelOnEscape || config.cancelOnEscape === undefined ) {
			this.$element.on( 'keydown', function ( e ) {
				if ( e.which === OO.ui.Keys.ESCAPE ) {
					widget.onEditorControlsWidgetCancel();
					e.preventDefault();
					e.stopPropagation();
				}
			} );
		}

		this.$element
			.append(
				this.$editorWrapper,
				this.error.$element,
				this.editorControlsWidget.$element
			)
			.addClass( 'flow-ui-editorWidget' );
	};

	/* Events */

	/**
	 * @event saveContent
	 * @param {string} content Content to save
	 * @param {string} format Format of content ('html' or 'wikitext')
	 */

	/**
	 * @event cancel
	 * The user clicked the cancel button.
	 */

	/**
	 * @event change
	 * The contents of the editor changed.
	 */

	/* Initialization */

	OO.inheritClass( mw.flow.ui.EditorWidget, OO.ui.Widget );
	OO.mixinClass( mw.flow.ui.EditorWidget, OO.ui.mixin.PendingElement );

	/* Static methods */

	mw.flow.ui.EditorWidget.static.isVisualEditorSupported = function () {
		/* global VisualEditorSupportCheck:false */
		return !!(
			!OO.ui.isMobile() &&
			mw.loader.getState( 'ext.visualEditor.core' ) &&
			mw.user.options.get( 'flow-visualeditor' ) &&
			window.VisualEditorSupportCheck && VisualEditorSupportCheck()
		);
	};

	/**
	 * Preload the VisualEditor modules so that loading the editor later will be faster.
	 *
	 * @return {jQuery.Promise} Promise that resolves when the VisualEditor modules have been loaded
	 */
	mw.flow.ui.EditorWidget.static.preload = function () {
		var conf, modules;
		if ( !this.preloadPromise ) {
			if ( this.isVisualEditorSupported() ) {
				conf = mw.config.get( 'wgVisualEditorConfig' );
				modules = [ 'ext.flow.visualEditor' ].concat(
					conf.pluginModules.filter( mw.loader.getState )
				);
				this.preloadPromise =
					mw.loader.using( conf.preloadModules )
						// If these fail, we still want to continue loading, so convert failure to success
						.catch( function () {
							return $.Deferred().resolve();
						} )
						.then( function () {
							return mw.loader.using( modules );
						} );
			} else {
				this.preloadPromise = $.Deferred().resolve().promise();
			}
		}
		return this.preloadPromise;
	};

	/**
	 * Load the VisualEditor code and create this.target.
	 *
	 * Calling this method externally can be useful to preload VisualEditor, but is not functionally
	 * necessary. #activate calls this method as well.
	 *
	 * It's safe to call this method multiple times, or to call it when loading is already
	 * complete: the same promise will be returned every time.
	 *
	 * @return {jQuery.Promise} Promise resolved when this.target has been created.
	 */
	mw.flow.ui.EditorWidget.prototype.load = function () {
		var widget = this;
		if ( !this.useVE ) {
			return $.Deferred().resolve().promise();
		}
		if ( !this.loadPromise ) {
			this.loadPromise = this.constructor.static.preload()
				.then( function () {
					widget.target = ve.init.mw.targetFactory.create( 'flow', { id: widget.id } );
					widget.target.connect( widget, {
						surfaceReady: 'onTargetSurfaceReady',
						switchMode: 'onTargetSwitchMode',
						submit: 'onTargetSubmit'
					} );
					widget.$editorWrapper.prepend( widget.target.$element );
				} );
		}
		return this.loadPromise;
	};

	/**
	 * Activate the editor.
	 *
	 * @param {Object} [content] Content to preload into the editor
	 * @param {string} content.content Content
	 * @param {string} content.format Format of content ('html' or 'wikitext')
	 * @return {jQuery.Promise}
	 */
	mw.flow.ui.EditorWidget.prototype.activate = function ( content ) {
		var widget = this;
		if ( !this.useVE ) {
			// FIXME doesn't work with HTML, figure out if that can even ever be passed in
			this.originalContent = content && content.content || '';
			this.input.setValue( this.originalContent );
			this.input.toggle( true );
			this.maybeAutoFocus();
			return $.Deferred().resolve().promise();
		}

		this.pushPending();
		this.error.toggle( false );
		return this.load()
			.then( this.createSurface.bind( this, content ) )
			.then( function () {
				widget.bindBeforeUnloadHandler();
				widget.maybeAutoFocus();
				widget.wikitextHelpLabel.toggle( widget.target.getDefaultMode() === 'source' );
				widget.target.getSurface().getView().getDocument().getDocumentNode().$element.attr( 'aria-label', widget.placeholder );
			}, function ( error ) {
				widget.error.setLabel( $( '<span>' ).text( error || mw.msg( 'flow-error-default' ) ) );
				widget.error.toggle( true );
			} )
			.always( function () {
				widget.popPending();
			} );
	};

	/**
	 * Create a VE surface with the provided content in it.
	 *
	 * @private
	 * @param {Object} content Content to put into the surface
	 * @param {string} content.content Content
	 * @param {string} content.format Format of content ('html' or 'wikitext')
	 * @return {jQuery.Promise} Promise which resolves when the surface is ready
	 */
	mw.flow.ui.EditorWidget.prototype.createSurface = function ( content ) {
		var contentToLoad,
			contentFormat,
			deferred = $.Deferred();

		if ( content ) {
			contentToLoad = content.content;
			contentFormat = content.format;
		} else {
			contentToLoad = '';
			contentFormat = this.getPreferredFormat();
		}
		this.target.setDefaultMode( contentFormat === 'html' ? 'visual' : 'source' );
		this.target.loadContent( contentToLoad );
		this.target.once( 'surfaceReady', function () {
			deferred.resolve();
		} );
		return deferred.promise();
	};

	/**
	 * If autofocus is enabled, focus the editor and move the cursor to the end.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.maybeAutoFocus = function () {
		if ( this.autoFocus ) {
			this.focus();
			this.moveCursorToEnd();
		}
	};

	/**
	 * Toggle whether the editor is automatically focused after activating and switching.
	 *
	 * @param {boolean} [autoFocus] Whether to focus automatically; if unset, flips current value
	 */
	mw.flow.ui.EditorWidget.prototype.toggleAutoFocus = function ( autoFocus ) {
		this.autoFocus = autoFocus === undefined ? !this.autoFocus : !!autoFocus;
	};

	/**
	 * Toggle whether the editor is saveable,
	 *
	 * @param {boolean} [saveable] Whether the editor is saveable
	 */
	mw.flow.ui.EditorWidget.prototype.toggleSaveable = function ( saveable ) {
		this.saveable = saveable === undefined ? !this.saveable : !!saveable;

		// Disabled state depends on saveable state
		this.updateDisabled();
		// Update controls widget
		this.editorControlsWidget.toggleSaveable( this.saveable );
	};

	/**
	 * Check whether the editor is saveable.
	 *
	 * @return {boolean} Whether the user can save their content
	 */
	mw.flow.ui.EditorWidget.prototype.isSaveable = function () {
		return this.saveable;
	};

	/**
	 * Respond to focusin event.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.onEditorFocusIn = function () {
		this.$element.addClass( 'flow-ui-editorWidget-focused' );
	};

	/**
	 * Respond to focusout event.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.onEditorFocusOut = function () {
		this.$element.removeClass( 'flow-ui-editorWidget-focused' );
	};

	mw.flow.ui.EditorWidget.prototype.onPreviewLinkClick = function () {
		this.target.switchMode();
		return false;
	};

	/**
	 * Set up event listeners when a new surface is created. This happens every time we
	 * switch modes.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.onTargetSurfaceReady = function () {
		var surface = this.target.getSurface();

		surface.setPlaceholder( this.placeholder );
		surface.getModel().connect( this, { documentUpdate: 'onSurfaceDocumentUpdate' } );
		surface.getView().connect( this, {
			focus: 'onEditorFocusIn',
			blur: 'onEditorFocusOut'
		} );
	};

	/**
	 * Every time the editor content changes, update the user's mode preference if necessary,
	 * and emit 'change'.
	 *
	 * @private
	 * @fires change
	 */
	mw.flow.ui.EditorWidget.prototype.onSurfaceDocumentUpdate = function () {
		// Update the user's preferred editor
		var currentEditor = this.target.getDefaultMode() === 'source' ? 'wikitext' : 'visualeditor';
		if ( mw.user.options.get( 'flow-editor' ) !== currentEditor ) {
			if ( !mw.user.isAnon() ) {
				new mw.Api().saveOption( 'flow-editor', currentEditor );
			}
			// Ensure we also see that preference in the current page
			mw.user.options.set( 'flow-editor', currentEditor );
		}

		this.emit( 'change' );
	};

	/**
	 * Respond to cancel event. Verify with the user that they want to cancel if
	 * there is changed data in the editor.
	 *
	 * @private
	 * @fires cancel
	 */
	mw.flow.ui.EditorWidget.prototype.onEditorControlsWidgetCancel = function () {
		var widget = this;

		if ( this.confirmCancel && this.hasBeenChanged() ) {
			mw.flow.ui.windowManager.openWindow( 'cancelconfirm' ).closed.then( function ( data ) {
				if ( data && data.action === 'discard' ) {
					// Remove content
					widget.clearContent();
					widget.unbindBeforeUnloadHandler();
					widget.emit( 'cancel' );
				}
			} );
		} else {
			this.unbindBeforeUnloadHandler();
			this.emit( 'cancel' );
		}
	};

	/**
	 * Get the content of the editor.
	 *
	 * @return {Object}
	 * @return {string} return.content Content of the editor
	 * @return {string} return.format 'html' or 'wikitext'
	 */
	mw.flow.ui.EditorWidget.prototype.getContent = function () {
		var dom, content, format;

		if ( !this.useVE ) {
			return {
				content: this.input.getValue(),
				format: 'wikitext'
			};
		}

		// If we haven't fully loaded yet, just return nothing.
		if ( !this.target || !this.target.getSurface() ) {
			return '';
		}

		dom = this.target.getSurface().getDom();
		if ( typeof dom === 'string' ) {
			content = dom;
			format = 'wikitext';
		} else {
			// Document content will include html, head & body nodes; get only content inside body node
			content = ve.properInnerHtml( dom.body );
			format = 'html';
		}
		return { content: content, format: format };
	};

	/**
	 * Check whether the editor is empty. Also returns true if the editor hasn't been loaded yet.
	 *
	 * @return {boolean} Editor is empty
	 */
	mw.flow.ui.EditorWidget.prototype.isEmpty = function () {
		if ( !this.useVE ) {
			return this.input.getValue().length === 0;
		}

		if ( !this.target || !this.target.getSurface() ) {
			return true;
		}
		return !this.target.getSurface().getModel().getDocument().data.hasContent();
	};

	/**
	 * Check if there are any changes made to the data in the editor.
	 *
	 * @return {boolean} The original content has changed
	 */
	mw.flow.ui.EditorWidget.prototype.hasBeenChanged = function () {
		if ( !this.useVE ) {
			return this.input.getValue() !== this.originalContent;
		}

		return this.target && this.target.getSurface().getModel().hasBeenModified();
	};

	/**
	 * Get the format the user prefers.
	 *
	 * @return {string} 'html' or 'wikitext'
	 */
	mw.flow.ui.EditorWidget.prototype.getPreferredFormat = function () {
		var vePref = mw.user.options.get( 'visualeditor-tabs' );
		// If VE isn't available, we don't have much of a choice
		if ( !this.useVE ) {
			return 'wikitext';
		}
		// If the user has their editor preference set to "always VE" or "always source", respect that
		if ( vePref === 'prefer-wt' ) {
			return 'wikitext';
		}
		if ( vePref === 'prefer-ve' ) {
			return 'html';
		}
		// Otherwise, use the last-used editor
		return mw.user.options.get( 'flow-editor' ) === 'visualeditor' ? 'html' : 'wikitext';
	};

	/**
	 * Make this widget pending while switching editor modes, and refocus the editor when
	 * the switch is complete.
	 *
	 * @private
	 * @param {jQuery.Promise} promise Promise resolved/rejected when switch is completed/aborted
	 * @param {string} newMode 'visual' or 'source'
	 * @fires switch
	 */
	mw.flow.ui.EditorWidget.prototype.onTargetSwitchMode = function ( promise, newMode ) {
		var widget = this;
		this.pushPending();
		this.error.toggle( false );
		promise
			.done( function () {
				widget.maybeAutoFocus();
				widget.wikitextHelpLabel.toggle( newMode === 'source' );
			} )
			.fail( function ( error ) {
				widget.error.setLabel( $( '<span>' ).text( error || mw.msg( 'flow-error-default' ) ) );
				widget.error.toggle( true );
			} )
			.always( function () {
				widget.popPending();
			} );
	};

	/**
	 * Handle submit events from the editor
	 */
	mw.flow.ui.EditorWidget.prototype.onTargetSubmit = function () {
		if ( !this.editorControlsWidget.saveButton.isDisabled() ) {
			this.onEditorControlsWidgetSave();
		}
	};

	/**
	 * Relay the save event, adding the content.
	 *
	 * @private
	 * @fires saveContent
	 */
	mw.flow.ui.EditorWidget.prototype.onEditorControlsWidgetSave = function () {
		var content = this.getContent();
		this.unbindBeforeUnloadHandler();
		this.emit(
			'saveContent',
			content.content,
			content.format
		);
	};

	/**
	 * Bind the beforeunload handler, if needed and if not already bound.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.bindBeforeUnloadHandler = function () {
		if ( !this.beforeUnloadHandler && ( this.confirmLeave || this.leaveCallback ) ) {
			this.beforeUnloadHandler = this.onBeforeUnload.bind( this );
			$( window ).on( 'beforeunload', this.beforeUnloadHandler );
		}
	};

	/**
	 * Unbind the beforeunload handler if it is bound.
	 *
	 * @private
	 */
	mw.flow.ui.EditorWidget.prototype.unbindBeforeUnloadHandler = function () {
		if ( this.beforeUnloadHandler ) {
			$( window ).off( 'beforeunload', this.beforeUnloadHandler );
			this.beforeUnloadHandler = null;
		}
	};

	/**
	 * Respond to beforeunload event.
	 *
	 * @private
	 * @return {string|undefined}
	 */
	mw.flow.ui.EditorWidget.prototype.onBeforeUnload = function () {
		if ( this.leaveCallback && this.leaveCallback() === false ) {
			return mw.msg( 'flow-cancel-warning' );
		}
		if ( this.confirmLeave && !this.isEmpty() ) {
			return mw.msg( 'flow-cancel-warning' );
		}
	};

	mw.flow.ui.EditorWidget.prototype.isDisabled = function () {
		// Auto-disable when pending or not saveable
		return this.isPending() ||
			!this.isSaveable() ||
			// Parent method
			mw.flow.ui.EditorWidget.super.prototype.isDisabled.apply( this, arguments );
	};

	mw.flow.ui.EditorWidget.prototype.setDisabled = function () {
		// Parent method
		mw.flow.ui.EditorWidget.super.prototype.setDisabled.apply( this, arguments );

		if ( this.editorControlsWidget ) {
			this.editorControlsWidget.setDisabled( this.isDisabled() );
		}

		if ( this.target ) {
			this.target.setDisabled( this.isDisabled() );
		}
	};

	mw.flow.ui.EditorWidget.prototype.pushPending = function () {
		// Parent method
		OO.ui.mixin.PendingElement.prototype.pushPending.apply( this, arguments );

		// Disabled state depends on pending state
		this.updateDisabled();
	};

	mw.flow.ui.EditorWidget.prototype.popPending = function () {
		// Parent method
		OO.ui.mixin.PendingElement.prototype.popPending.apply( this, arguments );

		// Disabled state depends on pending state
		this.updateDisabled();
	};

	/**
	 * Focus the editor
	 */
	mw.flow.ui.EditorWidget.prototype.focus = function () {
		if ( !this.useVE ) {
			this.input.focus();
			return;
		}

		if ( this.target && this.target.getSurface() ) {
			this.target.getSurface().getView().focus();
		}
	};

	/**
	 * Move the cursor to the end of the editor.
	 */
	mw.flow.ui.EditorWidget.prototype.moveCursorToEnd = function () {
		if ( !this.useVE ) {
			this.input.moveCursorToEnd();
			return;
		}

		if ( this.target && this.target.getSurface() ) {
			this.target.getSurface().getModel().selectLastContentOffset();
		}
	};

	/**
	 * Remove all content from the editor.
	 *
	 */
	mw.flow.ui.EditorWidget.prototype.clearContent = function () {
		if ( !this.useVE ) {
			this.input.setValue( '' );
			return;
		}

		if ( this.target ) {
			this.target.clearDocState();
			this.target.clearSurfaces();
		}
	};

	/**
	 * Destroy the widget.
	 *
	 * @return {jQuery.Promise} Promise which resolves when the widget is destroyed
	 */
	mw.flow.ui.EditorWidget.prototype.destroy = function () {
		if ( this.target ) {
			// clearDocState is called by #destroy
			this.target.destroy();
			// TODO: We should be able to just return target.destroy()
			return this.target.teardownPromise;
		}
		return $.Deferred().resolve().promise();
	};
}() );
