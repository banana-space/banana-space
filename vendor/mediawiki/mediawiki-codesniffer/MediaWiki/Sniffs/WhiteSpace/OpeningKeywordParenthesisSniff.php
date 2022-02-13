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

class OpeningKeywordParenthesisSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_EMPTY,
			T_EVAL,
			T_EXIT,
			T_ISSET,
			T_LIST,
			T_UNSET,
			// also check for array(), when not replaced with short syntax
			T_ARRAY,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr Index of registered keywords
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$next = $stackPtr + 1;

		$openParenthesis = $phpcsFile->findNext( T_WHITESPACE, $next, null, true );
		if ( $openParenthesis === false ||
			$tokens[$openParenthesis]['code'] !== T_OPEN_PARENTHESIS
		) {
			// no parenthesis found
			return;
		}

		if ( $next === $openParenthesis ) {
			// no whitespaces found
			return;
		}

		$whitespaces = $phpcsFile->getTokensAsString( $next, $openParenthesis - $next );
		$fix = $phpcsFile->addFixableError(
			'Expected no space before opening parenthesis; found %s',
			$openParenthesis,
			'WrongWhitespaceBeforeParenthesis',
			[ strlen( $whitespaces ) ]
		);
		if ( $fix ) {
			$phpcsFile->fixer->beginChangeset();
			for ( $i = $next; $i < $openParenthesis; $i++ ) {
				$phpcsFile->fixer->replaceToken( $i, '' );
			}
			$phpcsFile->fixer->endChangeset();
		}
	}
}
