<?php

namespace Flow\Model;

use Flow\Exception\DataModelException;

// TODO: We shouldn't need this class
class TopicListEntry {

	/**
	 * @var UUID
	 */
	protected $topicListId;

	/**
	 * @var UUID
	 */
	protected $topicId;

	/**
	 * @var string|null
	 */
	protected $topicWorkflowLastUpdated;

	/**
	 * @param Workflow $topicList
	 * @param Workflow $topic
	 * @return TopicListEntry
	 */
	public static function create( Workflow $topicList, Workflow $topic ) {
		$obj = new self;
		$obj->topicListId = $topicList->getId();
		$obj->topicId = $topic->getId();
		$obj->topicWorkflowLastUpdated = $topic->getLastUpdated();
		return $obj;
	}

	/**
	 * @param array $row
	 * @param TopicListEntry|null $obj
	 * @return TopicListEntry
	 * @throws DataModelException
	 */
	public static function fromStorageRow( array $row, $obj = null ) {
		if ( $obj === null ) {
			$obj = new self;
		} elseif ( !$obj instanceof self ) {
			throw new DataModelException( 'Wrong obj type: ' . get_class( $obj ), 'process-data' );
		}
		$obj->topicListId = UUID::create( $row['topic_list_id'] );
		$obj->topicId = UUID::create( $row['topic_id'] );
		if ( isset( $row['workflow_last_update_timestamp'] ) ) {
			$obj->topicWorkflowLastUpdated = $row['workflow_last_update_timestamp'];
		}
		return $obj;
	}

	/**
	 * @param TopicListEntry $obj
	 * @return array
	 */
	public static function toStorageRow( TopicListEntry $obj ) {
		$row = [
			'topic_list_id' => $obj->topicListId->getAlphadecimal(),
			'topic_id' => $obj->topicId->getAlphadecimal(),
		];
		if ( $obj->topicWorkflowLastUpdated ) {
			$row['workflow_last_update_timestamp'] = $obj->topicWorkflowLastUpdated;
		}
		return $row;
	}

	/**
	 * @return UUID
	 */
	public function getId() {
		return $this->topicId;
	}

	/**
	 * @return UUID
	 */
	public function getListId() {
		return $this->topicListId;
	}

	/**
	 * @return string|null
	 */
	public function getTopicWorkflowLastUpdated() {
		return $this->topicWorkflowLastUpdated;
	}
}
