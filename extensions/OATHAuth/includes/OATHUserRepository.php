<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

use Wikimedia\Rdbms\LoadBalancer;
use Wikimedia\Rdbms\DBConnRef;

class OATHUserRepository {
	/** @var LoadBalancer */
	protected $lb;

	/** @var BagOStuff */
	protected $cache;

	/**
	 * OATHUserRepository constructor.
	 * @param LoadBalancer $lb
	 * @param BagOStuff $cache
	 */
	public function __construct( LoadBalancer $lb, BagOStuff $cache ) {
		$this->lb = $lb;
		$this->cache = $cache;
	}

	/**
	 * @param User $user
	 * @return OATHUser
	 */
	public function findByUser( User $user ) {
		$oathUser = $this->cache->get( $user->getName() );
		if ( !$oathUser ) {
			$oathUser = new OATHUser( $user, null );

			$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
			$res = $this->getDB( DB_REPLICA )->selectRow(
				'oathauth_users',
				'*',
				[ 'id' => $uid ],
				__METHOD__
			);
			if ( $res ) {
				$key = new OATHAuthKey( $res->secret, explode( ',', $res->scratch_tokens ) );
				$oathUser->setKey( $key );
			}

			$this->cache->set( $user->getName(), $oathUser );
		}
		return $oathUser;
	}

	/**
	 * @param OATHUser $user
	 */
	public function persist( OATHUser $user ) {
		$this->getDB( DB_MASTER )->replace(
			'oathauth_users',
			[ 'id' ],
			[
				'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ),
				'secret' => $user->getKey()->getSecret(),
				'scratch_tokens' => implode( ',', $user->getKey()->getScratchTokens() ),
			],
			__METHOD__
		);
		$this->cache->set( $user->getUser()->getName(), $user );
	}

	/**
	 * @param OATHUser $user
	 */
	public function remove( OATHUser $user ) {
		$this->getDB( DB_MASTER )->delete(
			'oathauth_users',
			[ 'id' => CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() ) ],
			__METHOD__
		);
		$this->cache->delete( $user->getUser()->getName() );
	}

	/**
	 * @param integer $index DB_MASTER/DB_REPLICA
	 * @return DBConnRef
	 */
	private function getDB( $index ) {
		global $wgOATHAuthDatabase;

		return $this->lb->getConnectionRef( $index, [], $wgOATHAuthDatabase );
	}
}
