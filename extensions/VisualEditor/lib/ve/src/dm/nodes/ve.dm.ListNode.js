/*!
 * VisualEditor DataModel ListNode class.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel list node.
 *
 * @class
 * @extends ve.dm.BranchNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.ListNode = function VeDmListNode() {
	// Parent constructor
	ve.dm.ListNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.dm.ListNode, ve.dm.BranchNode );

/* Static Properties */

ve.dm.ListNode.static.name = 'list';

ve.dm.ListNode.static.childNodeTypes = [ 'listItem' ];

ve.dm.ListNode.static.defaultAttributes = {
	style: 'bullet'
};

ve.dm.ListNode.static.matchTagNames = [ 'ul', 'ol' ];

ve.dm.ListNode.static.isDiffedAsList = true;

/**
 * Creates a list item element
 *
 * @return {Object} Element data
 */
ve.dm.ListNode.static.createItem = function () {
	return { type: 'listItem' };
};

ve.dm.ListNode.static.toDataElement = function ( domElements ) {
	var style = domElements[ 0 ].nodeName.toLowerCase() === 'ol' ? 'number' : 'bullet';
	return { type: this.name, attributes: { style: style } };
};

ve.dm.ListNode.static.toDomElements = function ( dataElement, doc ) {
	var tag = dataElement.attributes && dataElement.attributes.style === 'number' ? 'ol' : 'ul';
	return [ doc.createElement( tag ) ];
};

ve.dm.ListNode.static.describeChange = function ( key, change ) {
	if ( key === 'style' ) {
		return ve.htmlMsg( 'visualeditor-changedesc-no-key',
			// The following messages are used here:
			// * visualeditor-listbutton-bullet-tooltip
			// * visualeditor-listbutton-number-tooltip
			this.wrapText( 'del', ve.msg( 'visualeditor-listbutton-' + change.from + '-tooltip' ) ),
			this.wrapText( 'ins', ve.msg( 'visualeditor-listbutton-' + change.to + '-tooltip' ) )
		);
	}
	// Parent method
	return ve.dm.ListNode.parent.static.describeChange.apply( this, arguments );
};

/* Methods */

ve.dm.ListNode.prototype.canHaveSlugAfter = function () {
	// A paragraph can be added after a list by pressing enter in an empty list item
	return false;
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.ListNode );
