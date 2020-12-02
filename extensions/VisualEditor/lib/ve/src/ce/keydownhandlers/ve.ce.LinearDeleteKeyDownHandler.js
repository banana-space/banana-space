/*!
 * VisualEditor ContentEditable linear delete key down handler
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/* istanbul ignore next */
/**
 * Delete key down handler for linear selections.
 *
 * @class
 * @extends ve.ce.KeyDownHandler
 *
 * @constructor
 */
ve.ce.LinearDeleteKeyDownHandler = function VeCeLinearDeleteKeyDownHandler() {
	// Parent constructor - never called because class is fully static
	// ve.ui.LinearDeleteKeyDownHandler.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.LinearDeleteKeyDownHandler, ve.ce.KeyDownHandler );

/* Static properties */

ve.ce.LinearDeleteKeyDownHandler.static.name = 'linearDelete';

ve.ce.LinearDeleteKeyDownHandler.static.keys = [ OO.ui.Keys.BACKSPACE, OO.ui.Keys.DELETE ];

ve.ce.LinearDeleteKeyDownHandler.static.supportedSelections = [ 'linear' ];

/* Static methods */

/**
 * @inheritdoc
 *
 * The handler just schedules a poll to observe the native content removal, unless
 * one of the following is true:
 * - The ctrlKey is down; or
 * - The selection is expanded; or
 * - We are directly adjacent to an element node in the deletion direction.
 * In these cases, it will perform the content removal itself.
 */
ve.ce.LinearDeleteKeyDownHandler.static.execute = function ( surface, e ) {
	var docLength, startNode, position, skipNode, pairNode, linkNode, range,
		documentModelSelectedNodes, i, node, nodeRange, nodeOuterRange, matrix, col, row,
		direction = e.keyCode === OO.ui.Keys.DELETE ? 1 : -1,
		unit = ( e.altKey === true || e.ctrlKey === true ) ? 'word' : 'character',
		offset = 0,
		rangeToRemove = surface.getModel().getSelection().getRange(),
		documentModel = surface.getModel().getDocument(),
		data = documentModel.data;

	if ( surface.isReadOnly() ) {
		e.preventDefault();
		return true;
	}

	if ( direction === 1 && e.shiftKey && ve.getSystemPlatform() !== 'mac' ) {
		// Shift+Del on non-Mac platforms performs 'cut', so
		// don't handle it here.
		return false;
	}

	// Use native behaviour then poll if collapsed, unless we are adjacent to some hard tag
	// (or CTRL is down, in which case we can't reliably predict whether the native behaviour
	// would delete far enough to remove some element)
	if ( rangeToRemove.isCollapsed() && !e.ctrlKey ) {
		position = ve.adjacentDomPosition(
			{
				node: surface.nativeSelection.focusNode,
				offset: surface.nativeSelection.focusOffset
			},
			direction,
			{ stop: ve.isHardCursorStep }
		);
		skipNode = position.steps[ position.steps.length - 1 ].node;
		if ( skipNode.nodeType === Node.TEXT_NODE ) {
			surface.eventSequencer.afterOne( {
				keydown: surface.surfaceObserver.pollOnce.bind( surface.surfaceObserver )
			} );
			return true;
		}

		// If the native action would delete an outside nail, move *two* cursor positions
		// in the deletion direction, to get inside the link just past the inside nail,
		// then preventDefault
		if (
			direction > 0 ?
				skipNode.classList.contains( 've-ce-nail-pre-open' ) :
				skipNode.classList.contains( 've-ce-nail-post-close' )
		) {
			position = ve.adjacentDomPosition(
				position,
				direction,
				{ stop: ve.isHardCursorStep }
			);
			range = document.createRange();
			range.setStart( position.node, position.offset );
			surface.nativeSelection.removeAllRanges();
			surface.nativeSelection.addRange( range );
			surface.updateActiveAnnotations();
			e.preventDefault();
			return true;
		}

		// If inside an empty link, delete it and preventDefault
		if (
			skipNode.classList &&
			skipNode.classList.contains(
				direction > 0 ?
					've-ce-nail-pre-close' :
					've-ce-nail-post-open'
			) &&
			( pairNode = (
				direction > 0 ?
					skipNode.previousSibling :
					skipNode.nextSibling
			) ) &&
			pairNode.classList &&
			pairNode.classList.contains(
				direction > 0 ?
					've-ce-nail-post-open' :
					've-ce-nail-pre-close'
			)
		) {
			linkNode = skipNode.parentNode;
			range = document.createRange();
			// Set start to link's offset, minus 1 to allow for outer nail deletion
			// (browsers actually tend to adjust range offsets automatically
			// for previous sibling deletion, but just in case …)
			range.setStart( linkNode.parentNode, ve.parentIndex( linkNode ) - 1 );
			// Remove the outer nails, then the link itself
			linkNode.parentNode.removeChild( linkNode.previousSibling );
			linkNode.parentNode.removeChild( linkNode.nextSibling );
			linkNode.parentNode.removeChild( linkNode );

			surface.nativeSelection.removeAllRanges();
			surface.nativeSelection.addRange( range );
			surface.updateActiveAnnotations();
			e.preventDefault();
			return true;
		}

		// If the native action would delete an inside nail, move *two* cursor positions
		// in the deletion direction, to get outside the link just past the outside nail,
		// then preventDefault
		if (
			direction > 0 ?
				skipNode.classList.contains( 've-ce-nail-pre-close' ) :
				skipNode.classList.contains( 've-ce-nail-post-open' )
		) {
			position = ve.adjacentDomPosition(
				position,
				direction,
				{ stop: ve.isHardCursorStep }
			);
			range = document.createRange();
			range.setStart( position.node, position.offset );
			surface.nativeSelection.removeAllRanges();
			surface.nativeSelection.addRange( range );
			surface.updateActiveAnnotations();
			e.preventDefault();
			return true;
		}

		offset = rangeToRemove.start;
		if ( !e.ctrlKey && (
			( direction < 0 && !data.isElementData( offset - 1 ) ) ||
			( direction > 0 && !data.isElementData( offset ) )
		) ) {
			surface.eventSequencer.afterOne( {
				keydown: surface.surfaceObserver.pollOnce.bind( surface.surfaceObserver )
			} );
			return true;
		}
	}

	// Else range is uncollapsed or is adjacent to a non-nail element.
	if ( rangeToRemove.isCollapsed() ) {
		// Expand rangeToRemove
		rangeToRemove = documentModel.getRelativeRange( rangeToRemove, direction, unit, true );
		if ( surface.getActiveNode() && !surface.getActiveNode().getRange().containsRange( rangeToRemove ) ) {
			e.preventDefault();
			return true;
		}

		// Prevent backspacing/deleting over table cells, select the cell instead
		documentModelSelectedNodes = documentModel.selectNodes( rangeToRemove, 'siblings' );
		for ( i = 0; i < documentModelSelectedNodes.length; i++ ) {
			node = documentModelSelectedNodes[ i ].node;
			nodeOuterRange = documentModelSelectedNodes[ i ].nodeOuterRange;
			if ( node instanceof ve.dm.TableNode ) {
				if ( rangeToRemove.containsOffset( nodeOuterRange.start ) ) {
					surface.getModel().setSelection( new ve.dm.TableSelection(
						nodeOuterRange, 0, 0
					) );
				} else {
					matrix = node.getMatrix();
					row = matrix.getRowCount() - 1;
					col = matrix.getColCount( row ) - 1;
					surface.getModel().setSelection( new ve.dm.TableSelection(
						nodeOuterRange, col, row
					) );
				}
				e.preventDefault();
				return true;
			}
		}

		offset = rangeToRemove.start;
		docLength = documentModel.getDocumentRange().getLength();
		if ( offset < docLength - 1 ) {
			while ( offset < docLength - 1 && data.isCloseElementData( offset ) ) {
				offset++;
			}
			// If the user tries to delete a focusable node from a collapsed selection,
			// just select the node and cancel the deletion.
			startNode = documentModel.getDocumentNode().getNodeFromOffset( offset + 1 );
			if ( startNode.isFocusable() ) {
				surface.getModel().setLinearSelection( startNode.getOuterRange() );
				e.preventDefault();
				return true;
			}
		}
		if ( rangeToRemove.isCollapsed() ) {
			// For some reason (most likely: we're at the beginning or end of the document) we can't
			// expand the range. So, should we delete something or not?
			// The rules are:
			// * if we're literally at the start or end, and are in a content node, don't do anything
			// * if we're in a plain paragraph, don't do anything
			// * if we're in a list item and it's empty get rid of the item
			startNode = documentModel.getDocumentNode().getNodeFromOffset( offset - 1 );
			nodeRange = startNode.getOuterRange();
			if (
				// The node is not unwrappable (e.g. table cells, text nodes)
				!startNode.isUnwrappable() ||
				// Content item at the start / end?
				(
					( startNode.canContainContent() || surface.attachedRoot === startNode ) &&
					( nodeRange.start === 0 || nodeRange.end === docLength )
				)
			) {
				e.preventDefault();
				return true;
			} else {
				// Expand our removal to reflect what we actually need to remove
				switch ( startNode.getType() ) {
					case 'list':
						// If this is an empty list, we wind up with the list node instead of the list item
						// to make the unwrapping work, we need to remove the list and the item
						rangeToRemove = new ve.Range( nodeRange.start, nodeRange.start + 2 );
						break;
					case 'listItem':
						rangeToRemove = new ve.Range( nodeRange.start, nodeRange.start + 1 );
						break;
					default:
						if ( direction > 0 ) {
							rangeToRemove = new ve.Range( rangeToRemove.start, nodeRange.end );
						} else {
							rangeToRemove = new ve.Range( nodeRange.start, rangeToRemove.start - 1 );
						}
				}
			}
		}
	}

	surface.getModel().getLinearFragment( rangeToRemove, true ).delete( direction ).select();
	// Rerender selection even if it didn't change
	// TODO: is any of this necessary?
	surface.focus();
	surface.surfaceObserver.clear();
	e.preventDefault();
	return true;
};

/* Registration */

ve.ce.keyDownHandlerFactory.register( ve.ce.LinearDeleteKeyDownHandler );
