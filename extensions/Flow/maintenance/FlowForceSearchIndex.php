<?php

use Flow\Container;
use Flow\Model\UUID;
use Flow\Search\Connection;
use Flow\Search\Updaters\AbstractUpdater;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Similar to CirrusSearch's forceSearchIndex, this will force indexing of Flow
 * data in ElasticSearch.
 *
 * @ingroup Maintenance
 */
class FlowForceSearchIndex extends Maintenance {
	// @todo: do we need to steal more from Cirrus' ForceSearchIndex? What options are important?

	/**
	 * @var Connection
	 */
	protected $connection;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Force indexing Flow revisions (headers & topics).' );

		$this->setBatchSize( 10 );

		$this->addOption( 'fromId', 'Start indexing at a specific revision id (inclusive).', false, true );
		$this->addOption( 'toId', 'Stop indexing at a specific revision (inclusive).', false, true );
		$this->addOption( 'namespace', 'Only index revisions in this given namespace', false, true );

		$this->requireExtension( 'Flow' );
	}

	public function execute() {
		global $wgFlowSearchMaintenanceTimeout;

		$this->connection = Container::get( 'search.connection' );

		// Set the timeout for maintenance actions
		$this->connection->setTimeout( $wgFlowSearchMaintenanceTimeout );

		/** @var AbstractUpdater[] $updaters */
		$updaters = Container::get( 'search.index.updaters' );
		foreach ( $updaters as $updaterType => $updater ) {
			$fromId = $this->getOption( 'fromId', null );
			$fromId = $fromId ? UUID::create( $fromId ) : null;
			$toId = $this->getOption( 'toId', null );
			$toId = $toId ? UUID::create( $toId ) : null;
			if ( $toId !== null ) {
				// AbstractIterator::toId is exclusive, but we want inclusive,
				// so just feed toId() the next possible UUID (UUID + 1)
				// We need some base conversion & bcadd because the number may
				// be too large to be an int.
				$decimal = \Wikimedia\base_convert( $toId->getAlphadecimal(), 36, 10 );
				$new = bcadd( $decimal, '1', 0 );
				$alnum = \Wikimedia\base_convert( $new, 10, 36 );
				$toId = UUID::create( $alnum );
			}
			$namespace = $this->getOption( 'namespace', null );
			$total = 0;

			$updater->iterator->setNamespace( $namespace );
			$updater->iterator->setFrom( $fromId );
			$updater->iterator->setTo( $toId );

			$total += $updater->updateRevisions( null, null, $this->mBatchSize );
			$this->output( "Indexed $total $updaterType document(s)\n" );
		}
	}
}

$maintClass = FlowForceSearchIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
