<?php

namespace Flow\Model;

use Flow\Exception\CrossWikiException;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidDataException;
use User;

/**
 * Small value object holds the values necessary to uniquely identify
 * a user across multiple wiki's.
 */
class UserTuple {
	/**
	 * @var string The wiki the user belongs to
	 */
	public $wiki;

	/**
	 * @var int The id of the user, or 0 for anonymous
	 */
	public $id;

	/**
	 * @var string|null The ip of the user, null if logged in.
	 */
	public $ip;

	/**
	 * @param string $wiki The wiki the user belongs to
	 * @param int|string $id The id of the user, or 0 for anonymous
	 * @param string|null $ip The ip of the user, blank string for no ip.
	 *  null special case pass-through to be removed.
	 * @throws InvalidDataException
	 */
	public function __construct( $wiki, $id, $ip ) {
		if ( !is_int( $id ) ) {
			if ( ctype_digit( $id ) ) {
				$id = (int)$id;
			} else {
				throw new InvalidDataException( 'User id must be an integer' );
			}
		}
		if ( $id < 0 ) {
			throw new InvalidDataException( 'User id must be >= 0' );
		}
		if ( !$wiki ) {
			throw new InvalidDataException( 'No wiki provided' );
		}
		if ( $id === 0 && strlen( $ip ) === 0 ) {
			throw new InvalidDataException( 'User has no id and no ip' );
		}
		if ( $id !== 0 && strlen( $ip ) !== 0 ) {
			throw new InvalidDataException( 'User has both id and ip' );
		}
		// @todo assert ip is ipv4 or ipv6, but do we really want
		// that on every anon user we load from storage?

		$this->wiki = $wiki;
		$this->id = $id;
		$this->ip = (string)$ip ?: null;
	}

	public static function newFromUser( User $user ) {
		return new self(
			wfWikiID(),
			$user->getId(),
			$user->isAnon() ? $user->getName() : null
		);
	}

	public static function newFromArray( array $user, $prefix = '' ) {
		$wiki = "{$prefix}wiki";
		$id = "{$prefix}id";
		$ip = "{$prefix}ip";

		if (
			isset( $user[$wiki] )
			&& array_key_exists( $id, $user ) && array_key_exists( $ip, $user )
			// $user[$id] === 0 is special case when when IRC formatter mocks up objects
			&& ( $user[$id] || $user[$ip] || $user[$id] === 0 )
		) {
			return new self( $user["{$prefix}wiki"], $user["{$prefix}id"], $user["{$prefix}ip"] );
		} else {
			return null;
		}
	}

	public function toArray( $prefix = '' ) {
		return [
			"{$prefix}wiki" => $this->wiki,
			"{$prefix}id" => $this->id,
			"{$prefix}ip" => $this->ip
		];
	}

	public function createUser() {
		if ( $this->wiki !== wfWikiID() ) {
			throw new CrossWikiException( 'Can only retrieve same-wiki users' );
		}
		if ( $this->id ) {
			return User::newFromId( $this->id );
		} elseif ( !$this->ip ) {
			throw new FlowException( 'Either $userId or $userIp must be set.' );
		} else {
			return User::newFromName( $this->ip, /* $validate = */ false );
		}
	}
}
