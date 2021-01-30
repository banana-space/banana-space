<?php
/**
 * Update the titles of flow-topic-resolved events to point to boards instead of topics
 *
 * @ingroup Maintenance
 */

use Flow\Container;
use Flow\WorkflowLoaderFactory;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Maintenance script that update flow-topic-resolved events to point event_page_id to the board instead of the topic.
 *
 * @ingroup Maintenance
 */
class FlowUpdateResolvedNotifTitles extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Update the titles of flow-topic-resolved Echo events to point to boards instead of topics" );

		$this->setBatchSize( 500 );

		$this->requireExtension( 'Flow' );
	}

	public function getUpdateKey() {
		return __CLASS__;
	}

	public function doDBUpdates() {
		$dbFactory = MWEchoDbFactory::newFromDefault();
		$dbw = $dbFactory->getEchoDb( DB_MASTER );
		$dbr = $dbFactory->getEchoDb( DB_REPLICA );
		// We can't join echo_event with page, because those tables can be on different
		// DB clusters. If we had been able to do that, we could have added
		// wHERE page_namespace=NS_TOPIC, but instead we have to examine all rows
		// and skip the non-NS_TOPIC ones.
		$iterator = new BatchRowIterator(
			$dbr,
			'echo_event',
			'event_id',
			$this->mBatchSize
		);
		$iterator->addConditions( [
			'event_type' => 'flow-topic-resolved',
			'event_page_id IS NOT NULL',
		] );
		$iterator->setFetchColumns( [ 'event_page_id' ] );

		$storage = Container::get( 'storage.workflow' );

		$this->output( "Retitling flow-topic-resolved events...\n" );

		$processed = 0;
		foreach ( $iterator as $batch ) {
			foreach ( $batch as $row ) {
				$topicTitle = Title::newFromID( $row->event_page_id );
				if ( !$topicTitle || $topicTitle->getNamespace() !== NS_TOPIC ) {
					continue;
				}
				$boardTitle = null;
				try {
					$uuid = WorkflowLoaderFactory::uuidFromTitle( $topicTitle );
					$workflow = $storage->get( $uuid );
					if ( $workflow ) {
						$boardTitle = $workflow->getOwnerTitle();
					}
				} catch ( Exception $e ) {
				}
				if ( $boardTitle ) {
					$dbw->update(
						'echo_event',
						[ 'event_page_id' => $boardTitle->getArticleID() ],
						[ 'event_id' => $row->event_id ],
						__METHOD__
					);
					$processed += $dbw->affectedRows();
				} else {
					$this->output( "Could not find board for topic: " . $topicTitle->getPrefixedText() . "\n" );
				}
			}

			$this->output( "Updated $processed events.\n" );
			$dbFactory->waitForReplicas();
		}

		return true;
	}
}

$maintClass = FlowUpdateResolvedNotifTitles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
