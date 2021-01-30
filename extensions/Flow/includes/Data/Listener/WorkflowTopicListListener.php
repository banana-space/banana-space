<?php

namespace Flow\Data\Listener;

use Flow\Data\Index\TopKIndex;
use Flow\Data\ObjectManager;
use Flow\Model\TopicListEntry;

/**
 * Every time an action is performed against something within a topic workflow
 * the workflow's last_update_timestamp is updated as well.  This listener
 * passes that updated timestamp along to the topic list last updated index
 * so that it can reorder any lists this workflow is in.
 */
class WorkflowTopicListListener extends AbstractListener {

	/**
	 * @var ObjectManager
	 */
	protected $topicListStorage;

	/**
	 * @var TopKIndex
	 */
	protected $topicListLastUpdatedIndex;

	/**
	 * @param ObjectManager $topicListStorage
	 * @param TopKIndex $topicListLastUpdatedIndex
	 */
	public function __construct( ObjectManager $topicListStorage, TopKIndex $topicListLastUpdatedIndex ) {
		$this->topicListStorage = $topicListStorage;
		$this->topicListLastUpdatedIndex = $topicListLastUpdatedIndex;
	}

	/**
	 * @param string $workflowId
	 * @return TopicListEntry|false
	 */
	protected function getTopicListEntry( $workflowId ) {
		$list = $this->topicListStorage->find( [ 'topic_id' => $workflowId ] );

		// One topic maps to only one topic list now
		if ( $list ) {
			return reset( $list );
		} else {
			return false;
		}
	}

	// Is this necessary?  It seems it doesn't find anything since the topic workflow is
	// inserted before TopicListEntry (TLE), but then there is a direct listener on the
	// TLE insertion so it shouldn't be needed.
	public function onAfterInsert( $object, array $new, array $metadata ) {
		$entry = $this->getTopicListEntry( $new['workflow_id'] );
		if ( $entry ) {
			$row = [
					'workflow_last_update_timestamp' => $new['workflow_last_update_timestamp']
				] + TopicListEntry::toStorageRow( $entry );
			$this->topicListLastUpdatedIndex->onAfterInsert( $entry, $row, $metadata );
		}
	}

	public function onAfterUpdate( $object, array $old, array $new, array $metadata ) {
		$entry = $this->getTopicListEntry( $new['workflow_id'] );
		if ( $entry ) {
			$row = TopicListEntry::toStorageRow( $entry );
			$this->topicListLastUpdatedIndex->onAfterUpdate(
				$entry,
				[
					'workflow_last_update_timestamp' => $old['workflow_last_update_timestamp']
				] + $row,
				[
					'workflow_last_update_timestamp' => $new['workflow_last_update_timestamp']
				] + $row,
				$metadata
			);
		}
	}
}
