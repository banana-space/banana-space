( function () {
	'use strict';

	/**
	 * Tool for user mentions
	 *
	 * @class
	 * @extends ve.ui.InspectorTool
	 *
	 * @constructor
	 * @param {OO.ui.ToolGroup} toolGroup
	 * @param {Object} [config] Configuration options
	 */

	mw.flow.ve.ui.MentionInspectorTool = function FlowVeMentionInspectorTool() {
		// Parent constructor
		mw.flow.ve.ui.MentionInspectorTool.super.apply( this, arguments );
	};

	OO.inheritClass( mw.flow.ve.ui.MentionInspectorTool, ve.ui.InspectorTool );

	// Static
	mw.flow.ve.ui.MentionInspectorTool.static.commandName = 'flowMention';
	mw.flow.ve.ui.MentionInspectorTool.static.name = 'flowMention';
	mw.flow.ve.ui.MentionInspectorTool.static.icon = 'userAdd';
	mw.flow.ve.ui.MentionInspectorTool.static.title = OO.ui.deferMsg( 'flow-ve-mention-tool-title' );

	mw.flow.ve.ui.MentionInspectorTool.static.template = mw.flow.ve.ui.MentionInspector.static.template;

	/**
	 * Checks whether the model represents a user mention
	 *
	 * @param {ve.dm.Model} model
	 * @return {boolean}
	 */
	mw.flow.ve.ui.MentionInspectorTool.static.isCompatibleWith = function ( model ) {
		return model instanceof ve.dm.MWTransclusionNode &&
			model.isSingleTemplate( mw.flow.ve.ui.MentionInspectorTool.static.template );
	};

	ve.ui.toolFactory.register( mw.flow.ve.ui.MentionInspectorTool );
}() );
