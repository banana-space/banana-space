/*!
 * VisualEditor MediaWiki UserInterface edit mode tool classes.
 *
 * @copyright 2011-2017 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface edit mode tool.
 *
 * @class
 * @abstract
 */
mw.flow.ve.ui.MWEditModeTool = function MwFlowUiMWEditModeTool() {
};

/* Inheritance */

OO.initClass( mw.flow.ve.ui.MWEditModeTool );

/* Methods */

mw.flow.ve.ui.MWEditModeTool.prototype.getMode = function () {
	return this.toolbar.getSurface().getMode();
};

mw.flow.ve.ui.MWEditModeTool.prototype.isModeAvailable = function () {
	// If we're showing the switcher, then both modes are available
	return true;
};

/**
 * MediaWiki UserInterface edit mode source tool.
 *
 * @class
 * @extends mw.libs.ve.MWEditModeSourceTool
 * @mixins mw.flow.ve.ui.MWEditModeTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Config options
 */
mw.flow.ve.ui.MWEditModeSourceTool = function MwFlowUiMWEditModeSourceTool() {
	// Parent constructor
	mw.flow.ve.ui.MWEditModeSourceTool.super.apply( this, arguments );
};

OO.inheritClass( mw.flow.ve.ui.MWEditModeSourceTool, mw.libs.ve.MWEditModeSourceTool );
OO.mixinClass( mw.flow.ve.ui.MWEditModeSourceTool, mw.flow.ve.ui.MWEditModeTool );

mw.flow.ve.ui.MWEditModeSourceTool.prototype.switch = function () {
	this.toolbar.getTarget().switchMode();
};

ve.ui.toolFactory.register( mw.flow.ve.ui.MWEditModeSourceTool );

/**
 * MediaWiki UserInterface edit mode visual tool.
 *
 * @class
 * @extends mw.libs.ve.MWEditModeVisualTool
 * @mixins mw.flow.ve.ui.MWEditModeTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Config options
 */
mw.flow.ve.ui.MWEditModeVisualTool = function MwFlowUiMWEditModeVisualTool() {
	// Parent constructor
	mw.flow.ve.ui.MWEditModeVisualTool.super.apply( this, arguments );
};

OO.inheritClass( mw.flow.ve.ui.MWEditModeVisualTool, mw.libs.ve.MWEditModeVisualTool );
OO.mixinClass( mw.flow.ve.ui.MWEditModeVisualTool, mw.flow.ve.ui.MWEditModeTool );

mw.flow.ve.ui.MWEditModeVisualTool.prototype.switch = function () {
	this.toolbar.getTarget().switchMode();
};

ve.ui.toolFactory.register( mw.flow.ve.ui.MWEditModeVisualTool );
