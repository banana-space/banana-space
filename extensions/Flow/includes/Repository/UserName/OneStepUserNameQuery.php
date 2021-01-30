<?php

namespace Flow\Repository\UserName;

use Flow\DbFactory;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Provide usernames filtered by per-wiki ipblocks. Batches together
 * database requests for multiple usernames when possible.
 */
class OneStepUserNameQuery implements UserNameQuery {
	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * @param DbFactory $dbFactory
	 */
	public function __construct( DbFactory $dbFactory ) {
		$this->dbFactory = $dbFactory;
	}

	/**
	 * Look up usernames while respecting ipblocks with one query.
	 * Unused, check to see if this is reasonable to use.
	 *
	 * @param string $wiki
	 * @param array $userIds
	 * @return IResultWrapper|null
	 */
	public function execute( $wiki, array $userIds ) {
		$dbr = $this->dbFactory->getWikiDB( DB_REPLICA, $wiki );
		return $dbr->select(
			/* table */ [ 'user', 'ipblocks' ],
			/* select */ [ 'user_id', 'user_name' ],
			/* conds */ [
				'user_id' => $userIds,
				// only accept records that did not match ipblocks
				'ipb_deleted is null'
			],
			__METHOD__,
			/* options */ [],
			/* join_conds */ [
				'ipblocks' => [ 'LEFT OUTER', [
					'ipb_user' => 'user_id',
					// match only deleted users
					'ipb_deleted' => 1,
				] ]
			]
		);
	}
}
