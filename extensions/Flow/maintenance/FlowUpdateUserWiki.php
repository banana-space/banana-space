<?php

use Flow\Container;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Update all xxx_user_wiki field to have the correct wiki name
 *
 * @ingroup Maintenance
 */
class FlowUpdateUserWiki extends LoggedUpdateMaintenance {

	/**
	 * Used to track the number of current updated count
	 */
	private $updatedCount = 0;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update xxx_user_wiki field in tables: flow_workflow, flow_tree_revision, flow_revision" );
		$this->requireExtension( 'Flow' );
		$this->setBatchSize( 300 );
	}

	/**
	 * This is a top-to-bottom update, the process is like this:
	 * workflow -> header -> header revision -> history
	 * workflow -> topic list -> post tree revision -> post revision -> history
	 *
	 * Some side effect, the script will also update those *_user_wiki fields with
	 * empty *_user_id and *_user_ip, but this doesn't hurt. Alternatively, we could
	 * add a check user_id != 0 and user_ip is not null to the query, but this will
	 * result in more db queries
	 * @return true
	 * @throws \MWException
	 */
	protected function doDBUpdates() {
		$id = '';
		$count = $this->mBatchSize;
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );

		// If table flow_header_revision does not exist, that means the wiki
		// has run the data migration before or the wiki starts from scratch,
		// there is no point to run the script againt invalid tables
		if ( !$dbr->tableExists( 'flow_header_revision', __METHOD__ ) ) {
			return true;
		}

		while ( $count == $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_workflow' ],
				[ 'workflow_wiki', 'workflow_id', 'workflow_type' ],
				[
					'workflow_id > ' . $dbr->addQuotes( $id ),
				],
				__METHOD__,
				[ 'ORDER BY' => 'workflow_id ASC', 'LIMIT' => $this->mBatchSize ]
			);
			if ( $res ) {
				foreach ( $res as $row ) {
					$count++;
					$id = $row->workflow_id;
					$uuid = UUID::create( $row->workflow_id );
					$workflow = Container::get( 'storage.workflow' )->get( $uuid );
					if ( $workflow ) {
						// definition type 'topic' is always under a 'discussion' and they
						// will be handled while processing 'discussion'
						if ( $row->workflow_type == 'discussion' ) {
							$this->updateHeader( $workflow, $row->workflow_wiki );
							$this->updateTopicList( $workflow, $row->workflow_wiki );
						}
					}
				}
			} else {
				throw new \MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
			}
		}

		return true;
	}

	/**
	 * Update header
	 * @param Workflow $workflow
	 * @param string $wiki
	 */
	private function updateHeader( $workflow, $wiki ) {
		$id = '';
		$count = $this->mBatchSize;
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );

		while ( $count == $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_header_revision', 'flow_revision' ],
				[ 'rev_id', 'rev_type' ],
				[
					'rev_id > ' . $dbr->addQuotes( $id ),
					'header_rev_id = rev_id',
					'header_workflow_id' => $workflow->getId()->getBinary()
				],
				__METHOD__,
				[ 'ORDER BY' => 'header_rev_id ASC', 'LIMIT' => $this->mBatchSize ]
			);
			if ( $res ) {
				foreach ( $res as $row ) {
					$count++;
					$id = $row->rev_id;
					$revision = Container::get( 'storage.header' )->get( UUID::create( $row->rev_id ) );
					if ( $revision ) {
						$this->updateRevision( $revision, $wiki );
					}
				}
			} else {
				throw new \MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
			}

		}
	}

	/**
	 * Update topic list
	 * @param Workflow $workflow
	 * @param string $wiki
	 */
	private function updateTopicList( $workflow, $wiki ) {
		$id = '';
		$count = $this->mBatchSize;
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );

		while ( $count == $this->mBatchSize ) {
			$count = 0;
			$res = $dbr->select(
				[ 'flow_topic_list' ],
				[ 'topic_id' ],
				[
					'topic_list_id' => $workflow->getId()->getBinary(),
					'topic_id > ' . $dbr->addQuotes( $id ),
				],
				__METHOD__,
				[ 'ORDER BY' => 'topic_id ASC', 'LIMIT' => $this->mBatchSize ]
			);
			if ( $res ) {
				$index = 0;
				foreach ( $res as $row ) {
					$count++;
					$index++;
					$id = $row->topic_id;
					$post = Container::get( 'loader.root_post' )->get( UUID::create( $row->topic_id ) );
					if ( $post ) {
						$this->updatePost( $post, $wiki );
					}
				}
			} else {
				throw new \MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
			}
		}
	}

	/**
	 * Update post
	 * @param PostRevision $post
	 * @param string $wiki
	 */
	private function updatePost( $post, $wiki ) {
		$this->updateHistory( $post, $wiki );
		$this->updateRevision( $post, $wiki );
		foreach ( $post->getChildren() as $child ) {
			$this->updatePost( $child, $wiki );
		}
	}

	/**
	 * Update history revision
	 * @param PostRevision $post
	 * @param string $wiki
	 */
	private function updateHistory( PostRevision $post, $wiki ) {
		if ( $post->getPrevRevisionId() ) {
			$parent = Container::get( 'storage.post' )->get( UUID::create( $post->getPrevRevisionId() ) );
			if ( $parent ) {
				$this->updateRevision( $parent, $wiki );
				$this->updateHistory( $parent, $wiki );
			}
		}
	}

	/**
	 * Update either header or post revision
	 * @param PostRevision $revision
	 * @param string $wiki
	 */
	private function updateRevision( $revision, $wiki ) {
		if ( !$revision ) {
			return;
		}
		$type = $revision->getRevisionType();

		$dbw = Container::get( 'db.factory' )->getDB( DB_MASTER );
		$res = $dbw->update(
			'flow_revision',
			[
				'rev_user_wiki' => $wiki,
				'rev_mod_user_wiki' => $wiki,
				'rev_edit_user_wiki' => $wiki,
			],
			[
				'rev_id' => $revision->getRevisionId()->getBinary(),
			],
			__METHOD__
		);
		if ( !$res ) {
			throw new \MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
		}
		$this->checkForReplica();

		if ( $type === 'post' ) {
			$res = $dbw->update(
				'flow_tree_revision',
				[
					'tree_orig_user_wiki' => $wiki,
				],
				[
					'tree_rev_id' => $revision->getRevisionId()->getBinary(),
				],
				__METHOD__
			);
			if ( !$res ) {
				throw new \MWException( 'SQL error in maintenance script ' . __CLASS__ . '::' . __METHOD__ );
			}
			$this->checkForReplica();
		}
	}

	private function checkForReplica() {
		global $wgFlowCluster;

		$this->updatedCount++;
		if ( $this->updatedCount > $this->mBatchSize ) {
			$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$lbFactory->waitForReplication( [ 'cluster' => $wgFlowCluster ] );
			$this->updatedCount = 0;
		}
	}

	/**
	 * Get the update key name to go in the update log table
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'FlowUpdateUserWiki';
	}
}

$maintClass = FlowUpdateUserWiki::class;
require_once RUN_MAINTENANCE_IF_MAIN;
