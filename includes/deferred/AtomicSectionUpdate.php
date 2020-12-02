<?php

use Wikimedia\Rdbms\IDatabase;

/**
 * Deferrable Update for closure/callback updates via IDatabase::doAtomicSection()
 * @since 1.27
 */
class AtomicSectionUpdate implements DeferrableUpdate, DeferrableCallback {
	/** @var IDatabase */
	private $dbw;
	/** @var string */
	private $fname;
	/** @var callable|null */
	private $callback;

	/**
	 * @param IDatabase $dbw DB handle; update aborts if a transaction now this rolls back
	 * @param string $fname Caller name (usually __METHOD__)
	 * @param callable $callback
	 * @param IDatabase[] $conns Abort if a transaction now on one of these rolls back [optional]
	 * @see IDatabase::doAtomicSection()
	 */
	public function __construct( IDatabase $dbw, $fname, callable $callback, array $conns = [] ) {
		$this->dbw = $dbw;
		$this->fname = $fname;
		$this->callback = $callback;
		// Register DB connections for which uncommitted changes are related to this update
		$conns[] = $dbw;
		foreach ( $conns as $conn ) {
			if ( $conn->trxLevel() ) {
				$conn->onTransactionResolution( [ $this, 'cancelOnRollback' ], $fname );
			}
		}
	}

	public function doUpdate() {
		if ( $this->callback ) {
			$this->dbw->doAtomicSection( $this->fname, $this->callback );
		}
	}

	/**
	 * @internal This method is public so that it works with onTransactionResolution()
	 * @param int $trigger
	 */
	public function cancelOnRollback( $trigger ) {
		if ( $trigger === IDatabase::TRIGGER_ROLLBACK ) {
			$this->callback = null;
		}
	}

	public function getOrigin() {
		return $this->fname;
	}
}
