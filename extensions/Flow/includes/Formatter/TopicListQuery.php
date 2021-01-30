<?php

namespace Flow\Formatter;

use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\TopicListEntry;
use Flow\Model\UUID;
use Flow\Repository\TreeRepository;
use Flow\RevisionActionPermissions;
use Flow\WatchedTopicItems;
use User;

class TopicListQuery extends AbstractQuery {

	protected $permissions;
	protected $watchedTopicItems;

	/**
	 * @param ManagerGroup $storage
	 * @param TreeRepository $treeRepository
	 * @param RevisionActionPermissions $permissions
	 * @param WatchedTopicItems $watchedTopicItems
	 */
	public function __construct(
		ManagerGroup $storage,
		TreeRepository $treeRepository,
		RevisionActionPermissions $permissions,
		WatchedTopicItems $watchedTopicItems
	) {
		parent::__construct( $storage, $treeRepository );
		$this->permissions = $permissions;
		$this->watchedTopicItems = $watchedTopicItems;
	}

	/**
	 * @param UUID[]|TopicListEntry[] $topicIdsOrEntries
	 * @return FormatterRow[]
	 * @suppress PhanUndeclaredMethod Types not inferred from instanceof
	 */
	public function getResults( array $topicIdsOrEntries ) {
		$topicIds = $this->getTopicIds( $topicIdsOrEntries );
		$allPostIds = $this->collectPostIds( $topicIds );
		$topicSummary = $this->collectSummary( $topicIds );
		$posts = $this->collectRevisions( $allPostIds );
		$watchStatus = $this->collectWatchStatus( $topicIds );

		$missing = array_diff(
			array_keys( $allPostIds ),
			array_keys( $posts )
		);
		if ( $missing ) {
			$needed = [];
			foreach ( $missing as $alpha ) {
				wfDebugLog( 'Flow', __METHOD__ .
					': Failed to load latest revision for post ID ' . $alpha );

				// convert alpha back into UUID object
				$needed[] = $allPostIds[$alpha];
			}
			$posts += $this->createFakePosts( $needed );
		}

		$this->loadMetadataBatch( $posts );
		$results = [];
		$replies = [];
		foreach ( $posts as $post ) {
			try {
				if ( !$this->permissions->isAllowed( $post, 'view' ) ) {
					continue;
				}
				$row = new TopicRow;
				$this->buildResult( $post, null, $row );
				/** @var PostRevision $revision */
				$revision = $row->revision;
				$replyToId = $revision->getReplyToId();
				$replyToId = $replyToId ? $replyToId->getAlphadecimal() : null;
				$postId = $revision->getPostId()->getAlphadecimal();
				$replies[$replyToId] = $postId;
				if ( $post->isTopicTitle() ) {
					// Attach the summary
					if ( isset( $topicSummary[$postId] ) ) {
						$row->summary = $this->buildResult( $topicSummary[$postId], 'rev_id' );
					}
					// Attach the watch status
					if ( isset( $watchStatus[$postId] ) && $watchStatus[$postId] ) {
						$row->isWatched = true;
					}
				}
				$results[] = $row;
			} catch ( FlowException $e ) {
				\MWExceptionHandler::logException( $e );
			}
		}

		foreach ( $results as $result ) {
			$alpha = $result->revision->getPostId()->getAlphadecimal();
			$result->replies = $replies[$alpha] ?? [];
		}

		return $results;
	}

	/**
	 * @param TopicListEntry[]|UUID[] $topicsIdsOrEntries Topic IDs as UUID entries or
	 *  TopicListEntry objects
	 * @return UUID[]
	 */
	protected function getTopicIds( array $topicsIdsOrEntries ) {
		$topicIds = [];
		foreach ( $topicsIdsOrEntries as $entry ) {
			if ( $entry instanceof UUID ) {
				$topicIds[] = $entry;
			} elseif ( $entry instanceof TopicListEntry ) {
				$topicIds[] = $entry->getId();
			}
		}
		return $topicIds;
	}

	/**
	 * @param UUID[] $topicIds
	 * @return UUID[] Indexed by alphadecimal representation
	 */
	protected function collectPostIds( array $topicIds ) {
		if ( !$topicIds ) {
			return [];
		}
		// Get the full list of postId's necessary
		$nodeList = $this->treeRepository->fetchSubtreeNodeList( $topicIds );

		// Merge all the children from the various posts into one array
		if ( !$nodeList ) {
			// It should have returned at least $topicIds
			wfDebugLog( 'Flow', __METHOD__ .
				': No result received from TreeRepository::fetchSubtreeNodeList' );
			$postIds = $topicIds;
		} elseif ( count( $nodeList ) === 1 ) {
			$postIds = reset( $nodeList );
		} else {
			$postIds = array_merge( ...array_values( $nodeList ) );
		}

		// re-index by alphadecimal id
		return array_combine(
			array_map(
				function ( UUID $x ) {
					return $x->getAlphadecimal();
				},
				$postIds
			),
			$postIds
		);
	}

	/**
	 * @param UUID[] $topicIds
	 * @return array
	 */
	protected function collectWatchStatus( $topicIds ) {
		$ids = [];
		foreach ( $topicIds as $topicId ) {
			$ids[] = $topicId->getAlphadecimal();
		}
		return $this->watchedTopicItems->getWatchStatus( $ids );
	}

	/**
	 * @param UUID[] $topicIds
	 * @return PostSummary[]
	 */
	protected function collectSummary( $topicIds ) {
		if ( !$topicIds ) {
			return [];
		}
		$conds = [];
		foreach ( $topicIds as $topicId ) {
			$conds[] = [ 'rev_type_id' => $topicId ];
		}
		$found = $this->storage->findMulti( 'PostSummary', $conds, [
			'sort' => 'rev_id',
			'order' => 'DESC',
			'limit' => 1,
		] );
		$result = [];
		foreach ( $found as $row ) {
			$summary = reset( $row );
			$result[$summary->getSummaryTargetId()->getAlphadecimal()] = $summary;
		}
		return $result;
	}

	/**
	 * @param UUID[] $postIds
	 * @return PostRevision[] Indexed by alphadecimal post id
	 */
	protected function collectRevisions( array $postIds ) {
		$queries = [];
		foreach ( $postIds as $postId ) {
			$queries[] = [ 'rev_type_id' => $postId ];
		}
		$found = $this->storage->findMulti( 'PostRevision', $queries, [
			'sort' => 'rev_id',
			'order' => 'DESC',
			'limit' => 1,
		] );

		// index results by post id for later filtering
		$result = [];
		foreach ( $found as $row ) {
			$revision = reset( $row );
			$result[$revision->getPostId()->getAlphadecimal()] = $revision;
		}

		return $result;
	}

	/**
	 * Override parent, we only load the most recent version, so just
	 * return self.
	 * @param AbstractRevision $revision
	 * @return AbstractRevision
	 */
	protected function getCurrentRevision( AbstractRevision $revision ) {
		return $revision;
	}

	/**
	 * @param UUID[] $missing
	 * @return PostRevision[]
	 */
	protected function createFakePosts( array $missing ) {
		$parents = $this->treeRepository->fetchParentMap( $missing );
		$posts = [];
		foreach ( $missing as $uuid ) {
			$alpha = $uuid->getAlphadecimal();
			if ( !isset( $parents[$alpha] ) ) {
				wfDebugLog( 'Flow', __METHOD__ . ": Unable not locate parent for postid $alpha" );
				continue;
			}
			$content = wfMessage( 'flow-stub-post-content' )->text();
			$username = wfMessage( 'flow-system-usertext' )->text();
			$user = User::newFromName( $username );

			// create a stub post instead of failing completely
			$post = PostRevision::newFromId( $uuid, $user, $content, 'wikitext' );
			$post->setReplyToId( $parents[$alpha] );
			$posts[$alpha] = $post;
		}

		return $posts;
	}
}
