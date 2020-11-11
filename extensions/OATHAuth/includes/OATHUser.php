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

/**
 * Class representing a user from OATH's perspective
 *
 * @ingroup Extensions
 */
class OATHUser {
	/** @var User */
	private $user;

	/** @var OATHAuthKey|null */
	private $key;

	/**
	 * Constructor. Can't be called directly. Use OATHUserRepository::findByUser instead.
	 * @param User $user
	 * @param OATHAuthKey|null $key
	 */
	public function __construct( User $user, OATHAuthKey $key = null ) {
		$this->user = $user;
		$this->key = $key;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * @return String
	 */
	public function getIssuer() {
		global $wgSitename, $wgOATHAuthAccountPrefix;
		if ( $wgOATHAuthAccountPrefix !== false ) {
			return $wgOATHAuthAccountPrefix;
		}
		return $wgSitename;
	}

	/**
	 * @return String
	 */
	public function getAccount() {
		return $this->user->getName();
	}

	/**
	 * Get the key associated with this user.
	 *
	 * @return null|OATHAuthKey
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Set the key associated with this user.
	 *
	 * @param OATHAuthKey|null $key
	 */
	public function setKey( OATHAuthKey $key = null ) {
		$this->key = $key;
	}
}
