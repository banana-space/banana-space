/*!
 * VisualEditor DataModel Surface class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel surface.
 *
 * @class
 * @extends ve.ce.Surface
 *
 * @constructor
 * @param {ve.dm.Surface} model
 * @param {ve.ui.Surface} ui
 * @param {Object} [config]
 */
ve.ce.MWWikitextSurface = function VeCeMwWikitextSurface() {
	// Parent constructors
	ve.ce.MWWikitextSurface.super.apply( this, arguments );

	this.pasteTargetInput = new OO.ui.MultilineTextInputWidget();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWWikitextSurface, ve.ce.Surface );

/**
 * @inheritdoc
 */
ve.ce.MWWikitextSurface.prototype.onCopy = function ( e ) {
	var originalSelection, scrollTop, slice, clipboardKey,
		view = this,
		clipboardData = e.originalEvent.clipboardData,
		text = this.getModel().getFragment().getText( true ).replace( /\n\n/g, '\n' );

	if ( !text ) {
		return;
	}

	if ( clipboardData ) {
		// Disable the default event so we can override the data
		e.preventDefault();
		clipboardData.setData( 'text/plain', text );
		// We're not going to set HTML, but for browsers that support custom data, set a clipboard key
		if ( ve.isClipboardDataFormatsSupported( e, true ) ) {
			slice = this.model.documentModel.shallowCloneFromSelection( this.getModel().getSelection() );
			this.clipboardIndex++;
			clipboardKey = this.clipboardId + '-' + this.clipboardIndex;
			this.clipboard = { slice: slice, hash: null };
			// Clone the elements in the slice
			slice.data.cloneElements( true );
			clipboardData.setData( 'text/xcustom', clipboardKey );

			// Explicitly store wikitext as text/x-wiki, so that wikitext-aware paste
			// contexts can accept it without having to do any content-
			// sniffing.
			clipboardData.setData( 'text/x-wiki', text );
		}
	} else {
		originalSelection = new ve.SelectionState( this.nativeSelection );

		// Save scroll position before changing focus to "offscreen" paste target
		scrollTop = this.$window.scrollTop();

		// Prevent surface observation due to native range changing
		this.surfaceObserver.disable();
		this.$pasteTarget.empty().append( this.pasteTargetInput.$element );
		this.pasteTargetInput.setValue( text ).select();

		// Restore scroll position after changing focus
		this.$window.scrollTop( scrollTop );

		// setTimeout: postpone until after the default copy action
		setTimeout( function () {
			// Change focus back
			view.$attachedRootNode[ 0 ].focus();
			view.showSelectionState( originalSelection );
			// Restore scroll position
			view.$window.scrollTop( scrollTop );
			view.surfaceObserver.clear();
			view.surfaceObserver.enable();
			// Detach input
			view.pasteTargetInput.$element.detach();
		} );
	}
};

/**
 * @inheritdoc
 */
ve.ce.MWWikitextSurface.prototype.afterPasteInsertExternalData = function ( targetFragment, pastedDocumentModel, contextRange ) {
	var windowAction, deferred,
		view = this;

	function makePlain() {
		pastedDocumentModel = pastedDocumentModel.shallowCloneFromRange( contextRange );
		pastedDocumentModel.data.sanitize( { plainText: true, keepEmptyContentBranches: true } );
		// We just turned this into plaintext, which probably
		// affected the content-length. Luckily, because of
		// the earlier clone, we know we just want the whole
		// document, and because of the major change to
		// plaintext, the difference between originalRange and
		// balancedRange don't really apply. As such, clear
		// out newDocRange. (Can't just make it undefined;
		// need to exclude the internal list, and since we're
		// from a paste we also have to exclude the
		// opening/closing paragraph.)
		contextRange = new ve.Range( pastedDocumentModel.getDocumentRange().from + 1, pastedDocumentModel.getDocumentRange().to - 1 );
		view.pasteSpecial = true;
	}

	if ( !pastedDocumentModel.data.isPlainText( contextRange, true, undefined, true ) ) {
		// Not plaintext. We need to ask whether we should convert it to
		// wikitext, or just strip the formatting out.
		deferred = ve.createDeferred();
		windowAction = ve.ui.actionFactory.create( 'window', this.getSurface() );
		windowAction.open( 'wikitextconvertconfirm', { deferred: deferred } );
		return deferred.promise().then( function ( usePlain ) {
			var insertPromise;
			if ( usePlain ) {
				makePlain();
			}
			insertPromise = ve.ce.MWWikitextSurface.super.prototype.afterPasteInsertExternalData.call( view, targetFragment, pastedDocumentModel, contextRange );
			if ( !usePlain ) {
				insertPromise = insertPromise.then( null, function () {
					// Rich text conversion failed, insert plain text
					makePlain();
					return ve.ce.MWWikitextSurface.super.prototype.afterPasteInsertExternalData.call( view, targetFragment, pastedDocumentModel, contextRange );
				} );
			}
			return insertPromise;
		} );
	}
	// isPlainText is true but we still need sanitize (e.g. remove lists)
	makePlain();
	return ve.ce.MWWikitextSurface.super.prototype.afterPasteInsertExternalData.call( this, targetFragment, pastedDocumentModel, contextRange );
};
