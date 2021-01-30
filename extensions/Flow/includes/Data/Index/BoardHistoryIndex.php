<?php

namespace Flow\Data\Index;

use Flow\Data\FlowObjectCache;
use Flow\Data\ObjectManager;
use Flow\Data\ObjectMapper;
use Flow\Data\Storage\BoardHistoryStorage;
use Flow\Exception\DataModelException;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\TopicListEntry;
use Flow\Model\UUID;
use Flow\Model\Workflow;

/**
 * Keeps a list of revision ids relevant to the board history bucketed
 * by the owning TopicList id (board workflow).
 *
 * Can be used with Header, PostRevision and PostSummary ObjectMapper's
 */
abstract class BoardHistoryIndex extends TopKIndex {
	/**
	 * @var ObjectManager Manager for the TopicListEntry model
	 */
	protected $om;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		FlowObjectCache $cache,
		BoardHistoryStorage $storage,
		ObjectMapper $mapper,
		$prefix,
		array $indexed,
		array $options,
		ObjectManager $om
	) {
		if ( $indexed !== [ 'topic_list_id' ] ) {
			throw new DataModelException(
				__CLASS__ . ' is hardcoded to only index topic_list_id: ' . print_r( $indexed, true ),
				'process-data'
			);
		}
		parent::__construct( $cache, $storage, $mapper, $prefix, $indexed, $options );
		$this->om = $om;
	}

	public function findMulti( array $queries, array $options = [] ) {
		if ( count( $queries ) > 1 ) {
			// why?
			throw new DataModelException( __METHOD__ . ' expects only one value in $queries', 'process-data' );
		}
		return parent::findMulti( $queries, $options );
	}

	/**
	 * @param array $queries
	 * @return array
	 */
	public function backingStoreFindMulti( array $queries ) {
		return $this->storage->findMulti(
			$queries,
			$this->queryOptions()
		) ?: [];
	}

	/**
	 * @param PostSummary|PostRevision $object
	 * @param string[] $row
	 */
	public function cachePurge( $object, array $row ) {
		$row['topic_list_id'] = $this->findTopicListId( $object, $row, [] );
		parent::cachePurge( $object, $row );
	}

	/**
	 * @param PostSummary|PostRevision $object
	 * @param string[] $new
	 * @param array $metadata
	 */
	public function onAfterInsert( $object, array $new, array $metadata ) {
		$new['topic_list_id'] = $this->findTopicListId( $object, $new, $metadata );
		parent::onAfterInsert( $object, $new, $metadata );
	}

	/**
	 * @param PostSummary|PostRevision $object
	 * @param string[] $old
	 * @param string[] $new
	 * @param array $metadata
	 */
	public function onAfterUpdate( $object, array $old, array $new, array $metadata ) {
		$new['topic_list_id'] = $old['topic_list_id'] = $this->findTopicListId( $object, $new, $metadata );
		parent::onAfterUpdate( $object, $old, $new, $metadata );
	}

	/**
	 * @param PostSummary|PostRevision $object
	 * @param string[] $old
	 * @param array $metadata
	 */
	public function onAfterRemove( $object, array $old, array $metadata ) {
		$old['topic_list_id'] = $this->findTopicListId( $object, $old, $metadata );
		parent::onAfterRemove( $object, $old, $metadata );
	}

	/**
	 * Find a topic ID given an AbstractRevision
	 *
	 * @param AbstractRevision $object
	 * @return UUID Topic ID
	 */
	abstract protected function findTopicId( AbstractRevision $object );

	/**
	 * Find a topic list ID related to an AbstractRevision
	 *
	 * @param AbstractRevision $object
	 * @param string[] $row
	 * @param array $metadata
	 * @return string Alphadecimal uid of the related board
	 * @throws DataModelException When the related id cannot be located
	 */
	protected function findTopicListId( AbstractRevision $object, array $row, array $metadata ) {
		if ( isset( $metadata['workflow'] ) && $metadata['workflow'] instanceof Workflow ) {
			$topicId = $metadata['workflow']->getId();
		} else {
			$topicId = $this->findTopicId( $object );
		}

		$found = $this->om->find( [ 'topic_id' => $topicId ] );
		if ( !$found ) {
			throw new DataModelException(
				"No topic list contains topic " . $topicId->getAlphadecimal() .
				", called for revision " . $object->getRevisionId()->getAlphadecimal()
			);
		}

		/** @var TopicListEntry $topicListEntry */
		$topicListEntry = reset( $found );
		return $topicListEntry->getListId()->getAlphadecimal();
	}
}
