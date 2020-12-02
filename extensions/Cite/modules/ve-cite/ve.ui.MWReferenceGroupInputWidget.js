/*!
 * VisualEditor UserInterface MWReferenceGroupInput class.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Creates an ve.ui.MWReferenceGroupInput object.
 *
 * @class
 * @extends OO.ui.ComboBoxInputWidget
 *
 * @constructor
 * @param {Object} [config] Configuration options
 * @cfg {string} emptyGroupName Label of the placeholder item
 */
ve.ui.MWReferenceGroupInputWidget = function VeUiMWReferenceGroupInputWidget( config ) {
	config = config || {};

	this.emptyGroupName = config.emptyGroupName;

	// Parent constructor
	ve.ui.MWReferenceGroupInputWidget.super.call( this, ve.extendObject( { placeholder: config.emptyGroupName }, config ) );

	this.$element.addClass( 've-ui-mwReferenceGroupInputWidget' );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWReferenceGroupInputWidget, OO.ui.ComboBoxInputWidget );

/* Methods */

/**
 * Populate the reference group menu
 *
 * @param {ve.dm.InternalList} internalList Internal list with which to populate the menu
 */
ve.ui.MWReferenceGroupInputWidget.prototype.populateMenu = function ( internalList ) {
	var placeholderGroupItem = new OO.ui.MenuOptionWidget( {
		data: '',
		label: this.emptyGroupName,
		flags: 'emptyGroupPlaceholder'
	} );
	this.menu.clearItems();
	this.menu.addItems( [ placeholderGroupItem ].concat(
		Object.keys( internalList.getNodeGroups() ).map(
			function ( groupInternalName ) {
				var groupName;
				if ( groupInternalName.indexOf( 'mwReference/' ) === 0 ) {
					groupName = groupInternalName.slice( 'mwReference/'.length );
					if ( groupName ) {
						return new OO.ui.MenuOptionWidget( { data: groupName, label: groupName } );
					}
				}
				return null;
			}
		).filter( function ( item ) {
			return item;
		} )
	), 0 );
	this.menu.toggle( false );
};
