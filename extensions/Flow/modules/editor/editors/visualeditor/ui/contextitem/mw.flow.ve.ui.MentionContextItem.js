( function () {
	'use strict';

	/**
	 * Context item for user mentions
	 *
	 * @class
	 * @extends ve.ui.ContextItem
	 *
	 * @param {ve.ui.Context} context Context item is in
	 * @param {ve.dm.Model} model Model item is related to
	 * @param {Object} config Configuration options
	 */
	mw.flow.ve.ui.MentionContextItem = function FlowVeMentionContextItem() {
		// Parent constructor
		mw.flow.ve.ui.MentionContextItem.super.apply( this, arguments );

		this.$element.addClass( 'flow-ve-ui-mentionContextItem' );
	};

	OO.inheritClass( mw.flow.ve.ui.MentionContextItem, ve.ui.MWTransclusionContextItem );

	// Static
	mw.flow.ve.ui.MentionContextItem.static.name = 'flowMention';

	mw.flow.ve.ui.MentionContextItem.static.icon = 'userAdd';

	mw.flow.ve.ui.MentionContextItem.static.label = OO.ui.deferMsg( 'flow-ve-mention-context-item-label' );

	mw.flow.ve.ui.MentionContextItem.static.commandName = 'flowMention';

	// Make sure the inspector uses an arrow, rather than trying to fit in the template.
	// Wouldn't fit anyway, though, most likely.

	mw.flow.ve.ui.MentionContextItem.static.embeddable = false;
	/**
	 * @static
	 * @localdoc Sharing implementation with mw.flow.ve.ui.MentionInspectorTool
	 */
	mw.flow.ve.ui.MentionContextItem.static.isCompatibleWith =
		mw.flow.ve.ui.MentionInspectorTool.static.isCompatibleWith;

	// Instance Methods

	/**
	 * Returns a short description emphasizing the relevant data (currently just the user name)
	 *
	 * @return {string} User name
	 */
	mw.flow.ve.ui.MentionContextItem.prototype.getDescription = function () {
		var key = mw.flow.ve.ui.MentionInspector.static.templateParameterKey;

		// Is there a more intuitive way to do this?
		return this.model.element.attributes.mw.parts[ 0 ].template.params[ key ].wt;
	};

	ve.ui.contextItemFactory.register( mw.flow.ve.ui.MentionContextItem );
}() );
