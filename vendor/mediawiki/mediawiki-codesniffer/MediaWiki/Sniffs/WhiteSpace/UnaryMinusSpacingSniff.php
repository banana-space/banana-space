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

namespace MediaWiki\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Sniff to warn when there is a space after a unary minus operator
 *
 * @author DannyS712
 */
class UnaryMinusSpacingSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_MINUS
		];
	}

	/**
	 * Tokens that can preceed subtraction rather than unary minus
	 *
	 * Comment tokens are accessed at runtime
	 */
	private const TOKENS_BEFORE_SUBTRACTION = [
		T_CLOSE_PARENTHESIS,
		T_CLOSE_SQUARE_BRACKET,
		T_LNUMBER,
		T_DNUMBER,
		T_VARIABLE,
		T_STRING,
		T_CONSTANT_ENCAPSED_STRING,
		T_TRUE,
		T_FALSE,
		T_NULL,
	];

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Check if the minus is followed by a space
		$nextToken = $tokens[$stackPtr + 1];
		if ( $nextToken['code'] !== T_WHITESPACE ) {
			return;
		}

		// Find the last non-whitespace token
		$lastToken = $phpcsFile->findPrevious( T_WHITESPACE, $stackPtr - 1, null, true );

		$tokensBeforeSubtraction = array_merge(
			self::TOKENS_BEFORE_SUBTRACTION,
			array_values( Tokens::$commentTokens )
		);
		if ( in_array( $tokens[$lastToken]['code'], $tokensBeforeSubtraction ) ) {
			// Not a unary minus
			return;
		}

		// This is a unary minus, remove the space
		$fix = $phpcsFile->addFixableWarning(
			'Do not use a space after a unary minus',
			$stackPtr + 1,
			'SpaceFound'
		);

		if ( $fix ) {
			$phpcsFile->fixer->replaceToken( $stackPtr + 1, '' );
		}
	}
}
