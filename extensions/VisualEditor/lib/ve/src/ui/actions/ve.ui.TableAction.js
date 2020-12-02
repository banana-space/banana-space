/*!
 * VisualEditor ContentEditable TableNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Table action.
 *
 * @class
 * @extends ve.ui.Action
 *
 * @constructor
 * @param {ve.ui.Surface} surface Surface to act on
 */
ve.ui.TableAction = function VeUiTableAction() {
	// Parent constructor
	ve.ui.TableAction.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.TableAction, ve.ui.Action );

/* Static Properties */

ve.ui.TableAction.static.name = 'table';

ve.ui.TableAction.static.methods = [
	'create', 'insert', 'moveRelative', 'move', 'delete', 'importTable',
	'changeCellStyle', 'mergeCells', 'enterTableCell', 'exitTableCell'
];

/* Methods */

/**
 * Creates a new table.
 *
 * @param {Object} [options] Table creation options
 * @param {boolean} [options.caption] Include a caption
 * @param {boolean} [options.header] Include a header row
 * @param {number} [options.cols=4] Number of columns
 * @param {number} [options.rows=3] Number of rows (not including optional header row)
 * @param {Object} [options.type='table'] Table node type, must inherit from table
 * @param {Object} [options.attributes] Attributes to give the table
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.create = function ( options ) {
	var i, type, tableElement, surfaceModel, fragment, data, numberOfCols, numberOfRows;

	options = options || {};

	type = options.type || 'table';
	tableElement = { type: type };
	surfaceModel = this.surface.getModel();
	fragment = surfaceModel.getFragment();
	data = [];
	numberOfCols = options.cols || 4;
	numberOfRows = options.rows || 3;

	if ( !( fragment.getSelection() instanceof ve.dm.LinearSelection ) ) {
		return false;
	}

	if ( options.attributes ) {
		tableElement.attributes = ve.copy( options.attributes );
	}

	data.push( tableElement );
	if ( options.caption ) {
		data.push(
			{ type: 'tableCaption' },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{ type: '/paragraph' },
			{ type: '/tableCaption' }
		);
	}
	data.push( { type: 'tableSection', attributes: { style: 'body' } } );
	if ( options.header ) {
		data = data.concat( ve.dm.TableRowNode.static.createData( { style: 'header', cellCount: numberOfCols } ) );
	}
	for ( i = 0; i < numberOfRows; i++ ) {
		data = data.concat( ve.dm.TableRowNode.static.createData( { style: 'data', cellCount: numberOfCols } ) );
	}
	data.push( { type: '/tableSection' } );
	data.push( { type: '/' + type } );

	fragment.insertContent( data, false );
	surfaceModel.setSelection( new ve.dm.TableSelection(
		fragment.getSelection().getRange(), 0, 0, 0, 0
	) );

	ve.track( 'activity.table', { action: 'create' } );

	return true;
};

/**
 * Inserts a new row or column into the currently focused table.
 *
 * @param {string} mode Insertion mode; 'row' to insert a new row, 'col' for a new column
 * @param {string} position Insertion position; 'before' to insert before the current selection,
 *   'after' to insert after it
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.insert = function ( mode, position ) {
	var index,
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection();

	if ( !( selection instanceof ve.dm.TableSelection ) ) {
		return false;
	}
	if ( mode === 'col' ) {
		index = position === 'before' ? selection.startCol : selection.endCol;
	} else {
		index = position === 'before' ? selection.startRow : selection.endRow;
	}
	if ( position === 'before' ) {
		if ( mode === 'col' ) {
			selection = selection.newFromAdjustment( documentModel, 1, 0 );
		} else {
			selection = selection.newFromAdjustment( documentModel, 0, 1 );
		}
		surfaceModel.setSelection( selection );
	}
	this.insertRowOrCol( selection.getTableNode( documentModel ), mode, index, position, selection );

	ve.track( 'activity.table', { action: 'insert-' + mode } );

	return true;
};

/**
 * Move a column or row relative to its current position
 *
 * @param {string} mode Move mode; 'col' or 'row'
 * @param {string} direction Direction; 'before' or 'after'
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.moveRelative = function ( mode, direction ) {
	var index,
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection(),
		matrix = selection.getTableNode( documentModel ).getMatrix();

	if ( mode === 'row' ) {
		if ( direction === 'before' ) {
			index = Math.max( 0, selection.startRow - 1 );
		} else {
			index = Math.min( matrix.getRowCount(), selection.endRow + 2 );
		}
	} else {
		if ( direction === 'before' ) {
			index = Math.max( 0, selection.startCol - 1 );
		} else {
			index = Math.min( matrix.getMaxColCount(), selection.endCol + 2 );
		}
	}
	return this.move( mode, index );
};

/**
 * Move a column or row.
 *
 * @param {string} mode Move mode; 'col' or 'row'
 * @param {string} index Row or column index to move to
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.move = function ( mode, index ) {
	var i, removedMatrix, position, newOffsets,
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection(),
		tableNode = selection.getTableNode( documentModel ),
		matrix = tableNode.getMatrix();

	if ( !( selection instanceof ve.dm.TableSelection ) ) {
		return false;
	}

	if ( mode === 'row' ) {
		removedMatrix = this.deleteRowsOrColumns( matrix, mode, selection.startRow, selection.endRow );
		if ( index > selection.endRow ) {
			index = index - selection.getRowCount();
		}
		newOffsets = [
			selection.fromCol,
			index,
			selection.toCol,
			index + selection.getRowCount() - 1
		];
	} else {
		removedMatrix = this.deleteRowsOrColumns( matrix, mode, selection.startCol, selection.endCol );
		if ( index > selection.endCol ) {
			index = index - selection.getColCount();
		}
		newOffsets = [
			index,
			selection.fromRow,
			index + selection.getColCount() - 1,
			selection.toRow
		];
	}
	if ( index === 0 ) {
		position = 'before';
	} else {
		index--;
		position = 'after';
	}
	for ( i = removedMatrix.length - 1; i >= 0; i-- ) {
		this.insertRowOrCol( tableNode, mode, index, position, null, removedMatrix[ i ] );
	}
	// Only set selection once for performance
	surfaceModel.setSelection( new ve.dm.TableSelection(
		// tableNode range was changed by deletion
		tableNode.getOuterRange(),
		newOffsets[ 0 ], newOffsets[ 1 ], newOffsets[ 2 ], newOffsets[ 3 ]
	) );

	ve.track( 'activity.table', { action: 'move-' + mode } );

	return true;
};

/**
 * Deletes selected rows, columns, or the whole table.
 *
 * @param {string} mode Deletion mode; 'row' to delete rows, 'col' for columns, 'table' to remove the whole table
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.delete = function ( mode ) {
	var tableNode, minIndex, maxIndex, isFull,
		documentModel = this.surface.getModel().getDocument(),
		selection = this.getTableSelectionFromSelection();

	if ( !( selection instanceof ve.dm.TableSelection ) ) {
		return false;
	}

	tableNode = selection.getTableNode( documentModel );
	// Either delete the table or rows or columns
	if ( mode === 'table' ) {
		this.deleteTable( tableNode );
	} else {
		if ( mode === 'col' ) {
			minIndex = selection.startCol;
			maxIndex = selection.endCol;
			isFull = selection.isFullRow( documentModel );
		} else {
			minIndex = selection.startRow;
			maxIndex = selection.endRow;
			isFull = selection.isFullCol( documentModel );
		}
		// Delete the whole table if all rows or cols get deleted
		if ( isFull ) {
			this.deleteTable( tableNode );
		} else {
			this.deleteRowsOrColumns( tableNode.matrix, mode, minIndex, maxIndex );
		}
	}

	ve.track( 'activity.table', { action: 'delete' + ( mode !== 'table' ? ( '-' + mode ) : '' ) } );

	return true;
};

/**
 * Import a table at the current selection, overwriting data cell by cell
 *
 * @param {ve.dm.TableNode} importedTableNode Table node to import
 * @param {boolean} importInternalList Import the table document's internalLiist
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.importTable = function ( importedTableNode, importInternalList ) {
	var i, l, row, col, cell, importedCell, cellRange, txBuilders,
		importedMatrix = importedTableNode.getMatrix(),
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection(),
		tableNode = selection.getTableNode( documentModel ),
		matrix = tableNode.getMatrix();

	// Increase size of table to fit imported table
	for ( i = 0, l = selection.startRow + importedMatrix.getRowCount() - matrix.getRowCount(); i < l; i++ ) {
		this.insertRowOrCol( tableNode, 'row', matrix.getRowCount() - 1, 'after' );
	}
	for ( i = 0, l = selection.startCol + importedMatrix.getMaxColCount() - matrix.getMaxColCount(); i < l; i++ ) {
		this.insertRowOrCol( tableNode, 'col', matrix.getMaxColCount() - 1, 'after' );
	}
	// Unmerge all cells in the target area
	for ( row = importedMatrix.getRowCount() - 1; row >= 0; row-- ) {
		for ( col = importedMatrix.getColCount( row ) - 1; col >= 0; col-- ) {
			cell = matrix.getCell( selection.fromRow + row, selection.fromCol + col );
			if ( cell.isPlaceholder() || cell.node.getColspan() > 1 || cell.node.getRowspan() > 1 ) {
				this.unmergeCell( matrix, cell.owner );
			}
		}
	}
	// Overwrite data
	for ( row = importedMatrix.getRowCount() - 1; row >= 0; row-- ) {
		for ( col = importedMatrix.getColCount( row ) - 1; col >= 0; col-- ) {
			cell = matrix.getCell( selection.fromRow + row, selection.fromCol + col );
			cellRange = cell.node.getRange();
			importedCell = importedMatrix.getCell( row, col );
			if ( importedCell.node.type !== cell.node.type ) {
				// Since the imported cell isn't the same type as the
				// existing cell, we can't quite trust our assumptions about
				// how it's supposed to work. As such, it's safer to outright
				// replace the cell rather than trying to be clever and switch
				// out the attributes / data. We shouldn't have gotten to this
				// point without it being Cellable, so this should at least
				// work.
				surfaceModel.change( ve.dm.TransactionBuilder.static.newFromReplacement(
					documentModel, cell.node.getOuterRange(),
					importedTableNode.getDocument().getData( importedCell.node.getOuterRange() )
				) );
			} else if ( !importedCell.isPlaceholder() ) {
				// Remove the existing cell contents
				surfaceModel.change( ve.dm.TransactionBuilder.static.newFromRemoval( documentModel, cellRange ) );
				// Attribute changes are performed separately, and removing the whole
				// cell could change the dimensions of the table
				txBuilders = [
					ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null,
						documentModel, cellRange.start - 1,
						ve.copy( importedCell.node.element.attributes )
					)
				];
				if ( importInternalList ) {
					txBuilders.push(
						ve.dm.TransactionBuilder.static.newFromDocumentInsertion.bind( null,
							documentModel, cellRange.start,
							importedTableNode.getDocument(),
							importedCell.node.getRange()
						)
					);
				} else {
					txBuilders.push(
						ve.dm.TransactionBuilder.static.newFromInsertion.bind( null,
							documentModel, cellRange.start,
							importedTableNode.getDocument().getData( importedCell.node.getRange() )
						)
					);
				}
				// Perform the insertion as a separate change so the internalList offsets are correct
				txBuilders.forEach( function ( txBuilder ) {
					surfaceModel.change( txBuilder() );
				} );
			} else {
				// Remove the existing cell completely
				surfaceModel.change( ve.dm.TransactionBuilder.static.newFromRemoval( documentModel, cell.node.getOuterRange() ) );
			}
		}
	}
	surfaceModel.setSelection(
		new ve.dm.TableSelection(
			tableNode.getOuterRange(),
			selection.startCol, selection.startRow,
			selection.startCol + importedMatrix.getMaxColCount() - 1,
			selection.startRow + importedMatrix.getRowCount() - 1
		)
	);
	return true;
};

/**
 * Change cell style
 *
 * @param {string} style Cell style; 'header' or 'data'
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.changeCellStyle = function ( style ) {
	var i, ranges,
		txBuilders = [],
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection();

	if ( !( selection instanceof ve.dm.TableSelection ) ) {
		return false;
	}

	ranges = selection.getOuterRanges( documentModel );
	for ( i = ranges.length - 1; i >= 0; i-- ) {
		txBuilders.push(
			ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null,
				documentModel, ranges[ i ].start, { style: style }
			)
		);
	}
	txBuilders.forEach( function ( txBuilder ) {
		surfaceModel.change( txBuilder() );
	} );

	ve.track( 'activity.table', { action: 'style-' + style } );

	return true;
};

/**
 * Merge multiple cells into one, or split a merged cell.
 *
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.mergeCells = function () {
	var i, l, r, c, cell, cells, hasNonPlaceholders, contentData,
		txBuilders = [],
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument(),
		selection = surfaceModel.getSelection(),
		matrix = selection.getTableNode( documentModel ).getMatrix();

	if ( !( selection instanceof ve.dm.TableSelection ) ) {
		return false;
	}

	if ( selection.isSingleCell( documentModel ) ) {
		// Split
		cells = selection.getMatrixCells( documentModel );
		this.unmergeCell( matrix, cells[ 0 ] );

		ve.track( 'activity.table', { action: 'cell-split' } );
	} else {
		// Merge
		if ( !selection.isMergeable( documentModel ) ) {
			return false;
		}
		cells = selection.getMatrixCells( documentModel );
		txBuilders.push(
			ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null,
				documentModel, cells[ 0 ].node.getOuterRange().start,
				{
					colspan: 1 + selection.endCol - selection.startCol,
					rowspan: 1 + selection.endRow - selection.startRow
				}
			)
		);

		// Find first cell with content
		for ( i = 0, l = cells.length; i < l; i++ ) {
			contentData = new ve.dm.ElementLinearData(
				documentModel.getStore(),
				documentModel.getData( cells[ i ].node.getRange() )
			);
			if ( contentData.hasContent() ) {
				// If the first cell contains content, we don't need to move any content
				if ( !i ) {
					contentData = null;
				}
				break;
			}
		}
		// Remove placeholders
		for ( i = cells.length - 1; i >= 1; i-- ) {
			txBuilders.push(
				ve.dm.TransactionBuilder.static.newFromRemoval.bind( null,
					documentModel, cells[ i ].node.getOuterRange()
				)
			);
		}
		// Move the first-found content to the merged cell
		if ( contentData ) {
			txBuilders.push(
				ve.dm.TransactionBuilder.static.newFromReplacement.bind( null,
					documentModel, cells[ 0 ].node.getRange(), contentData.data
				)
			);
		}
		txBuilders.forEach( function ( txBuilder ) {
			surfaceModel.change( txBuilder() );
		} );

		// Check for rows filled with entirely placeholders. If such a row exists, delete it.
		for ( r = selection.endRow; r >= selection.startRow; r-- ) {
			hasNonPlaceholders = false;
			for ( c = 0; ( cell = matrix.getCell( r, c ) ) !== undefined; c++ ) {
				if ( cell && !cell.isPlaceholder() ) {
					hasNonPlaceholders = true;
					break;
				}
			}
			if ( !hasNonPlaceholders ) {
				this.deleteRowsOrColumns( matrix, 'row', r, r );
			}
		}

		// Check for columns filled with entirely placeholders. If such a column exists, delete it.
		for ( c = selection.endCol; c >= selection.startCol; c-- ) {
			hasNonPlaceholders = false;
			for ( r = 0; ( cell = matrix.getCell( r, c ) ) !== undefined; r++ ) {
				if ( cell && !cell.isPlaceholder() ) {
					hasNonPlaceholders = true;
					break;
				}
			}
			if ( !hasNonPlaceholders ) {
				this.deleteRowsOrColumns( matrix, 'col', c, c );
			}
		}

		ve.track( 'activity.table', { action: 'cell-merge' } );
	}
	return true;
};

/**
 * Enter a table cell for editing
 *
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.enterTableCell = function () {
	var tableNode = this.findClosestTable();

	if ( !tableNode ) {
		return false;
	}

	tableNode.setEditing( true );
	this.surface.getView().focus();
	return true;
};

/**
 * Exit a table cell for table-structure-editing
 *
 * @return {boolean} Action was executed
 */
ve.ui.TableAction.prototype.exitTableCell = function () {
	var tableNode = this.findClosestTable();

	if ( !tableNode ) {
		return false;
	}

	tableNode.setEditing( false );
	this.surface.getView().focus();
	return true;
};

/* Low-level API */
// TODO: This API does only depends on the model so it should possibly be moved

/**
 * Deletes a whole table.
 *
 * @param {ve.dm.TableNode} tableNode Table node
 */
ve.ui.TableAction.prototype.deleteTable = function ( tableNode ) {
	this.surface.getModel().getLinearFragment( tableNode.getOuterRange() ).delete();
};

/**
 * Unmerge a cell
 *
 * @param {ve.dm.TableMatrix} matrix Table matrix the cell is in
 * @param {ve.dm.TableMatrixCell} ownerCell The cell to unmerge
 */
ve.ui.TableAction.prototype.unmergeCell = function ( matrix, ownerCell ) {
	var col, row, cell,
		txBuilders = [],
		colspan = ownerCell.node.getColspan(),
		rowspan = ownerCell.node.getRowspan(),
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument();

	txBuilders.push(
		ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null,
			documentModel, ownerCell.node.getOuterRange().start,
			{ colspan: 1, rowspan: 1 }
		)
	);
	for ( row = ownerCell.row + rowspan - 1; row >= ownerCell.row; row-- ) {
		for ( col = ownerCell.col + colspan - 1; col >= ownerCell.col; col-- ) {
			cell = matrix.getCell( row, col );
			if ( cell.isPlaceholder() ) {
				txBuilders.push(
					this.replacePlaceholder(
						matrix,
						cell,
						{ style: ownerCell.node.getStyle() }
					)
				);
			}
		}
	}
	txBuilders.forEach( function ( txBuilder ) {
		surfaceModel.change( txBuilder() );
	} );
};

/**
 * Inserts a new row or column.
 *
 * Example: a new row can be inserted after the 2nd row using
 *
 *    insertRowOrCol( table, 'row', 1, 'after' );
 *
 * @param {ve.dm.TableNode} tableNode Table node
 * @param {string} mode Insertion mode; 'row' or 'col'
 * @param {number} index Row or column index of the base row or column.
 * @param {string} position Insertion position; 'before' or 'after'
 * @param {ve.dm.TableSelection} [selection] Selection to move to after insertion
 * @param {Object} [dataMatrixLine] Data to insert
 * @param {Array} [dataMatrixLine.rowData] Row data if inserting a row
 * @param {ve.dm.TableMatrixCell[]} [dataMatrixLine.cells] Table cells to insert
 */
ve.ui.TableAction.prototype.insertRowOrCol = function ( tableNode, mode, index, position, selection, dataMatrixLine ) {
	var refIndex, cells, refCells, before, cellData,
		offset, range, i, l, cell, refCell, style,
		matrix = tableNode.matrix,
		insertCells = [],
		insertData = [],
		txBuilders = [],
		updated = {},
		inserts = [],
		surfaceModel = this.surface.getModel();

	before = position === 'before';

	// Note: when we insert a new row (or column) we might need to increment a span property
	// instead of inserting a new cell.
	// To achieve this we look at the so called base row and a so called reference row.
	// The base row is the one after or before which the new row will be inserted.
	// The reference row is the one which is currently at the place of the new one.
	// E.g. consider inserting a new row after the second: the base row is the second, the
	// reference row is the third.
	// A span must be increased if the base cell and the reference cell have the same 'owner'.
	// E.g.:  C* | P**; C | P* | P**, i.e., one of the two cells might be the owner of the other,
	// or vice versa, or both a placeholders of a common cell.

	// The index of the reference row or column
	refIndex = index + ( before ? -1 : 1 );
	// Cells of the selected row or column
	if ( mode === 'row' ) {
		cells = matrix.getRow( index ) || [];
		refCells = matrix.getRow( refIndex ) || [];
	} else {
		cells = matrix.getColumn( index ) || [];
		refCells = matrix.getColumn( refIndex ) || [];
	}

	for ( i = 0, l = Math.max( cells.length, dataMatrixLine ? dataMatrixLine.cells.length : 0 ); i < l; i++ ) {
		cell = cells[ i ];
		if ( !cell ) {
			if ( dataMatrixLine && dataMatrixLine.cells[ i ] ) {
				// If we've been given data to fill the empty cells with, do so
				insertCells.push( dataMatrixLine.cells[ i ] );
			}
			// Either way, continue on to the next cell
			continue;
		}
		refCell = refCells[ i ];
		// Detect if span update is necessary
		if ( refCell && ( cell.isPlaceholder() || refCell.isPlaceholder() ) ) {
			if ( cell.node === refCell.node ) {
				cell = cell.owner || cell;
				if ( !updated[ cell.key ] ) {
					// Note: we can safely record span modifications as they do not affect range offsets.
					txBuilders.push( this.incrementSpan( cell, mode ) );
					updated[ cell.key ] = true;
				}
				// Resolve merged cell conflicts when moving
				if ( dataMatrixLine && dataMatrixLine.cells[ i ].owner.data && !dataMatrixLine.cells[ i ].owner.conflicted ) {
					if ( dataMatrixLine.cells[ i ].isPlaceholder() ) {
						// If placeholders conflict, collapse their owners
						dataMatrixLine.cells[ i ].owner.data[ 0 ].attributes.colspan = 1;
						dataMatrixLine.cells[ i ].owner.data[ 0 ].attributes.rowspan = 1;
					}
					// Mark owner (could be self) as conflicted so placeholders know it didn't get inserted
					dataMatrixLine.cells[ i ].owner.conflicted = true;
				}
				continue;
			}
		}
		// If it is not a span changer, we record the base cell as a reference for insertion
		inserts.push( cell );
		if ( dataMatrixLine ) {
			insertCells.push( dataMatrixLine.cells[ i ] );
		}
	}

	// Inserting a new row differs completely from inserting a new column:
	// For a new row, a new row node is created, and inserted relative to an existing row node.
	// For a new column, new cells are inserted into existing row nodes at appropriate positions,
	// i.e., relative to an existing cell node.
	if ( mode === 'row' ) {
		if ( !dataMatrixLine ) {
			insertData = ve.dm.TableRowNode.static.createData( {
				cellCount: inserts.length,
				style: cells.map( function ( cell ) {
					return cell.node.getStyle();
				} )
			} );
		} else {
			insertData.push( dataMatrixLine.row[ 0 ] );
			insertCells.forEach( function ( cell ) {
				if ( cell && cell.data ) {
					insertData = insertData.concat( cell.data );
				} else if ( !( cell && cell.isPlaceholder() && cell.owner.data && !cell.owner.conflicted ) ) {
					// If a placeholder, and the owner was not inserted, created a blank cell
					insertData = insertData.concat( ve.dm.TableCellNode.static.createData() );
				}
			} );
			insertData.push( dataMatrixLine.row[ 1 ] );
		}
		range = matrix.getRowNode( index ).getOuterRange();
		offset = before ? range.start : range.end;
		txBuilders.push( ve.dm.TransactionBuilder.static.newFromInsertion.bind( null, surfaceModel.getDocument(), offset, insertData ) );
	} else {
		// Make sure that the inserts are in descending offset order
		// so that the transactions do not affect subsequent range offsets.
		inserts.sort( ve.dm.TableMatrixCell.static.sortDescending );

		// For inserting a new cell we need to find a reference cell node
		// which we can use to get a proper insertion offset.
		for ( i = 0; i < inserts.length; i++ ) {
			cell = inserts[ i ];
			if ( !cell ) {
				continue;
			}
			// If the cell is a placeholder this will find a close cell node in the same row
			refCell = matrix.findClosestCell( cell );
			if ( refCell ) {
				range = refCell.node.getOuterRange();
				// If the found cell is before the base cell the new cell must be placed after it, in any case,
				// Only if the base cell is not a placeholder we have to consider the insert mode.
				if ( refCell.col < cell.col || ( refCell.col === cell.col && !before ) ) {
					offset = range.end;
				} else {
					offset = range.start;
				}
				style = refCell.node.getStyle();
			} else {
				// If there are only placeholders in the row, we use the row node's inner range
				// for the insertion offset
				range = matrix.getRowNode( cell.row ).getRange();
				offset = before ? range.start : range.end;
				style = cells[ 0 ].node.getStyle();
			}
			if ( !dataMatrixLine ) {
				cellData = ve.dm.TableCellNode.static.createData( { style: style } );
			} else {
				cell = dataMatrixLine.cells[ cell.row ];
				cellData = [];
				if ( cell && cell.data ) {
					cellData = cell.data;
				} else if ( !( cell && cell.isPlaceholder() && cell.owner.data && !cell.owner.conflicted ) ) {
					// If a placeholder, and the owner was not inserted, created a blank cell
					cellData = ve.dm.TableCellNode.static.createData();
				}
			}
			txBuilders.push( ve.dm.TransactionBuilder.static.newFromInsertion.bind( null, surfaceModel.getDocument(), offset, cellData ) );
		}
	}
	txBuilders.forEach( function ( txBuilder ) {
		var tx = txBuilder();
		selection = selection && selection.translateByTransaction( tx );
		surfaceModel.change( tx );
	} );
	if ( selection ) {
		surfaceModel.change( null, selection );
	}
};

/**
 * Increase the span of a cell by one.
 *
 * @param {ve.dm.TableMatrixCell} cell Table matrix cell
 * @param {string} mode Span to increment; 'row' or 'col'
 * @return {Function} Zero-argument function returning a ve.dm.Transaction
 */
ve.ui.TableAction.prototype.incrementSpan = function ( cell, mode ) {
	var data,
		surfaceModel = this.surface.getModel();

	if ( mode === 'row' ) {
		data = { rowspan: cell.node.getRowspan() + 1 };
	} else {
		data = { colspan: cell.node.getColspan() + 1 };
	}

	return ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null, surfaceModel.getDocument(), cell.node.getOuterRange().start, data );
};

/**
 * Decreases the span of a cell so that the given interval is removed.
 *
 * @param {ve.dm.TableMatrixCell} cell Table matrix cell
 * @param {string} mode Span to decrement 'row' or 'col'
 * @param {number} minIndex Smallest row or column index (inclusive)
 * @param {number} maxIndex Largest row or column index (inclusive)
 * @return {Function} Zero-argument function returning a ve.dm.Transaction
 */
ve.ui.TableAction.prototype.decrementSpan = function ( cell, mode, minIndex, maxIndex ) {
	var span, data,
		surfaceModel = this.surface.getModel();

	span = ( minIndex - cell[ mode ] ) + Math.max( 0, cell[ mode ] + cell.node.getSpans()[ mode ] - 1 - maxIndex );
	if ( mode === 'row' ) {
		data = { rowspan: span };
	} else {
		data = { colspan: span };
	}

	return ve.dm.TransactionBuilder.static.newFromAttributeChanges.bind( null, surfaceModel.getDocument(), cell.node.getOuterRange().start, data );
};

/**
 * Deletes rows or columns within a given range.
 *
 * e.g. rows 2-4 can be deleted using
 *
 *    ve.ui.TableAction.deleteRowsOrColumns( matrix, 'row', 1, 3 );
 *
 * @param {ve.dm.TableMatrix} matrix Table matrix
 * @param {string} mode 'row' or 'col'
 * @param {number} minIndex Smallest row or column index to be deleted
 * @param {number} maxIndex Largest row or column index to be deleted (inclusive)
 * @return {Array} Plain sub-matrix of items removed. In column mode this matrix is transposed.
 */
ve.ui.TableAction.prototype.deleteRowsOrColumns = function ( matrix, mode, minIndex, maxIndex ) {
	var row, col, i, l, cell, key,
		span, startRow, startCol, endRow, endCol,
		rowNode, rowRange, rowData,
		removedMatrix = [],
		cells = [],
		txBuilders = [],
		adapted = {},
		actions = [],
		surfaceModel = this.surface.getModel(),
		documentModel = surfaceModel.getDocument();

	// Deleting cells can have two additional consequences:
	// 1. The cell is a Placeholder. The owner's span must be decreased.
	// 2. The cell is owner of placeholders which get orphaned by the deletion.
	//    The first of the placeholders now becomes the real cell, with the span adjusted.
	//    It also inherits all of the properties and content of the removed cell.
	// Insertions and deletions of cells must be done in an appropriate order, so that the transactions
	// do not interfere with each other. To achieve that, we record insertions and deletions and
	// sort them by the position of the cell (row, column) in the table matrix.

	if ( mode === 'row' ) {
		for ( row = minIndex; row <= maxIndex; row++ ) {
			cells = cells.concat( matrix.getRow( row ) );
		}
	} else {
		for ( col = minIndex; col <= maxIndex; col++ ) {
			cells = cells.concat( matrix.getColumn( col ) );
		}
	}

	for ( i = 0, l = cells.length; i < l; i++ ) {
		cell = cells[ i ];
		if ( !cell ) {
			continue;
		}
		if ( cell.isPlaceholder() ) {
			key = cell.owner.key;
			if ( !adapted[ key ] ) {
				// Note: we can record this transaction immediately, as it does not have an effect on the
				// node range
				txBuilders.push( this.decrementSpan( cell.owner, mode, minIndex, maxIndex ) );
				adapted[ key ] = true;
			}
			continue;
		}

		// Detect if the owner of a spanning cell gets deleted and
		// leaves orphaned placeholders
		span = cell.node.getSpans()[ mode ];
		if ( cell[ mode ] + span - 1 > maxIndex ) {
			// add inserts for orphaned place holders
			if ( mode === 'col' ) {
				startRow = cell.row;
				startCol = maxIndex + 1;
			} else {
				startRow = maxIndex + 1;
				startCol = cell.col;
			}
			endRow = cell.row + cell.node.getRowspan() - 1;
			endCol = cell.col + cell.node.getColspan() - 1;

			// Record the insertion to apply it later
			actions.push( {
				action: 'insert',
				cell: matrix.getCell( startRow, startCol ),
				colspan: 1 + endCol - startCol,
				rowspan: 1 + endRow - startRow,
				style: cell.node.getStyle(),
				content: documentModel.getData( cell.node.getRange() )
			} );
		}

		// Cell nodes only get deleted when deleting columns (otherwise row nodes)
		if ( mode === 'col' ) {
			actions.push( { action: 'delete', cell: cell } );
		}
	}

	// Make sure that the actions are in descending offset order
	// so that the transactions do not affect subsequent range offsets.
	// Sort recorded actions to make sure the transactions will not interfere with respect to offsets
	actions.sort( function ( a, b ) {
		return ve.dm.TableMatrixCell.static.sortDescending( a.cell, b.cell );
	} );

	if ( mode === 'row' ) {
		// First replace orphaned placeholders which are below the last deleted row,
		// thus, this works with regard to transaction offsets
		for ( i = 0; i < actions.length; i++ ) {
			txBuilders.push( this.replacePlaceholder( matrix, actions[ i ].cell, actions[ i ] ) );
		}
		// Remove rows in reverse order to have valid transaction offsets
		for ( row = maxIndex; row >= minIndex; row-- ) {
			rowNode = matrix.getRowNode( row );
			txBuilders.push( ve.dm.TransactionBuilder.static.newFromRemoval.bind( null, documentModel, rowNode.getOuterRange() ) );

			// Store removed data for moving
			cells = matrix.getRow( row );
			rowRange = rowNode.getOuterRange();
			rowData = documentModel.getData( new ve.Range( rowRange.start, rowRange.start + 1 ), true ).concat(
				documentModel.getData( new ve.Range( rowRange.end - 1, rowRange.end ), true )
			);
			// Remove all but start and end tags
			rowData.splice( 1, rowData.length - 2 );
			removedMatrix[ row - minIndex ] = {
				row: rowData,
				cells: cells.map( function ( cell ) {
					if ( cell && !cell.isPlaceholder() ) {
						cell.data = documentModel.getData( cell.node.getOuterRange(), true );
						// When re-insterted the span can not exceed the size of the selection
						if ( cell.data[ 0 ].attributes.rowspan > 1 + maxIndex - minIndex ) {
							cell.data = null;
						}
					}
					return cell;
				} )
			};
		}
	} else {
		for ( i = 0; i < actions.length; i++ ) {
			if ( actions[ i ].action === 'insert' ) {
				txBuilders.push( this.replacePlaceholder( matrix, actions[ i ].cell, actions[ i ] ) );
			} else {
				txBuilders.push( ve.dm.TransactionBuilder.static.newFromRemoval.bind( null, documentModel, actions[ i ].cell.node.getOuterRange() ) );
				col = actions[ i ].cell.col - minIndex;
				actions[ i ].cell.data = documentModel.getData( actions[ i ].cell.node.getOuterRange(), true );
			}
		}
		for ( col = maxIndex; col >= minIndex; col-- ) {
			removedMatrix[ col - minIndex ] = {
				cells: matrix.getColumn( col ).map( function ( cell ) {
					if ( cell && !cell.isPlaceholder() ) {
						cell.data = documentModel.getData( cell.node.getOuterRange(), true );
						// When re-insterted the span can not exceed the size of the selection
						if ( cell.data[ 0 ].attributes.colspan > 1 + maxIndex - minIndex ) {
							cell.data = null;
						}
					}
					return cell;
				} )
			};
		}
	}
	surfaceModel.change( null, new ve.dm.NullSelection() );
	txBuilders.forEach( function ( txBuilder ) {
		surfaceModel.change( txBuilder() );
	} );
	return removedMatrix;
};

/**
 * Inserts a new cell for an orphaned placeholder.
 *
 * @param {ve.dm.TableMatrix} matrix Table matrix
 * @param {ve.dm.TableMatrixCell} placeholder Placeholder cell to replace
 * @param {Object} [options] Options to pass to ve.dm.TableCellNode.static.createData
 * @return {Function} Zero-argument function returning a ve.dm.Transaction
 */
ve.ui.TableAction.prototype.replacePlaceholder = function ( matrix, placeholder, options ) {
	var range, offset, data,
		// For inserting the new cell a reference cell node
		// which is used to get an insertion offset.
		refCell = matrix.findClosestCell( placeholder ),
		surfaceModel = this.surface.getModel();

	if ( refCell ) {
		range = refCell.node.getOuterRange();
		offset = ( placeholder.col < refCell.col ) ? range.start : range.end;
	} else {
		// if there are only placeholders in the row, the row node's inner range is used
		range = matrix.getRowNode( placeholder.row ).getRange();
		offset = range.start;
	}
	data = ve.dm.TableCellNode.static.createData( options );
	return ve.dm.TransactionBuilder.static.newFromInsertion.bind( null, surfaceModel.getDocument(), offset, data );
};

/**
 * Find the closest table node to the current selection
 *
 * @return {ve.ce.TableNode|null} a TableNode, if one is found
 */
ve.ui.TableAction.prototype.findClosestTable = function () {
	var tableNode, node,
		selection = this.surface.getModel().getSelection();

	if ( selection instanceof ve.dm.TableSelection ) {
		tableNode = this.surface.getView().documentView.getBranchNodeFromOffset( selection.tableRange.start + 1 );
	} else if ( selection instanceof ve.dm.LinearSelection ) {
		node = this.surface.getView().documentView.getBranchNodeFromOffset( selection.getRange().start );
		if ( node ) {
			tableNode = node.$element.closest( 'table' ).data( 'view' );
		}
	}

	return tableNode;
};

/**
 * Get a TableSelection that contains the current selection
 *
 * @return {ve.dm.TableSelection|boolean}
 */
ve.ui.TableAction.prototype.getTableSelectionFromSelection = function () {
	// If the current selection is contained within a table, we'd like the relevant TableSelection.
	var tableNode, cellNode, cell,
		surfaceModel = this.surface.getModel(),
		selection = surfaceModel.getSelection();

	if ( selection instanceof ve.dm.TableSelection ) {
		return selection;
	}

	tableNode = this.findClosestTable();
	if ( !tableNode ) {
		return false;
	}

	cellNode = tableNode.getActiveCellNode();
	if ( !cellNode ) {
		// This is actually strange -- we've got a selection inside a table, but no active table cell.
		return false;
	}

	cell = tableNode.getModel().matrix.lookupCell( cellNode.getModel() );
	if ( !cell ) {
		return false;
	}

	selection = new ve.dm.TableSelection(
		tableNode.getModel().getOuterRange(),
		cell.col, cell.row
	);
	selection = selection.expand( surfaceModel.getDocument() );
	return selection;
};

/* Registration */

ve.ui.actionFactory.register( ve.ui.TableAction );
