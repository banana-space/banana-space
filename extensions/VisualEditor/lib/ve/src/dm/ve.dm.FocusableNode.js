/*!
 * VisualEditor DataModel Focusable node.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * A mixin class for focusable nodes.
 *
 * @class
 * @abstract
 * @constructor
 */
ve.dm.FocusableNode = function VeDmFocusableNode() {};

/* Inheritance */

OO.initClass( ve.dm.FocusableNode );

/* Static Properties */

ve.dm.FocusableNode.static.isFocusable = true;
