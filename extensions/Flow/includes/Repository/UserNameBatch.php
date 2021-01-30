<?php
/**
 * Provide usernames filtered by per-wiki ipblocks. Batches together
 * database requests for multiple usernames when possible.
 */
namespace Flow\Repository;

use Flow\Model\UserTuple;
use MapCacheLRU;
use User;

/**
 * Batch together queries for a bunch of wiki+userid -> username
 */
class UserNameBatch {
	// Maximum number of usernames to cache for each wiki
	private const USERNAMES_PER_WIKI = 250;

	/**
	 * @var UserName\UserNameQuery
	 */
	protected $query;

	/**
	 * @var array[] map from wikiid to list of userid's to request
	 */
	protected $queued = [];

	/**
	 * @var array Map from wiki id to MapCacheLRU.  MapCacheLRU is a map of user ID (as
	 *  string, though to PHP it's the same anyway...) to username.
	 */
	protected $usernames = [];

	/**
	 * @param UserName\UserNameQuery $query
	 * @param array $queued map from wikiid to list of userid's to request
	 */
	public function __construct( UserName\UserNameQuery $query, array $queued = [] ) {
		$this->query = $query;
		foreach ( $queued as $wiki => $userIds ) {
			$this->queued[$wiki] = array_map( 'intval', $userIds );
		}
	}

	/**
	 * Make sure the LRU for the given wiki is in place.
	 *
	 * @param string $wiki Wiki identifier
	 */
	protected function ensureLRU( $wiki ) {
		if ( !isset( $this->usernames[$wiki] ) ) {
			$this->usernames[$wiki] = new MapCacheLRU( self::USERNAMES_PER_WIKI );
		}
	}

	public function clear() {
		$this->queued = [];
		$this->usernames = [];
	}

	/**
	 * @param string $wiki
	 * @param int $userId
	 * @param string|null $userName Non null to set known usernames like $wgUser
	 */
	public function add( $wiki, $userId, $userName = null ) {
		$userId = (int)$userId;

		$this->ensureLRU( $wiki );
		if ( $userName !== null ) {
			$this->usernames[$wiki]->set( (string)$userId, $userName );
		} elseif ( !$this->usernames[$wiki]->has( (string)$userId ) ) {
			$this->queued[$wiki][] = $userId;
		}
	}

	/**
	 * @param UserTuple $tuple
	 */
	public function addFromTuple( UserTuple $tuple ) {
		$this->add( $tuple->wiki, $tuple->id, $tuple->ip );
	}

	/**
	 * Get the displayable username
	 *
	 * @param string $wiki
	 * @param int $userId
	 * @param string|bool $userIp
	 * @return string|bool false if username is not found or display is suppressed
	 * @todo Return something better for not found / suppressed, but what? Making
	 *   return type string|Message would suck.
	 */
	public function get( $wiki, $userId, $userIp = false ) {
		$userId = (int)$userId;
		if ( $userId === 0 ) {
			return $userIp;
		}

		$this->ensureLRU( $wiki );
		if ( !$this->usernames[$wiki]->has( (string)$userId ) ) {
			$this->queued[$wiki][] = $userId;
			$this->resolve( $wiki );
		}
		return $this->usernames[$wiki]->get( (string)$userId );
	}

	/**
	 * @param UserTuple $tuple
	 * @return string|bool false if username is not found or display is suppressed
	 */
	public function getFromTuple( UserTuple $tuple ) {
		return $this->get( $tuple->wiki, $tuple->id, $tuple->ip );
	}

	/**
	 * Resolve all queued user ids to usernames for the given wiki
	 *
	 * @param string $wiki
	 */
	public function resolve( $wiki ) {
		if ( empty( $this->queued[$wiki] ) ) {
			return;
		}
		$queued = array_unique( $this->queued[$wiki] );
		if ( isset( $this->usernames[$wiki] ) ) {
			$queued = array_diff( $queued, $this->usernames[$wiki]->getAllKeys() );
		} else {
			$this->ensureLRU( $wiki );
		}

		$res = $this->query->execute( $wiki, $queued );
		unset( $this->queued[$wiki] );
		if ( $res ) {
			$usernames = [];
			foreach ( $res as $row ) {
				$id = (int)$row->user_id;
				$usernames[$id] = $row->user_name;
				$this->usernames[$wiki]->set( (string)$id, $row->user_name );
			}
			$this->resolveUserPages( $wiki, $usernames );
			$missing = array_diff( $queued, array_keys( $usernames ) );
		} else {
			$missing = $queued;
		}
		foreach ( $missing as $id ) {
			$this->usernames[$wiki]->set( (string)$id, false );
		}
	}

	/**
	 * Update in-process title existence cache with NS_USER and
	 * NS_USER_TALK pages related to the provided usernames.
	 *
	 * @param string $wiki Wiki the users belong to
	 * @param array $usernames List of user names
	 */
	protected function resolveUserPages( $wiki, array $usernames ) {
		// LinkBatch currently only supports the current wiki
		if ( $wiki !== wfWikiID() || !$usernames ) {
			return;
		}

		$lb = new \LinkBatch();
		foreach ( $usernames as $name ) {
			$user = User::newFromName( $name );
			if ( $user ) {
				$lb->addObj( $user->getUserPage() );
				$lb->addObj( $user->getTalkPage() );
			}
		}
		$lb->setCaller( __METHOD__ );
		$lb->execute();
	}
}
