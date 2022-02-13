<?php
/**
 * Report when !! is used instead of (bool).
 * Ignores !! when it is near instanceof, as the change in operator precedence can cause issues.
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

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DoubleNotOperatorSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() : array {
		return [ T_BOOLEAN_NOT ];
	}

	/**
	 * Called when one of the token types that this sniff is listening for is found.
	 *
	 * @param File $phpcsFile The PHP_CodeSniffer file where the token was found.
	 * @param int $stackPtr The position in the PHP_CodeSniffer file's token stack.
	 *
	 * @return int|null Stack pointer to continue. Null continues directly after $stackPtr.
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$nextToken = $phpcsFile->findNext(
			Tokens::$emptyTokens,
			$stackPtr + 1,
			null,
			true
		);

		if ( $tokens[$nextToken]['code'] !== T_BOOLEAN_NOT ) {
			return null;
		}

		// Limit searching for an `instanceof` token to the current scope
		if ( isset( $tokens[$stackPtr]['nested_parenthesis'] ) ) {
			$scopeCloser = end( $tokens[$stackPtr]['nested_parenthesis'] );
		} else {
			$scopeCloser = $phpcsFile->findEndOfStatement( $nextToken + 1 );
		}

		// The trivial auto-fix below would break code like `!!$obj instanceof`.
		if ( $phpcsFile->findNext( T_INSTANCEOF, $nextToken + 1, $scopeCloser ) ) {
			$phpcsFile->addWarning(
				'Use (bool) instead of !!, possibly with extra parenthesis due to operator precedence',
				$stackPtr,
				'DoubleNotOperatorParenthesis'
			);
			return $nextToken + 1;
		}

		$fix = $phpcsFile->addFixableWarning(
			'Use (bool) instead of !!',
			$stackPtr,
			'DoubleNotOperator'
		);
		if ( $fix ) {
			$phpcsFile->fixer->beginChangeset();

			do {
				$phpcsFile->fixer->replaceToken( $stackPtr, '' );
				$stackPtr++;
			} while ( $stackPtr < $nextToken );

			$phpcsFile->fixer->replaceToken( $nextToken, '(bool)' );

			$phpcsFile->fixer->endChangeset();
		}

		// Skip beyond the operator to prevent the sniff triggering twice on the same spot.
		return $nextToken + 1;
	}
}
