<?php

namespace Flow\Formatter;

use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Repository\TreeRepository;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Base class that collects the data necessary to utilize AbstractFormatter
 * based on a list of revisions. In some cases formatters will not utilize
 * this query and will instead receive data from a table such as the core
 * recentchanges.
 */
abstract class AbstractQuery {
	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var TreeRepository
	 */
	protected $treeRepository;

	// Consider converting these in-process caches to MapCacheLRU to avoid
	// memory leaks.  Should only be an issue if a batch is repeatedly doing queries.
	/**
	 * @var UUID[] Associative array of post ID to root post's UUID object.
	 */
	protected $rootPostIdCache = [];

	/**
	 * @var PostRevision[] Associative array of post ID to PostRevision object.
	 */
	protected $postCache = [];

	/**
	 * @var AbstractRevision[] Associative array of revision ID to AbstractRevision object
	 */
	protected $revisionCache = [];

	/**
	 * @var Workflow[] Associative array of workflow ID to Workflow object.
	 */
	protected $workflowCache = [];

	/**
	 * Array of collection ids mapping to their most recent revision ids.
	 *
	 * @var UUID[]
	 */
	protected $currentRevisionsCache = [];

	protected $identityMap = [];

	/**
	 * @param ManagerGroup $storage
	 * @param TreeRepository $treeRepository
	 */
	public function __construct( ManagerGroup $storage, TreeRepository $treeRepository ) {
		$this->storage = $storage;
		$this->treeRepository = $treeRepository;
	}

	/**
	 * Entry point for batch loading metadata for a variety of revisions
	 * into the internal cache.
	 *
	 * @param AbstractRevision[]|IResultWrapper $results
	 */
	protected function loadMetadataBatch( $results ) {
		// Batch load data related to a list of revisions
		$postIds = [];
		$workflowIds = [];
		$revisions = [];
		$previousRevisionIds = [];
		$collectionIds = [];
		foreach ( $results as $result ) {
			if ( $result instanceof PostRevision ) {
				// If top-level, then just get the workflow.
				// Otherwise we need to find the root post.
				$id = $result->getPostId();
				$alpha = $id->getAlphadecimal();
				if ( $result->isTopicTitle() ) {
					$workflowIds[] = $id;
				} else {
					$postIds[$alpha] = $id;
				}
				$this->postCache[$alpha] = $result;
			} elseif ( $result instanceof Header ) {
				$workflowIds[] = $result->getWorkflowId();
			} elseif ( $result instanceof PostSummary ) {
				// This would be the post id for the summary
				$id = $result->getSummaryTargetId();
				$postIds[$id->getAlphadecimal()] = $id;
			}

			$revisions[$result->getRevisionId()->getAlphadecimal()] = $result;
			if ( $this->needsPreviousRevision( $result ) ) {
				$previousRevisionIds[get_class( $result )][] = $result->getPrevRevisionId();
			}

			$collection = $result->getCollection();
			$collectionIds[get_class( $result )][] = $collection->getId();
		}

		// map from post Id to the related root post id
		$rootPostIds = array_filter( $this->treeRepository->findRoots( $postIds ) );
		$rootPostRequests = [];
		foreach ( $rootPostIds as $postId ) {
			$rootPostRequests[] = [ 'rev_type_id' => $postId ];
		}

		// these tree identity maps are required for determining where a reply goes when
		// replying to a specific post.
		$identityMap = $this->treeRepository->fetchSubtreeIdentityMap(
			array_unique( $rootPostIds, SORT_REGULAR )
		);

		$rootPostResult = $this->storage->findMulti(
			'PostRevision',
			$rootPostRequests,
			[
				'SORT' => 'rev_id',
				'ORDER' => 'DESC',
				'LIMIT' => 1,
			]
		);

		$rootPosts = [];
		if ( count( $rootPostResult ) > 0 ) {
			foreach ( $rootPostResult as $found ) {
				$root = reset( $found );
				$rootPosts[$root->getPostId()->getAlphadecimal()] = $root;
				$revisions[$root->getRevisionId()->getAlphadecimal()] = $root;
			}
		}

		// Workflow IDs are the same as root post IDs
		// So any post IDs that *are* root posts + found root post IDs + header workflow IDs
		// should cover the lot.
		$workflows = $this->storage->getMulti( 'Workflow', array_merge( $rootPostIds, $workflowIds ) );
		$workflows = $workflows ?: [];

		// preload all requested previous revisions
		foreach ( $previousRevisionIds as $revisionType => $ids ) {
			// get rid of null-values (for original revisions, without previous revision)
			$ids = array_filter( $ids );
			/** @var AbstractRevision[] $found */
			$found = $this->storage->getMulti( $revisionType, $ids );
			foreach ( $found as $rev ) {
				$revisions[$rev->getRevisionId()->getAlphadecimal()] = $rev;
			}
		}

		// preload all current versions
		foreach ( $collectionIds as $revisionType => $ids ) {
			$queries = [];
			foreach ( $ids as $uuid ) {
				$queries[] = [ 'rev_type_id' => $uuid ];
			}

			$found = $this->storage->findMulti( $revisionType,
				$queries,
				[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
			);

			/** @var AbstractRevision[] $result */
			foreach ( $found as $result ) {
				$rev = reset( $result );
				$cacheKey = $this->getCurrentRevisionCacheKey( $rev );
				$this->currentRevisionsCache[$cacheKey] = $rev->getRevisionId();
				$revisions[$rev->getRevisionId()->getAlphadecimal()] = $rev;
			}
		}

		$this->revisionCache = array_merge( $this->revisionCache, $revisions );
		$this->postCache = array_merge( $this->postCache, $rootPosts );
		$this->rootPostIdCache = array_merge( $this->rootPostIdCache, $rootPostIds );
		$this->workflowCache = array_merge( $this->workflowCache, $workflows );
		$this->identityMap = array_merge( $this->identityMap, $identityMap );
	}

	/**
	 * Build a stdClass object that contains all related data models necessary
	 * for rendering a revision.
	 *
	 * @param AbstractRevision $revision
	 * @param string|null $indexField The field used for pagination
	 * @param FormatterRow|null $row Row to populate
	 * @return FormatterRow
	 * @throws FlowException
	 */
	protected function buildResult( AbstractRevision $revision, $indexField, FormatterRow $row = null ) {
		$uuid = $revision->getRevisionId();
		$timestamp = $uuid->getTimestamp();

		$workflow = $this->getWorkflow( $revision );
		if ( !$workflow ) {
			throw new FlowException( "could not locate workflow for revision " .
				$revision->getRevisionId()->getAlphadecimal() );
		}

		$row = $row ?: new FormatterRow;
		$row->revision = $revision;
		if ( $this->needsPreviousRevision( $revision ) ) {
			$row->previousRevision = $this->getPreviousRevision( $revision );
		}
		$row->currentRevision = $this->getCurrentRevision( $revision );
		$row->workflow = $workflow;

		// some core classes that process this row before our formatter
		// require a specific field to handle pagination
		if ( property_exists( $row, $indexField ) ) {
			$row->$indexField = $timestamp;
		}

		if ( $revision instanceof PostRevision ) {
			$row->rootPost = $this->getRootPost( $revision );
			$revision->setRootPost( $row->rootPost );
			$row->isFirstReply = $this->isFirstReply( $revision, $row->rootPost );
			$row->isLastReply = $this->isLastReply( $revision );
		}

		return $row;
	}

	/**
	 * @param PostRevision $revision
	 * @param PostRevision $root
	 * @return bool
	 */
	protected function isFirstReply( PostRevision $revision, PostRevision $root ) {
		// check if it's a first-level reply (not topic title, but the level just below that)
		if ( !$root->getPostId()->equals( $revision->getReplyToId() ) ) {
			return false;
		}

		// we can use the timestamps to check if the reply was created at roughly the same time the
		// topic was created if they're 0 or 1 seconds apart, they must have been created in the
		// same request unless our servers are extremely slow and can't create topic + first reply
		// in < 1 seconds, this should be a pretty safe method to detect first reply
		if ( (int)$revision->getPostId()->getTimestamp( TS_UNIX ) -
			(int)$root->getPostId()->getTimestamp( TS_UNIX ) >= 2
		) {
			return false;
		}

		return true;
	}

	/**
	 * @param PostRevision $revision
	 * @return bool
	 */
	protected function isLastReply( PostRevision $revision ) {
		if ( $revision->isTopicTitle() ) {
			return false;
		}
		$reply = $revision->getReplyToId()->getAlphadecimal();
		if ( !isset( $this->identityMap[$reply] ) ) {
			wfDebugLog( 'Flow', __METHOD__ . ": Missing $reply in identity map" );
			return false;
		}
		$parent = $this->identityMap[$revision->getReplyToId()->getAlphadecimal()];
		$keys = array_keys( $parent['children'] );
		return end( $keys ) === $revision->getPostId()->getAlphadecimal();
	}

	/**
	 * @param AbstractRevision $revision
	 * @return Workflow
	 * @throws \MWException
	 */
	protected function getWorkflow( AbstractRevision $revision ) {
		if ( $revision instanceof PostRevision ) {
			$rootPostId = $this->getRootPostId( $revision );
			return $this->getWorkflowById( $rootPostId );
		} elseif ( $revision instanceof Header ) {
			return $this->getWorkflowById( $revision->getWorkflowId() );
		} elseif ( $revision instanceof PostSummary ) {
			return $this->getWorkflowById( $revision->getCollection()->getWorkflowId() );
		} else {
			throw new \MWException( 'Unsupported revision type ' . get_class( $revision ) );
		}
	}

	/**
	 * Decides if the given abstract revision needs its prior revision for formatting
	 * @param AbstractRevision $revision
	 * @return bool true when the previous revision to this should be loaded
	 */
	protected function needsPreviousRevision( AbstractRevision $revision ) {
		// crappy special case needs the previous object so it can show the title
		// but only when outputting a full history api result(we don't know that here)
		return $revision instanceof PostRevision
			&& $revision->getChangeType() === 'edit-title';
	}

	/**
	 * Retrieves the previous revision for a given AbstractRevision
	 * @param AbstractRevision $revision The revision to retrieve the previous revision for.
	 * @return AbstractRevision|null AbstractRevision of the previous revision or null if no
	 *   previous revision.
	 */
	protected function getPreviousRevision( AbstractRevision $revision ) {
		$previousRevisionId = $revision->getPrevRevisionId();

		// original post; no previous revision
		if ( $previousRevisionId === null ) {
			return null;
		}

		if ( !isset( $this->revisionCache[$previousRevisionId->getAlphadecimal()] ) ) {
			$this->revisionCache[$previousRevisionId->getAlphadecimal()] =
				$this->storage->get( 'PostRevision', $previousRevisionId );
		}

		return $this->revisionCache[$previousRevisionId->getAlphadecimal()];
	}

	/**
	 * Retrieves the current revision for a given AbstractRevision
	 * @param AbstractRevision $revision The revision to retrieve the current revision for.
	 * @return AbstractRevision|null AbstractRevision of the current revision.
	 */
	protected function getCurrentRevision( AbstractRevision $revision ) {
		$cacheKey = $this->getCurrentRevisionCacheKey( $revision );
		if ( !isset( $this->currentRevisionsCache[$cacheKey] ) ) {
			$currentRevision = $revision->getCollection()->getLastRevision();

			$this->currentRevisionsCache[$cacheKey] = $currentRevision->getRevisionId();
			$this->revisionCache[$currentRevision->getRevisionId()->getAlphadecimal()] = $currentRevision;
		}

		$currentRevisionId = $this->currentRevisionsCache[$cacheKey];
		return $this->revisionCache[$currentRevisionId->getAlphadecimal()];
	}

	/**
	 * Retrieves the root post for a given PostRevision
	 * @param PostRevision $revision The revision to retrieve the root post for.
	 * @return PostRevision PostRevision of the root post.
	 * @throws \MWException
	 */
	protected function getRootPost( PostRevision $revision ) {
		if ( $revision->isTopicTitle() ) {
			return $revision;
		}
		$rootPostId = $this->getRootPostId( $revision );

		if ( !isset( $this->postCache[$rootPostId->getAlphadecimal()] ) ) {
			throw new \MWException( 'Did not load root post ' . $rootPostId->getAlphadecimal() );
		}

		$rootPost = $this->postCache[$rootPostId->getAlphadecimal()];
		if ( !$rootPost ) {
			throw new \MWException( 'Did not locate root post ' . $rootPostId->getAlphadecimal() );
		}
		if ( !$rootPost->isTopicTitle() ) {
			throw new \MWException( "Not a topic title: " . $rootPost->getRevisionId()->getAlphadecimal() );
		}

		return $rootPost;
	}

	/**
	 * Gets the root post ID for a given PostRevision
	 * @param PostRevision $revision The revision to get the root post ID for.
	 * @return UUID The UUID for the root post.
	 * @throws \MWException
	 */
	protected function getRootPostId( PostRevision $revision ) {
		$postId = $revision->getPostId();
		if ( $revision->isTopicTitle() ) {
			return $postId;
		} elseif ( isset( $this->rootPostIdCache[$postId->getAlphadecimal()] ) ) {
			return $this->rootPostIdCache[$postId->getAlphadecimal()];
		} else {
			throw new \MWException( "Unable to find root post ID for post " . $postId->getAlphadecimal() );
		}
	}

	/**
	 * Gets a Workflow object given its ID
	 * @param UUID $workflowId The Workflow ID to retrieve.
	 * @return Workflow The Workflow.
	 */
	protected function getWorkflowById( UUID $workflowId ) {
		$alpha = $workflowId->getAlphadecimal();
		if ( isset( $this->workflowCache[$alpha] ) ) {
			return $this->workflowCache[$alpha];
		} else {
			$this->workflowCache[$alpha] = $this->storage->get( 'Workflow', $workflowId );
			return $this->workflowCache[$alpha];
		}
	}

	/**
	 * @param AbstractRevision $revision
	 * @return string
	 */
	protected function getCurrentRevisionCacheKey( AbstractRevision $revision ) {
		return $revision->getRevisionType() . '-' . $revision->getCollectionId()->getAlphadecimal();
	}
}
