/*!
 * VisualEditor Standalone Initialization Target class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * Initialization Standalone target.
 *
 * A platform must be constructed first. See ve.init.sa.Platform for an example.
 *
 *     @example
 *     ve.init.platform.initialize().done( function () {
 *         var target = new ve.init.sa.DesktopTarget();
 *         target.addSurface(
 *             ve.dm.converter.getModelFromDom(
 *                 ve.createDocumentFromHtml( '<p>Hello, World!</p>' )
 *             )
 *         );
 *         $( document.body ).append( target.$element );
 *     } );
 *
 * @abstract
 * @class
 * @extends ve.init.Target
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {Object} [toolbarConfig] Configuration options for the toolbar
 */
ve.init.sa.Target = function VeInitSaTarget( config ) {
	config = config || {};
	config.toolbarConfig = ve.extendObject( { shadow: true, actions: true, floatable: true }, config.toolbarConfig );

	// Parent constructor
	ve.init.sa.Target.super.call( this, config );

	this.$element
		.addClass( 've-init-sa-target' )
		.attr( 'lang', ve.init.platform.getUserLanguages()[ 0 ] );
};

/* Inheritance */

OO.inheritClass( ve.init.sa.Target, ve.init.Target );

/* Static properties */

ve.init.sa.Target.static.actionGroups = [
	{
		name: 'pageMenu',
		type: 'list',
		icon: 'menu',
		indicator: null,
		title: OO.ui.deferMsg( 'visualeditor-pagemenu-tooltip' ),
		label: OO.ui.deferMsg( 'visualeditor-pagemenu-tooltip' ),
		invisibleLabel: true,
		include: [ 'findAndReplace', 'changeDirectionality', 'commandHelp' ]
	}
];

/* Methods */

/**
 * @inheritdoc
 * @fires surfaceReady
 */
ve.init.sa.Target.prototype.addSurface = function () {
	// Parent method
	var surface = ve.init.sa.Target.super.prototype.addSurface.apply( this, arguments );

	this.$element.append( $( '<div>' ).addClass( 've-init-sa-target-surfaceWrapper' ).append( surface.$element ) );
	if ( !this.getSurface() ) {
		this.setSurface( surface );
	}
	surface.initialize();
	this.emit( 'surfaceReady' );
	return surface;
};

/**
 * @inheritdoc
 */
ve.init.sa.Target.prototype.setupToolbar = function ( surface ) {
	// Parent method
	ve.init.sa.Target.super.prototype.setupToolbar.call( this, surface );

	this.getToolbar().$element.addClass( 've-init-sa-target-toolbar' );
};
