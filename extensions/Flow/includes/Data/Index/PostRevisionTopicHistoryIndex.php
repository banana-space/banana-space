<?php

namespace Flow\Data\Index;

use Flow\Collection\PostCollection;
use Flow\Data\FlowObjectCache;
use Flow\Data\ObjectMapper;
use Flow\Data\Storage\PostRevisionTopicHistoryStorage;
use Flow\Exception\DataModelException;
use Flow\Model\PostRevision;
use Flow\Model\UUID;

/**
 * TopKIndex that calculates the topic_root_id
 */
class PostRevisionTopicHistoryIndex extends TopKIndex {
	public function __construct(
		FlowObjectCache $cache,
		PostRevisionTopicHistoryStorage $storage,
		ObjectMapper $mapper,
		$prefix,
		array $indexed,
		array $options = []
	) {
		if ( $indexed !== [ 'topic_root_id' ] ) {
			throw new \MWException( __CLASS__ . ' is hardcoded to only index topic_root_id: ' .
				print_r( $indexed, true ) );
		}
		parent::__construct( $cache, $storage, $mapper, $prefix, $indexed, $options );
	}

	/**
	 * @param PostRevision $object
	 * @param array $row
	 */
	public function cachePurge( $object, array $row ) {
		$row['topic_root_id'] = $this->findTopicId( $object );
		parent::cachePurge( $object, $row );
	}

	/**
	 * @param PostRevision $object
	 * @param string[] $new
	 * @param array $metadata
	 */
	public function onAfterInsert( $object, array $new, array $metadata ) {
		$new['topic_root_id'] = $this->findTopicId( $object );
		parent::onAfterInsert( $object, $new, $metadata );
	}

	/**
	 * @param PostRevision $object
	 * @param string[] $old
	 * @param string[] $new
	 * @param array $metadata
	 */
	public function onAfterUpdate( $object, array $old, array $new, array $metadata ) {
		$old['topic_root_id'] = $new['topic_root_id'] = $this->findTopicId( $object );
		parent::onAfterUpdate( $object, $old, $new, $metadata );
	}

	/**
	 * @param PostRevision $object
	 * @param string[] $old
	 * @param array $metadata
	 */
	public function onAfterRemove( $object, array $old, array $metadata ) {
		$old['topic_root_id'] = $this->findTopicId( $object );
		parent::onAfterRemove( $object, $old, $metadata );
	}

	/**
	 * Finds topic ID for given Post
	 *
	 * @param PostRevision $post
	 * @return UUID Topic ID
	 * @throws DataModelException
	 */
	protected function findTopicId( PostRevision $post ) {
		try {
			$root = $post->getCollection()->getRoot();
		} catch ( DataModelException $e ) {
			// in some cases, we may fail to find root post from the current
			// object (e.g. data has already been removed)
			// try to find if via parent, in that case
			$parentId = $post->getReplyToId();
			if ( $parentId === null ) {
				throw new DataModelException( 'Unable to locate root for post ' .
					$post->getCollectionId() );
			}

			$parent = PostCollection::newFromId( $parentId );
			$root = $parent->getRoot();
		}

		return $root->getId();
	}

	protected function backingStoreFindMulti( array $queries ) {
		return $this->storage->findMulti( $queries, $this->queryOptions() );
	}
}
