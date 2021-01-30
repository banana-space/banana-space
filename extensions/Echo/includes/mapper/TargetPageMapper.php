<?php

/**
 * Database mapper for EchoTargetPage model
 */
class EchoTargetPageMapper extends EchoAbstractMapper {

	/**
	 * List of db fields used to construct an EchoTargetPage model
	 * @var string[]
	 */
	protected static $fields = [
		'etp_page',
		'etp_event'
	];

	/**
	 * Insert an EchoTargetPage instance into the database
	 *
	 * @param EchoTargetPage $targetPage
	 * @return bool
	 */
	public function insert( EchoTargetPage $targetPage ) {
		$dbw = $this->dbFactory->getEchoDb( DB_MASTER );

		$row = $targetPage->toDbArray();

		$dbw->insert( 'echo_target_page', $row, __METHOD__ );

		return true;
	}
}
