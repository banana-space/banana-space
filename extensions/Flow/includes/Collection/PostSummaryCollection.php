<?php

namespace Flow\Collection;

use Flow\Container;
use Flow\Model\UUID;

class PostSummaryCollection extends LocalCacheAbstractCollection {
	/**
	 * @var UUID
	 */
	protected $rootId;

	public static function getRevisionClass() {
		return \Flow\Model\PostSummary::class;
	}

	public function getWorkflowId() {
		// the root post (topic title) has the same id as the workflow
		if ( !$this->rootId ) {
			/** @var \Flow\Repository\TreeRepository $treeRepo */
			$treeRepo = Container::get( 'repository.tree' );
			$this->rootId = $treeRepo->findRoot( $this->getId() );
		}

		return $this->rootId;
	}

	public function getBoardWorkflowId() {
		return $this->getPost()->getBoardWorkflowId();
	}

	/**
	 * Get the post collection for this summary
	 * @return PostCollection
	 */
	public function getPost() {
		return PostCollection::newFromId( $this->uuid );
	}
}
