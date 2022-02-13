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
 * Disallows usage of &$this, which results in
 * warnings since PHP 7.1
 */
class ReferenceThisSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		// As per https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#Other
		return [
			T_BITWISE_AND
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		if ( !isset( $tokens[$stackPtr + 1] ) ) {
			// Syntax error or live coding, bow out.
			return;
		}

		$next = $tokens[$stackPtr + 1];
		if ( $next['code'] === T_VARIABLE && $next['content'] === '$this' ) {
			$after = $phpcsFile->findNext( T_WHITESPACE, $stackPtr + 2, null, true );
			if ( $after !== false &&
				in_array(
					$tokens[$after]['code'],
					[ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_OPEN_SQUARE_BRACKET, T_DOUBLE_COLON ]
				)
			) {
				return;
			}
			$phpcsFile->addError(
				'The ampersand in "&$this" must be removed. If you plan to get back another ' .
					'instance of this class, assign $this to a temporary variable.',
				$stackPtr,
				'Found'
			);
		}
	}
}
