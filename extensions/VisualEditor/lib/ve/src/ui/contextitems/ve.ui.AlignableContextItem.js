/*!
 * VisualEditor Alignable class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Context item for an alignable node.
 *
 * @class
 * @extends ve.ui.LinearContextItem
 *
 * @param {ve.ui.Context} context Context item is in
 * @param {ve.dm.Model} model Model item is related to
 * @param {Object} config Configuration options
 */
ve.ui.AlignableContextItem = function VeUiAlignableContextItem( context, model, config ) {
	var align;

	// Parent constructor
	ve.ui.AlignableContextItem.super.call( this, context, model, config );

	align = model.getAttribute( 'align' );

	this.align = new ve.ui.AlignWidget( {
		dir: this.context.getSurface().getDir()
	} );
	this.align.selectItemByData( align );
	this.align.connect( this, { choose: 'onAlignChoose' } );

	if ( OO.ui.isMobile() ) {
		this.align.items.forEach( function ( item ) {
			item.setInvisibleLabel( true );
		} );
	}

	// Initialization
	this.$element.addClass( 've-ui-alignableContextItem' );
};

/* Inheritance */

OO.inheritClass( ve.ui.AlignableContextItem, ve.ui.LinearContextItem );

/* Static Properties */

ve.ui.AlignableContextItem.static.name = 'alignable';

ve.ui.AlignableContextItem.static.icon = 'alignLeft';

ve.ui.AlignableContextItem.static.label = OO.ui.deferMsg( 'visualeditor-alignablecontextitem-title' );

ve.ui.AlignableContextItem.static.editable = false;

ve.ui.AlignableContextItem.static.exclusive = false;

ve.ui.AlignableContextItem.static.isCompatibleWith = function ( model ) {
	return model instanceof ve.dm.Node && model.isAlignable();
};

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.AlignableContextItem.prototype.setup = function () {
	this.align.setDisabled( this.context.getSurface().isReadOnly() );

	// Parent method
	ve.ui.AlignableContextItem.super.prototype.setup.apply( this, arguments );
};

/**
 * @inheritdoc
 */
ve.ui.AlignableContextItem.prototype.renderBody = function () {
	this.$body.empty().append( this.align.$element );
};

ve.ui.AlignableContextItem.prototype.onAlignChoose = function ( item ) {
	this.getFragment().changeAttributes( { align: item.getData() } );
};

/* Registration */

ve.ui.contextItemFactory.register( ve.ui.AlignableContextItem );
