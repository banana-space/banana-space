<?php

namespace EchoPush;

use CentralIdLookup;
use EchoAbstractMapper;
use IDatabase;
use MediaWiki\Storage\NameTableStore;
use User;
use Wikimedia\Rdbms\DBError;

class SubscriptionManager extends EchoAbstractMapper {

	/** @var IDatabase */
	private $dbw;

	/** @var IDatabase */
	private $dbr;

	/** @var CentralIdLookup */
	private $centralIdLookup;

	/** @var NameTableStore */
	private $pushProviderStore;

	/**
	 * @param IDatabase $dbw primary DB connection (for writes)
	 * @param IDatabase $dbr replica DB connection (for reads)
	 * @param CentralIdLookup $centralIdLookup
	 * @param NameTableStore $pushProviderStore
	 */
	public function __construct(
		IDatabase $dbw,
		IDatabase $dbr,
		CentralIdLookup $centralIdLookup,
		NameTableStore $pushProviderStore
	) {
		parent::__construct();
		$this->dbw = $dbw;
		$this->dbr = $dbr;
		$this->centralIdLookup = $centralIdLookup;
		$this->pushProviderStore = $pushProviderStore;
	}

	/**
	 * Store push subscription information for a user.
	 * @param User $user
	 * @param string $provider Provider name string (validated by presence in the PARAM_TYPE array)
	 * @param string $token Subscriber token provided by the push provider
	 * @return bool true if the subscription was created; false if the token already exists
	 */
	public function create( User $user, string $provider, string $token ): bool {
		$this->dbw->insert(
			'echo_push_subscription',
			[
				'eps_user' => $this->getCentralId( $user ),
				'eps_provider' => $this->pushProviderStore->acquireId( $provider ),
				'eps_token' => $token,
				'eps_token_sha256' => hash( 'sha256', $token ),
				'eps_updated' => $this->dbw->timestamp()
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
		return (bool)$this->dbw->affectedRows();
	}

	/**
	 * Get all registered subscriptions for a user (by central ID).
	 * @param int $centralId
	 * @return array array of Subscription objects
	 */
	public function getSubscriptionsForUser( int $centralId ) {
		$res = $this->dbr->select(
			[ 'echo_push_subscription', 'echo_push_provider' ],
			'*',
			[ 'eps_user' => $centralId ],
			__METHOD__,
			[],
			[ 'echo_push_provider' => [ 'INNER JOIN', [ 'eps_provider = epp_id' ] ] ]
		);
		$result = [];
		foreach ( $res as $row ) {
			$result[] = Subscription::newFromRow( $row );
		}
		return $result;
	}

	/**
	 * Delete a push subscription for a user.
	 * Note: Selecting for the user in addition to the token should be redundant, since tokens
	 * are globally unique and user-specific, but it's probably safest to keep it as a sanity check.
	 * Also, currently the eps_user column is indexed but eps_token is not.
	 * @param User $user
	 * @param string $token Delete the subscription with this token
	 * @return int number of rows deleted
	 * @throws DBError
	 */
	public function delete( User $user, string $token ): int {
		$this->dbw->delete(
			'echo_push_subscription',
			[
				'eps_user' => $this->getCentralId( $user ),
				'eps_token' => $token,
			],
			__METHOD__
		);
		return $this->dbw->affectedRows();
	}

	/**
	 * Get the user's central ID.
	 * @param User $user
	 * @return int
	 */
	private function getCentralId( User $user ): int {
		return $this->centralIdLookup->centralIdFromLocalUser(
			$user,
			CentralIdLookup::AUDIENCE_RAW
		);
	}

}
