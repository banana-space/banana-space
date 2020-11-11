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
 * Class representing a two-factor key
 *
 * Keys can be tied to OATHUsers
 *
 * @ingroup Extensions
 */
class OATHAuthKey {
	/**
	 * Represents that a token corresponds to the main secret
	 * @see verifyToken
	 */
	const MAIN_TOKEN = 1;

	/**
	 * Represents that a token corresponds to a scratch token
	 * @see verifyToken
	 */
	const SCRATCH_TOKEN = -1;

	/** @var array Two factor binary secret */
	private $secret;

	/** @var string[] List of scratch tokens */
	private $scratchTokens;

	/**
	 * Make a new key from random values
	 *
	 * @return OATHAuthKey
	 */
	public static function newFromRandom() {
		$object = new self(
			Base32::encode( MWCryptRand::generate( 10, true ) ),
			[]
		);

		$object->regenerateScratchTokens();

		return $object;
	}

	/**
	 * @param string $secret
	 * @param array $scratchTokens
	 */
	public function __construct( $secret, array $scratchTokens ) {
		// Currently harcoded values; might be used in future
		$this->secret = [
			'mode' => 'hotp',
			'secret' => $secret,
			'period' => 30,
			'algorithm' => 'SHA1',
		];
		$this->scratchTokens = $scratchTokens;
	}

	/**
	 * @return string
	 */
	public function getSecret() {
		return $this->secret['secret'];
	}

	/**
	 * @return array
	 */
	public function getScratchTokens() {
		return $this->scratchTokens;
	}

	/**
	 * Verify a token against the secret or scratch tokens
	 *
	 * @param string $token Token to verify
	 * @param OATHUser $user
	 *
	 * @return int|false Returns a constant represent what type of token was matched,
	 *  or false for no match
	 */
	public function verifyToken( $token, OATHUser $user ) {
		global $wgOATHAuthWindowRadius;

		if ( $this->secret['mode'] !== 'hotp' ) {
			throw new \DomainException( 'OATHAuth extension does not support non-HOTP tokens' );
		}

		// Prevent replay attacks
		$memc = ObjectCache::newAnything( [] );
		$uid = CentralIdLookup::factory()->centralIdFromLocalUser( $user->getUser() );
		$memcKey = wfMemcKey( 'oathauth', 'usedtokens', $uid );
		$lastWindow = (int)$memc->get( $memcKey );

		$retval = false;
		$results = HOTP::generateByTimeWindow(
			Base32::decode( $this->secret['secret'] ),
			$this->secret['period'], -$wgOATHAuthWindowRadius, $wgOATHAuthWindowRadius
		);

		// Remove any whitespace from the received token, which can be an intended group seperator
		// or trimmeable whitespace
		$token = preg_replace( '/\s+/', '', $token );

		// Check to see if the user's given token is in the list of tokens generated
		// for the time window.
		foreach ( $results as $window => $result ) {
			if ( $window > $lastWindow && $result->toHOTP( 6 ) === $token ) {
				$lastWindow = $window;
				$retval = self::MAIN_TOKEN;
				break;
			}
		}

		// See if the user is using a scratch token
		if ( !$retval ) {
			$length = count( $this->scratchTokens );
			// Detect condition where all scratch tokens have been used
			if ( $length == 1 && "" === $this->scratchTokens[0] ) {
				$retval = false;
			} else {
				for ( $i = 0; $i < $length; $i++ ) {
					if ( $token === $this->scratchTokens[$i] ) {
						// If there is a scratch token, remove it from the scratch token list
						unset( $this->scratchTokens[$i] );
						$oathrepo = OATHAuthHooks::getOATHUserRepository();
						$user->setKey( $this );
						$oathrepo->persist( $user );
						// Only return true if we removed it from the database
						$retval = self::SCRATCH_TOKEN;
						break;
					}
				}
			}
		}

		if ( $retval ) {
			$memc->set(
				$memcKey,
				$lastWindow,
				$this->secret['period'] * ( 1 + 2 * $wgOATHAuthWindowRadius )
			);
		} else {
			// Increase rate limit counter for failed request
			$user->getUser()->pingLimiter( 'badoath' );
		}

		return $retval;
	}

	public function regenerateScratchTokens() {
		$scratchTokens = [];
		for ( $i = 0; $i < 5; $i++ ) {
			array_push( $scratchTokens, Base32::encode( MWCryptRand::generate( 10, true ) ) );
		}
		$this->scratchTokens = $scratchTokens;
	}

	/**
	 * Check if a token is one of the scratch tokens for this two factor key.
	 *
	 * @param string $token Token to verify
	 *
	 * @return bool true if this is a scratch token.
	 */
	public function isScratchToken( $token ) {
		$token = preg_replace( '/\s+/', '', $token );
		return in_array( $token, $this->scratchTokens, true );
	}
}
