<?php
/**
 * Provide usernames filtered by per-wiki ipblocks. Batches together
 * database requests for multiple usernames when possible.
 */
namespace Flow\Repository\UserName;

use Wikimedia\Rdbms\IResultWrapper;

/**
 * Classes implementing the interface can lookup
 * user names based on wiki + id
 */
interface UserNameQuery {
	/**
	 * @param string $wiki wiki id
	 * @param array $userIds List of user ids to lookup
	 * @return IResultWrapper|bool Containing objects with user_id and
	 *   user_name properies.
	 */
	public function execute( $wiki, array $userIds );
}
