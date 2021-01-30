<?php

use Flow\Container;
use Flow\FlowActions;
use Flow\Model\UUID;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Adjusts edit counts for all existing Flow data.
 *
 * @ingroup Maintenance
 */
class FlowFixEditCount extends LoggedUpdateMaintenance {
	/**
	 * Array of [username => increased edit count]
	 *
	 * @var array
	 */
	protected $updates = [];

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Adjusts edit counts for all existing Flow data' );

		$this->addOption( 'start', 'Timestamp to start counting revisions at', false, true );
		$this->addOption( 'stop', 'Timestamp to stop counting revisions at', false, true );

		$this->setBatchSize( 300 );

		$this->requireExtension( 'Flow' );
	}

	protected function getUpdateKey() {
		return 'FlowFixEditCount';
	}

	protected function doDBUpdates() {
		/** @var IDatabase $dbr */
		$dbr = Container::get( 'db.factory' )->getDB( DB_REPLICA );
		$countableActions = $this->getCountableActions();

		// defaults = date of first Flow commit up until now
		$continue = UUID::getComparisonUUID( $this->getOption( 'start', '20130710230511' ) );
		$stop = UUID::getComparisonUUID( $this->getOption( 'stop', time() ) );

		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		while ( $continue !== false ) {
			$continue = $this->refreshBatch( $dbr, $continue, $countableActions, $stop );

			// wait for core (we're updating user table) replicas to catch up
			$lbFactory->waitForReplication();
		}

		$this->output( "Done increasing edit counts. Increased:\n" );
		foreach ( $this->updates as $userId => $count ) {
			$userName = User::newFromId( $userId )->getName();
			$this->output( "  User $userId ($userName): +$count\n" );
		}

		return true;
	}

	public function refreshBatch( IDatabase $dbr, UUID $continue, array $countableActions, UUID $stop ) {
		$rows = $dbr->select(
			'flow_revision',
			[ 'rev_id', 'rev_user_id' ],
			[
				'rev_id > ' . $dbr->addQuotes( $continue->getBinary() ),
				'rev_id <= ' . $dbr->addQuotes( $stop->getBinary() ),
				'rev_user_id > 0',
				'rev_user_wiki' => wfWikiID(),
				'rev_change_type' => $countableActions,
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_id ASC',
				'LIMIT' => $this->mBatchSize,
			]
		);

		// end of data
		if ( !$rows || $rows->numRows() === 0 ) {
			return false;
		}

		foreach ( $rows as $row ) {
			// User::incEditCount only allows for edit count to be increased 1
			// at a time. It'd be better to immediately be able to increase the
			// edit count by the exact number it should be increased with, but
			// I'd rather re-use existing code, especially in a run-once script,
			// where performance is not the most important thing ;)
			$user = User::newFromId( $row->rev_user_id );
			$user->incEditCount();

			// save updates so we can print them when the script is done running
			if ( !isset( $this->updates[$user->getId()] ) ) {
				$this->updates[$user->getId()] = 0;
			}
			$this->updates[$user->getId()]++;

			// set value for next batch to continue at
			$continue = $row->rev_id;
		}

		return UUID::create( $continue );
	}

	/**
	 * Returns list of rev_change_type values that warrant an editcount increase.
	 *
	 * @return string[]
	 */
	protected function getCountableActions() {
		$allowedActions = [];

		/** @var FlowActions $actions */
		$actions = Container::get( 'flow_actions' );
		foreach ( $actions->getActions() as $action ) {
			if ( $actions->getValue( $action, 'editcount' ) ) {
				$allowedActions[] = $action;
			}
		}

		return $allowedActions;
	}
}

$maintClass = FlowFixEditCount::class;
require_once RUN_MAINTENANCE_IF_MAIN;
