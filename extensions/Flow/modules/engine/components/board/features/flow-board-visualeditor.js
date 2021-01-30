/*!
 * Expose some functionality on the board object that is needed for VisualEditor.
 */

( function () {
	/**
	 * FlowBoardComponentVisualEditorFeatureMixin
	 *
	 * @this FlowBoardComponent
	 * @constructor
	 */
	function FlowBoardComponentVisualEditorFeatureMixin() {
	}

	// This is not really VE-specific, but I'm not sure where best to put it.
	// Also, should we pre-compute this in a loadHandler?
	/**
	 * Finds topic authors for the given node
	 *
	 * @param {jQuery} $node
	 * @return {string[]} List of usernames
	 */
	function flowVisualEditorGetTopicPosters( $node ) {
		var $topic = $node.closest( '.flow-topic' ),
			duplicatedArray;

		// Could use a data attribute to avoid trim.
		duplicatedArray = $topic.find( '.flow-author .mw-userlink' ).get().map( function ( el ) {
			return $( el ).text().trim();
		} );
		return OO.unique( duplicatedArray );
	}

	FlowBoardComponentVisualEditorFeatureMixin.prototype.getTopicPosters = flowVisualEditorGetTopicPosters;

	mw.flow.mixinComponent( 'board', FlowBoardComponentVisualEditorFeatureMixin );
}() );
