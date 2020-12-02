/*!
 * VisualEditor SumCellsContextItem class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Show the sum and average of numeric values across a table selection.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.SumCellsContextItem = function VeUiSumCellsContextItem( context, model, config ) {
	// Parent constructor
	ve.ui.SumCellsContextItem.super.call( this, context, model, config );

	// Initialization
	this.$element.addClass( 've-ui-sumCellsContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.SumCellsContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.SumCellsContextItem.static.name = 'sumCells';

ve.ui.SumCellsContextItem.static.icon = 'mathematics';

ve.ui.SumCellsContextItem.static.editable = false;

ve.ui.SumCellsContextItem.static.deletable = false;

ve.ui.SumCellsContextItem.static.embeddable = false;

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.SumCellsContextItem.static.isCompatibleWith = function ( model ) {
	return model instanceof ve.dm.Node && model.isCellable();
};

/**
 * @inheritdoc
 */
ve.ui.SumCellsContextItem.prototype.setup = function () {
	// If not disabled, selection must be table and spanning multiple matrix cells
	var sum, cells, viewCell,
		count = 0,
		selection = this.getFragment().getSurface().getSelection(),
		documentModel = this.getFragment().getDocument(),
		documentView = this.context.getSurface().getView().getDocument();

	// There's some situations involving transclusion table cells which
	// can make us have a LinearSelection here, so make sure this will
	// work:
	if ( selection instanceof ve.dm.TableSelection ) {
		cells = selection.getMatrixCells( documentModel, true );
		if ( cells.length > 1 ) {
			sum = cells.reduce( function ( sum, cell ) {
				var number;
				if ( !cell.isPlaceholder() ) {
					// Get text from view rendering to catch numbers in alien nodes, etc.
					viewCell = documentView.getBranchNodeFromOffset( cell.node.getRange().start );
					number = ve.init.platform.parseNumber(
						viewCell.$element.text()
					);
					if ( !isNaN( number ) ) {
						count++;
						return sum + number;
					}
				}
				return sum;
			}, 0 );
		}
	}

	// Only show if more than one numeric value was selected
	if ( count > 1 ) {
		this.setLabel(
			ve.msg( 'visualeditor-table-sum',
				ve.init.platform.formatNumber( sum ),
				ve.init.platform.formatNumber( sum / count )
			)
		);
	} else {
		this.$element.detach();
	}
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.SumCellsContextItem );
