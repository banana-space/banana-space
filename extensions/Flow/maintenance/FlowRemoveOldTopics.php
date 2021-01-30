<?php

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Data\Utils\RawSql;
use Flow\DbFactory;
use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Repository\TreeRepository;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * @ingroup Maintenance
 */
class FlowRemoveOldTopics extends Maintenance {
	/**
	 * @var bool
	 */
	protected $dryRun = false;

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var TreeRepository
	 */
	protected $treeRepo;

	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Deletes old topics" );

		$this->addOption( 'date', 'Date cutoff (in any format understood by wfTimestamp), topics ' .
			'older than this date will be deleted.', true, true );
		$this->addOption( 'dryrun', 'Simulate script run, without actually deleting anything' );

		$this->setBatchSize( 10 );

		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		$this->dryRun = $this->getOption( 'dryrun', false );
		$this->storage = Container::get( 'storage' );
		$this->treeRepo = Container::get( 'repository.tree' );
		$this->dbFactory = Container::get( 'db.factory' );

		$timestamp = wfTimestamp( TS_MW, $this->getOption( 'date' ) );

		$this->removeHeader( $timestamp );
		// remove topics that are older than the given timestamp
		$this->removeTopics( $timestamp );
		// remove topics that have more recent updates, but only from Flow talk
		// page manager
		$this->removeTopicsWithFlowUpdates( $timestamp );
	}

	protected function removeHeader( $timestamp ) {
		$dbr = $this->dbFactory->getDB( DB_REPLICA );

		// we don't store a timestamp with revisions - the id also holds date
		// info, so that's what we should compare against
		$endId = UUID::getComparisonUUID( $timestamp );

		// start from around unix epoch - there can be no Flow data before that
		$startId = UUID::getComparisonUUID( '1' );
		do {
			/** @var Header[] $revisions */
			$revisions = $this->storage->find(
				'Header',
				[
					'rev_user_wiki' => wfWikiID(),
					'rev_type' => 'header',
					new RawSql( 'rev_id > ' . $dbr->addQuotes( $startId->getBinary() ) ),
					new RawSql( 'rev_id < ' . $dbr->addQuotes( $endId->getBinary() ) ),
					// only fetch original post at this point: we still need to
					// narrow down the results
					'rev_parent_id' => null,
				],
				[
					'limit' => $this->mBatchSize,
					'sort' => 'rev_id',
					'order' => 'ASC',
				]
			);

			if ( empty( $revisions ) ) {
				break;
			}

			// prepare for next batch, which will start at this
			/** @var UUID $startId */
			$startId = end( $revisions )->getRevisionId();

			// we've now found all first revisions prior to a certain date, but we
			// don't want to remove those that have revisions after that date cutoff
			// (we don't want to break history)
			// let's see if any has revisions more recent than timestamp
			$conds = [];
			$uuids = [];
			foreach ( $revisions as $revision ) {
				// keep track of UUIDs we may want to delete
				$uuids[$revision->getCollectionId()->getAlphadecimal()] = $revision->getCollectionId();

				$conds[] = [
					'rev_user_wiki' => wfWikiID(),
					'rev_type' => 'header',
					new RawSql( 'rev_id >= ' . $dbr->addQuotes( $endId->getBinary() ) ),
					'rev_type_id' => $revision->getCollectionId()->getBinary(),
				];
			}

			/** @var Header[] $recent */
			$recent = $this->storage->findMulti( 'Header', $conds, [ 'limit' => 1 ] );

			// now exclude collection ids where there's a revision that is more
			// recent than the timestamp cutoff
			foreach ( $recent as $revisions ) {
				foreach ( $revisions as $revision ) {
					unset( $uuids[$revision->getCollectionId()->getAlphadecimal()] );
				}
			}

			// by now, there may be nothing left to remove, so move on to the
			// next batch...
			if ( empty( $uuids ) ) {
				continue;
			}

			$revisions = $this->storage->find(
				'Header',
				[
					'rev_user_wiki' => wfWikiID(),
					'rev_type' => 'header',
					'rev_type_id' => UUID::convertUUIDs( $uuids ),
				]
			);

			$this->output( 'Removing ' . count( $revisions ) . ' header revisions from ' .
				count( $uuids ) . ' headers (up to ' . $startId->getTimestamp() . ")\n" );

			$this->dbFactory->getDB( DB_MASTER )->begin( __METHOD__ );

			foreach ( $revisions as $revision ) {
				$this->removeReferences( $revision );
			}

			$this->multiRemove( $revisions );

			if ( $this->dryRun ) {
				$this->dbFactory->getDB( DB_MASTER )->rollback( __METHOD__ );
			} else {
				$this->dbFactory->getDB( DB_MASTER )->commit( __METHOD__ );
				$this->dbFactory->waitForReplicas();
			}
		} while ( !empty( $revisions ) );
	}

	/**
	 * @param string $timestamp Timestamp in TS_MW format
	 * @throws \Flow\Exception\FlowException
	 */
	protected function removeTopics( $timestamp ) {
		$dbr = $this->dbFactory->getDB( DB_REPLICA );

		// start from around unix epoch - there can be no Flow data before that
		$startId = UUID::getComparisonUUID( '1' );
		do {
			$workflows = $this->storage->find(
				'Workflow',
				[
					new RawSql( 'workflow_id > ' . $dbr->addQuotes( $startId->getBinary() ) ),
					'workflow_wiki' => wfWikiID(),
					'workflow_type' => 'topic',
					new RawSql( 'workflow_last_update_timestamp < ' . $dbr->addQuotes( $timestamp ) ),
				],
				[
					'limit' => $this->mBatchSize,
					'sort' => 'workflow_id',
					'order' => 'ASC',
				]
			);

			if ( empty( $workflows ) ) {
				break;
			}

			// prepare for next batch
			/** @var UUID $startId */
			$startId = end( $workflows )->getId();

			$this->output( 'Removing ' . count( $workflows ) .
				' topic workflows (up to ' . $startId->getTimestamp() . ")\n" );
			$this->removeWorkflows( $workflows );
		} while ( !empty( $workflows ) );
	}

	/**
	 * @param string $timestamp Timestamp in TS_MW format
	 * @throws \Wikimedia\Rdbms\DBUnexpectedError
	 * @throws \Flow\Exception\FlowException
	 */
	protected function removeTopicsWithFlowUpdates( $timestamp ) {
		$dbr = $this->dbFactory->getDB( DB_REPLICA );
		$talkpageManager = Flow\Hooks::getOccupationController()->getTalkpageManager();

		// start from around unix epoch - there can be no Flow data before that
		$batchStartId = UUID::getComparisonUUID( '1' );

		// we only care about revisions since cutoff here
		$cutoffStartId = UUID::getComparisonUUID( $timestamp );

		do {
			$workflowIds = $dbr->selectFieldValues(
				[ 'flow_workflow', 'flow_tree_node', 'flow_revision' ],
				'workflow_id',
				[
					// revisions more recent than cutoff time
					'rev_id > ' . $dbr->addQuotes( $cutoffStartId->getBinary() ),
					// workflow_id condition is only used to batch, the exact
					// $batchStartId otherwise doesn't matter (unlike rev_id)
					'workflow_id > ' . $dbr->addQuotes( $batchStartId->getBinary() ),
					'workflow_wiki' => wfWikiID(),
					'workflow_type' => 'topic',
					'workflow_last_update_timestamp >= ' . $dbr->addQuotes( $timestamp ),
				],
				__METHOD__,
				[
					'LIMIT' => $this->mBatchSize,
					'ORDER BY' => 'workflow_id ASC',
					// we only want to find topics that were only altered by talk
					// page manager: as long as anyone else edited any post, we're
					// not interested in it
					'GROUP BY' => 'workflow_id',
					'HAVING' => [ 'GROUP_CONCAT(DISTINCT rev_user_id)' => $talkpageManager->getId() ],
				],
				[
					'flow_tree_node' => [ 'INNER JOIN', [ 'tree_ancestor_id = workflow_id' ] ],
					'flow_revision' => [ 'INNER JOIN', [ 'rev_type_id = tree_descendant_id' ] ],
				]
			);

			if ( empty( $workflowIds ) ) {
				break;
			}

			$workflows = $this->storage->getMulti( 'Workflow', $workflowIds );

			// prepare for next batch
			/** @var UUID $batchStartId */
			$batchStartId = end( $workflows )->getId();

			$this->output( 'Removing ' . count( $workflows ) . ' topic workflows with recent ' .
				'Flow updates (up to ' . $batchStartId->getTimestamp() . ")\n" );
			$this->removeWorkflows( $workflows );
		} while ( !empty( $workflows ) );
	}

	/**
	 * @param Workflow[] $workflows
	 * @throws \Wikimedia\Rdbms\DBUnexpectedError
	 */
	protected function removeWorkflows( array $workflows ) {
		$this->dbFactory->getDB( DB_MASTER )->begin( __METHOD__ );

		foreach ( $workflows as $workflow ) {
			$this->removeSummary( $workflow );
			$this->removePosts( $workflow );
			$this->removeTopicList( $workflow );
		}

		$this->multiRemove( $workflows );

		if ( $this->dryRun ) {
			$this->dbFactory->getDB( DB_MASTER )->rollback( __METHOD__ );
		} else {
			$this->dbFactory->getDB( DB_MASTER )->commit( __METHOD__ );
			$this->dbFactory->waitForReplicas();
		}
	}

	protected function removeTopicList( Workflow $workflow ) {
		$entries = $this->storage->find( 'TopicListEntry', [ 'topic_id' => $workflow->getId() ] );
		if ( $entries ) {
			$this->output( 'Removing ' . count( $entries ) . " topiclist entries.\n" );
			$this->multiRemove( $entries );
		}
	}

	protected function removeSummary( Workflow $workflow ) {
		$revisions = $this->storage->find( 'PostSummary', [ 'rev_type_id' => $workflow->getId() ] );
		if ( $revisions ) {
			foreach ( $revisions as $revision ) {
				$this->removeReferences( $revision );
			}

			$this->output( 'Removing ' . count( $revisions ) . " summary revisions from 1 topic.\n" );
			$this->multiRemove( $revisions );
		}
	}

	/**
	 * @param UUID $parentId
	 * @param array $subtree
	 * @return array
	 */
	protected function sortSubtree( UUID $parentId, array $subtree ) {
		$flat = [];

		// first recursively process all children, so they come first in $flat
		foreach ( $subtree['children'] as $id => $data ) {
			$flat = array_merge(
				$flat,
				$this->sortSubtree( UUID::create( $id ), $data )
			);
		}

		// then add parent, which should come last in $flat
		$flat[] = $parentId;

		return $flat;
	}

	protected function removePosts( Workflow $workflow ) {
		// fetch all children (posts) from a topic & reverse-sort all the posts:
		// deepest-nested children should come first, parents last
		$subtree = $this->treeRepo->fetchSubtree( $workflow->getId() );
		$uuids = $this->sortSubtree( $workflow->getId(), $subtree );

		$conds = [];
		foreach ( $uuids as $id ) {
			$conds[] = [ 'rev_type_id' => $id ];
		}

		$posts = $this->storage->findMulti( 'PostRevision', $conds );
		$count = 0;
		foreach ( $posts as $revisions ) {
			/** @var PostRevision[] $revisions */
			foreach ( $revisions as $revision ) {
				$this->removeReferences( $revision );
			}

			$count += count( $revisions );
			$this->multiRemove( $revisions );

			foreach ( $revisions as $revision ) {
				$this->treeRepo->delete( $revision->getCollectionId() );
			}
		}
		$this->output( 'Removing ' . $count . ' post revisions from ' . count( $posts ) . " posts.\n" );
	}

	protected function removeReferences( AbstractRevision $revision ) {
		$wikiReferences = $this->storage->find( 'WikiReference', [
			'ref_src_wiki' => wfWikiID(),
			'ref_src_object_type' => $revision->getRevisionType(),
			'ref_src_object_id' => $revision->getCollectionId(),
		] );
		if ( $wikiReferences ) {
			$this->output( 'Removing ' . count( $wikiReferences ) . " wiki references from 1 revision.\n" );
			$this->multiRemove( $wikiReferences );
		}

		$urlReferences = $this->storage->find( 'URLReference', [
			'ref_src_wiki' => wfWikiID(),
			'ref_src_object_type' => $revision->getRevisionType(),
			'ref_src_object_id' => $revision->getCollectionId(),
		] );
		if ( $urlReferences ) {
			$this->output( 'Removing ' . count( $urlReferences ) . " url references from 1 revision.\n" );
			$this->multiRemove( $urlReferences );
		}
	}

	protected function multiRemove( array $objects ) {
		$this->storage->multiRemove( $objects );
	}
}

$maintClass = FlowRemoveOldTopics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
