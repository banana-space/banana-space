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
 *
 * @file
 */

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Enforce use of `?MyClass $x` instead of `MyClass $x = null`, which is (correctly)
 * misinterpreted as optional by IDEs and static analysis tools.
 * This is only done for nullable types followed by required parameters.
 * Note that we don't offer an autofix, because changing a signature should be verified carefully.
 */
class NullableTypeSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_FUNCTION ];
	}

	/**
	 * @param File $phpcsFile File
	 * @param int $stackPtr Location
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$params = $phpcsFile->getMethodParameters( $stackPtr );

		$found = $temp = [];
		foreach ( $params as $param ) {
			if (
				$param['type_hint'] &&
				$param['nullable_type'] === false &&
				array_key_exists( 'default', $param ) &&
				$param['default'] === 'null'
			) {
				$temp[] = $param;
			} elseif ( !array_key_exists( 'default', $param ) ) {
				$found = array_merge( $found, $temp );
				$temp = [];
			}
		}

		foreach ( $found as $param ) {
			$phpcsFile->addError(
				'Use PHP 7.1 syntax for nullable parameters ("?%s %s")',
				$param['token'],
				'PHP71NullableStyle',
				[ $param['type_hint'], $param['name'] ]
			);
		}
	}
}
