<?php

namespace Flow\Collection;

use Flow\Container;
use Flow\Exception\InvalidDataException;
use Flow\Model\TopicListEntry;
use Flow\Model\UUID;

class PostCollection extends LocalCacheAbstractCollection {
	/**
	 * @var UUID
	 */
	protected $rootId;

	public static function getRevisionClass() {
		return \Flow\Model\PostRevision::class;
	}

	/**
	 * @return UUID
	 * @throws \Flow\Exception\DataModelException
	 */
	public function getWorkflowId() {
		// the root post (topic title) has the same id as the workflow
		if ( !$this->rootId ) {
			/** @var \Flow\Repository\TreeRepository $treeRepo */
			$treeRepo = Container::get( 'repository.tree' );
			$this->rootId = $treeRepo->findRoot( $this->getId() );
		}

		return $this->rootId;
	}

	/**
	 * @return UUID
	 * @throws InvalidDataException
	 */
	public function getBoardWorkflowId() {
		$found = self::getStorage( TopicListEntry::class )->find(
			// uses flow_topic_list:topic index, for topic->board lookups
			[ 'topic_id' => $this->getWorkflowId() ]
		);
		if ( !$found ) {
			throw new InvalidDataException( 'No TopicListEntry founds for topic id ' .
				$this->getWorkflowId()->getAlphadecimal(), 'invalid-workflow' );
		}

		/** @var TopicListEntry $topicListEntry */
		$topicListEntry = $found[0];
		return $topicListEntry->getListId();
	}

	/**
	 * Returns the topic title collection this post is associated with.
	 *
	 * @return PostCollection
	 */
	public function getRoot() {
		return static::newFromId( $this->getWorkflowId() );
	}
}
