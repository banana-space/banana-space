/*!
 * VisualEditor ContentEditable linear enter key down handler
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* istanbul ignore next */
/**
 * Enter key down handler for linear selections.
 *
 * @class
 * @extends ve.ce.KeyDownHandler
 *
 * @constructor
 */
ve.ce.LinearEnterKeyDownHandler = function VeCeLinearEnterKeyDownHandler() {
	// Parent constructor - never called because class is fully static
	// ve.ui.LinearEnterKeyDownHandler.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.LinearEnterKeyDownHandler, ve.ce.KeyDownHandler );

/* Static properties */

ve.ce.LinearEnterKeyDownHandler.static.name = 'linearEnter';

ve.ce.LinearEnterKeyDownHandler.static.keys = [ OO.ui.Keys.ENTER ];

ve.ce.LinearEnterKeyDownHandler.static.supportedSelections = [ 'linear' ];

/* Static methods */

/**
 * @inheritdoc
 */
ve.ce.LinearEnterKeyDownHandler.static.execute = function ( surface, e ) {
	var txRemove, txInsert, outerParent, outerChildrenCount, container, prevContentOffset,
		insertEmptyParagraph, node, focusedNode, splitData,
		range = surface.model.getSelection().getRange(),
		cursor = range.from,
		documentModel = surface.model.getDocument(),
		emptyParagraph = [ { type: 'paragraph' }, { type: '/paragraph' } ],
		advanceCursor = true,
		outermostNode = null,
		nodeModel = null,
		nodeModelRange = null;

	e.preventDefault();

	if ( e.ctrlKey || e.metaKey ) {
		// CTRL+Enter triggers the submit command
		return false;
	}

	focusedNode = surface.getFocusedNode();
	if ( focusedNode ) {
		if ( focusedNode.getModel().isEditable() ) {
			focusedNode.executeCommand();
		}
		return true;
	}

	if ( surface.isReadOnly() ) {
		return true;
	}

	node = surface.getDocument().getBranchNodeFromOffset( range.from );

	if ( !node.isMultiline() ) {
		return true;
	}

	// Handle removal first
	if ( !range.isCollapsed() ) {
		txRemove = ve.dm.TransactionBuilder.static.newFromRemoval( documentModel, range );
		range = txRemove.translateRange( range );
		// We do want this to propagate to the surface
		surface.model.change( txRemove, new ve.dm.LinearSelection( range ) );
		// Remove may have changed node at range.from
		node = surface.getDocument().getBranchNodeFromOffset( range.from );
	}

	if ( node !== null ) {
		// Assertion: node is certainly a contentBranchNode
		nodeModel = node.getModel();
		nodeModelRange = nodeModel.getRange();
	}

	// Handle insertion
	if ( node === null ) {
		throw new Error( 'node === null' );
	} else if (
		nodeModel.getType() !== 'paragraph' &&
		(
			cursor === nodeModelRange.from ||
			cursor === nodeModelRange.to
		)
	) {
		// If we're at the start/end of something that's not a paragraph, insert a paragraph
		// before/after. Insert after for empty nodes (from === to).
		if ( cursor === nodeModelRange.to ) {
			txInsert = ve.dm.TransactionBuilder.static.newFromInsertion(
				documentModel, nodeModel.getOuterRange().to, emptyParagraph
			);
		} else if ( cursor === nodeModelRange.from ) {
			txInsert = ve.dm.TransactionBuilder.static.newFromInsertion(
				documentModel, nodeModel.getOuterRange().from, emptyParagraph
			);
			advanceCursor = false;
		}
	} else if ( e.shiftKey && nodeModel.hasSignificantWhitespace() ) {
		// Insert newline
		txInsert = ve.dm.TransactionBuilder.static.newFromInsertion( documentModel, range.from, '\n' );
	} else if ( !node.splitOnEnter() ) {
		// Cannot split, so insert some appropriate node

		insertEmptyParagraph = false;
		if ( documentModel.hasSlugAtOffset( range.from ) ) {
			insertEmptyParagraph = true;
		} else {
			prevContentOffset = documentModel.data.getNearestContentOffset(
				cursor,
				-1
			);
			if ( prevContentOffset === -1 ) {
				insertEmptyParagraph = true;
			}
		}

		if ( insertEmptyParagraph ) {
			txInsert = ve.dm.TransactionBuilder.static.newFromInsertion(
				documentModel, cursor, emptyParagraph
			);
		} else {
			// Act as if cursor were at previous content offset
			cursor = prevContentOffset;
			node = surface.documentView.getBranchNodeFromOffset( cursor );
			txInsert = undefined;
			// Continue to traverseUpstream below. That will succeed because all
			// ContentBranchNodes have splitOnEnter === true.
		}
		insertEmptyParagraph = undefined;
	}

	// Assertion: if txInsert === undefined then node.splitOnEnter() === true

	function getSplitData( node ) {
		var stack = [];
		node.traverseUpstream( function ( node ) {
			if ( !node.splitOnEnter() ) {
				return false;
			}
			stack.splice(
				stack.length / 2,
				0,
				{ type: '/' + node.type },
				node.getModel().getClonedElement()
			);
			outermostNode = node;
			if ( e.shiftKey ) {
				return false;
			} else {
				return true;
			}
		} );
		return stack;
	}

	if ( txInsert === undefined ) {
		// This node has splitOnEnter = true. Traverse upstream until the first node
		// that has splitOnEnter = false, splitting each node as it is reached. Set
		// outermostNode to the last splittable node.
		splitData = getSplitData( node );

		outerParent = outermostNode.getParent();
		outerChildrenCount = outerParent.getChildren().length;

		if (
			// Parent removes empty last children
			outerParent.removeEmptyLastChildOnEnter() &&
			// This is the last child
			outerParent.getChildren()[ outerChildrenCount - 1 ] === outermostNode && (
				// Contains one empty ContentBranchNode
				( outermostNode.children.length === 1 && node.getModel().length === 0 ) ||
				// ..or is an empty ContentBranchNode
				( outermostNode.canContainContent() && outermostNode.getModel().length === 0 )
			)
		) {
			// Enter was pressed in an empty last child
			container = outerParent.getParent();
			advanceCursor = false;
			if ( outerChildrenCount === 1 ) {
				// The item we're about to remove is the only child
				// Remove the ouerParent
				txInsert = ve.dm.TransactionBuilder.static.newFromRemoval(
					documentModel, outerParent.getOuterRange()
				);
			} else {
				// Remove the item
				txInsert = ve.dm.TransactionBuilder.static.newFromRemoval(
					documentModel, outermostNode.getOuterRange()
				);
			}

			surface.model.change( txInsert );
			range = txInsert.translateRange( range );

			// The removed item was in a splitOnEnter node, split it
			if ( container.splitOnEnter() ) {
				splitData = getSplitData( container ).concat( emptyParagraph );
				txInsert = ve.dm.TransactionBuilder.static.newFromInsertion( documentModel, container.getOuterRange().to - 1, splitData );
			} else if ( outerParent.getChildren().length ) {
				// Otherwise just insert a paragraph
				txInsert = ve.dm.TransactionBuilder.static.newFromInsertion(
					documentModel, outerParent.getOuterRange().to, emptyParagraph
				);
			} else {
				// Parent was emptied, nothing more to do
				txInsert = null;
			}
			// Advance the cursor into the new paragraph
			advanceCursor = true;
		} else {
			// We must process the transaction first because getRelativeContentOffset can't help us yet
			txInsert = ve.dm.TransactionBuilder.static.newFromInsertion( documentModel, range.from, splitData );
		}
	}

	// Commit the transaction
	if ( txInsert ) {
		surface.model.change( txInsert );
		range = txInsert.translateRange( range );
	}

	// Now we can move the cursor forward
	if ( advanceCursor ) {
		cursor = documentModel.data.getRelativeContentOffset( range.from, 1 );
	} else {
		cursor = documentModel.data.getNearestContentOffset( range.from );
	}
	if ( cursor === -1 ) {
		// Cursor couldn't be placed in a nearby content node, so create an empty paragraph
		surface.model.change(
			ve.dm.TransactionBuilder.static.newFromInsertion(
				documentModel, range.from, emptyParagraph
			)
		);
		surface.model.setLinearSelection( new ve.Range( range.from + 1 ) );
	} else {
		surface.model.setLinearSelection( new ve.Range( cursor ) );
	}
	// Reset and resume polling
	surface.surfaceObserver.clear();
	// TODO: This setTimeout appears to be unnecessary (we're not render-locked)
	setTimeout( function () {
		surface.checkSequences();
	} );

	return true;
};

/* Registration */

ve.ce.keyDownHandlerFactory.register( ve.ce.LinearEnterKeyDownHandler );
