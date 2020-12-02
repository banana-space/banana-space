/*!
 * VisualEditor DataModel CheckListItemNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel list item node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.CheckListItemNode = function VeDmCheckListItemNode() {
	// Parent constructor
	ve.dm.CheckListItemNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.CheckListItemNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.CheckListItemNode.static.name = 'checkListItem';

ve.dm.CheckListItemNode.static.parentNodeTypes = [ 'checkList' ];

ve.dm.CheckListItemNode.static.matchTagNames = [ 'li' ];

ve.dm.CheckListItemNode.static.matchRdfaTypes = [ 've:checkList' ];

ve.dm.CheckListItemNode.static.toDataElement = function ( domElements ) {
	var checked = domElements[ 0 ].hasAttribute( 'checked' );
	return { type: this.name, attributes: { checked: checked } };
};

ve.dm.CheckListItemNode.static.toDomElements = function ( dataElement, doc ) {
	var listItem = doc.createElement( 'li' );
	listItem.setAttribute( 'rel', 've:checkList' );
	if ( dataElement.attributes.checked ) {
		listItem.setAttribute( 'checked', 'checked' );
	}
	return [ listItem ];
};

ve.dm.CheckListItemNode.static.cloneElement = function () {
	var clone = ve.dm.CheckListItemNode.super.static.cloneElement.apply( this, arguments );
	clone.attributes.checked = false;
	return clone;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.CheckListItemNode );
