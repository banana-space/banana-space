<?php
/**
 * Implements the Pbkdf2Password class for the MediaWiki software.
 *
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
 *
 * @file
 */

declare( strict_types = 1 );

/**
 * A PBKDF2-hashed password
 *
 * This is a computationally complex password hash for use in modern applications.
 * The number of rounds can be configured by $wgPasswordConfig['pbkdf2']['cost'].
 *
 * @since 1.24
 */
class Pbkdf2Password extends ParameterizedPassword {
	protected function getDefaultParams() : array {
		return [
			'algo' => $this->config['algo'],
			'rounds' => $this->config['cost'],
			'length' => $this->config['length']
		];
	}

	protected function getDelimiter() : string {
		return ':';
	}

	public function crypt( string $password ) : void {
		if ( count( $this->args ) == 0 ) {
			$this->args[] = base64_encode( random_bytes( 16 ) );
		}

		try {
			$hash = hash_pbkdf2(
				$this->params['algo'],
				$password,
				base64_decode( $this->args[0] ),
				(int)$this->params['rounds'],
				(int)$this->params['length'],
				true
			);

			// PHP < 8 raises a warning in case of an error, such as unknown algorithm...
			if ( !is_string( $hash ) ) {
				throw new PasswordError( 'Error when hashing password.' );
			}
		} catch ( ValueError $e ) {
			// ...while PHP 8 throws ValueError
			throw new PasswordError( 'Error when hashing password.', 0, $e );
		}

		$this->hash = base64_encode( $hash );
	}
}
