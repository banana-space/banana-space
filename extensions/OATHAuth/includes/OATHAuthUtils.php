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
 * Utility class for various OATH functions
 *
 * @ingroup Extensions
 */
class OATHAuthUtils {
	/**
	 * Check whether OATH two-factor authentication is enabled for a given user.
	 * This is a stable method that does not change and can be used in other extensions.
	 * @param User $user
	 * @return bool
	 */
	public static function isEnabledFor( User $user ) {
		$oathUser = OATHAuthHooks::getOATHUserRepository()->findByUser( $user );
		return $oathUser && $oathUser->getKey();
	}

	/**
	 * Encrypt an aray of variables to put into the user's session. We use this
	 * when storing the user's password in their session. We can use json as the
	 * serialization format because $plaintextVars is an array of strings.
	 * @param array $plaintextVars array of user input strings
	 * @param int $userId passed to key derivation functions so each user uses
	 * 	distinct encryption and hmac keys
	 * @return string encrypted data packet
	 */
	public static function encryptSessionData( array $plaintextVars, $userId ) {
		$keyMaterial = self::getKeyMaterials();
		$keys = self::getUserKeys( $keyMaterial, $userId );
		return self::seal( json_encode( $plaintextVars ), $keys['encrypt'], $keys['hmac'] );
	}

	/**
	 * Decrypt an encrypted packet, generated with encryptSessionData
	 * @param string $ciphertext Encrypted data packet
	 * @param string|int $userId
	 * @return array of strings
	 */
	public static function decryptSessionData( $ciphertext, $userId ) {
		$keyMaterial = self::getKeyMaterials();
		$keys = self::getUserKeys( $keyMaterial, $userId );
		return json_decode( self::unseal( $ciphertext, $keys['encrypt'], $keys['hmac'] ), true );
	}

	/**
	 * Get the base secret for this wiki, used to derive all of the encryption
	 * keys. When $wgOATHAuthSecret is rotated, users who are part way through the
	 * two-step login will get an exception, and have to re-start the login.
	 * @return string
	 */
	private static function getKeyMaterials() {
		global $wgOATHAuthSecret, $wgSecretKey;
		return $wgOATHAuthSecret ?: $wgSecretKey;
	}

	/**
	 * Generate encryption and hmac keys, unique to this user, based on a single
	 * wiki secret. Use a moderate pbkdf2 work factor in case we ever leak keys.
	 * @param string $secret
	 * @param string|int $userid
	 * @return array including key for encryption and integrity checking
	 */
	private static function getUserKeys( $secret, $userid ) {
		$keymats = hash_pbkdf2( 'sha256', $secret, "oath-$userid", 10001, 64, true );
		return [
			'encrypt' => substr( $keymats, 0, 32 ),
			'hmac' => substr( $keymats, 32, 32 ),
		];
	}

	/**
	 * Actually encrypt the data, using a new random IV, and prepend the hmac
	 * of the encrypted data + IV, using a separate hmac key.
	 * @param string $data
	 * @param string $encKey
	 * @param string $hmacKey
	 * @return string $hmac.$iv.$ciphertext, each component b64 encoded
	 */
	private static function seal( $data, $encKey, $hmacKey ) {
		$iv = MWCryptRand::generate( 16, true );
		$ciphertext = openssl_encrypt(
			$data,
			'aes-256-ctr',
			$encKey,
			OPENSSL_RAW_DATA,
			$iv
		);
		$sealed = base64_encode( $iv ) . '.' . base64_encode( $ciphertext );
		$hmac = hash_hmac( 'sha256', $sealed, $hmacKey, true );
		return base64_encode( $hmac ) . '.' . $sealed;
	}

	/**
	 * Decrypt data sealed using seal(). First checks the hmac to prevent various
	 * attacks.
	 * @param string $encrypted
	 * @param string $encKey
	 * @param string $hmacKey
	 * @return string plaintext
	 * @throws Exception
	 */
	private static function unseal( $encrypted, $encKey, $hmacKey ) {
		$pieces = explode( '.', $encrypted );
		if ( count( $pieces ) !== 3 ) {
			throw new InvalidArgumentException( 'Invalid sealed-secret format' );
		}

		list( $hmac, $iv, $ciphertext ) = $pieces;
		$integCalc = hash_hmac( 'sha256', $iv . '.' . $ciphertext, $hmacKey, true );
		if ( !hash_equals( $integCalc, base64_decode( $hmac ) ) ) {
			throw new Exception( 'Sealed secret has been tampered with, aborting.' );
		}

		return openssl_decrypt(
			base64_decode( $ciphertext ),
			'aes-256-ctr',
			$encKey,
			OPENSSL_RAW_DATA,
			base64_decode( $iv )
		);
	}

}
