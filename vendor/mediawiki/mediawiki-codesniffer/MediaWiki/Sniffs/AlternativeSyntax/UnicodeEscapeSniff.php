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

namespace MediaWiki\Sniffs\AlternativeSyntax;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class UnicodeEscapeSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_CONSTANT_ENCAPSED_STRING,
			T_DOUBLE_QUOTED_STRING,
			T_START_HEREDOC,
		];
	}

	/**
	 * @param File $phpcsFile File
	 * @param int $stackPtr Location
	 * @return int
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Find the end of the string.
		$endPtr = $phpcsFile->findNext(
			/* types */ [ $tokens[$stackPtr]['code'], T_HEREDOC, T_END_HEREDOC ],
			/* start */ $stackPtr + 1,
			/* end */ null,
			/* exclude */ true
		) ?: $phpcsFile->numTokens;

		if ( $tokens[$endPtr - 1]['code'] === T_END_HEREDOC ) {
			if ( isset( $tokens[$endPtr] ) && $tokens[$endPtr]['code'] === T_SEMICOLON ) {
				++$endPtr;
			}
			if ( isset( $tokens[$endPtr] ) && $tokens[$endPtr]['code'] === T_WHITESPACE ) {
				++$endPtr;
			}
		}

		// If this is a single-quoted string, skip it.
		if ( $tokens[$stackPtr]['code'] === T_CONSTANT_ENCAPSED_STRING &&
			$tokens[$stackPtr]['content'][0] === "'"
		) {
			return $endPtr;
		}

		// If the string takes up multiple lines, PHP_CodeSniffer would
		// have split some of its tokens. Recombine the string's tokens
		// so the next step will work.
		$content = $phpcsFile->getTokensAsString( $stackPtr, $endPtr - $stackPtr );

		// If the string contains braced expressions, PHP_CodeSniffer
		// would have combined these and surrounding tokens, which could
		// lead to false matches. Avoid this by retokenizing the string.
		$origTokens = token_get_all( '<?php ' . $content );
		$warn = false;
		$content = '';
		foreach ( $origTokens as $i => $origToken ) {
			// Skip the PHP opening tag we added.
			if ( $i === 0 ) {
				continue;
			}

			// Don't check tokens that cannot contain escape sequences.
			$origToken = (array)$origToken;
			if ( !(
				$origToken[0] === T_ENCAPSED_AND_WHITESPACE ||
				$origToken[0] === T_CONSTANT_ENCAPSED_STRING && $origToken[1][0] !== "'"
			) ) {
				$content .= $origToken[1] ?? $origToken[0];
				continue;
			}

			// Check for Unicode escape sequences in the token, explicitly
			// skipping escaped backslashes to prevent false matches.
			$content .= preg_replace_callback(
				'/\\\\(?:u\{([0-9A-Fa-f]+)\}|\\\\(*SKIP)(*FAIL))/',
				function ( array $m ) use ( &$warn ) {
					// Decode the codepoint-digits.
					$cp = hexdec( $m[1] );
					if ( $cp > 0x10FFFF ) {
						// This is a parse error. Don't offer to fix it.
						return $m[0];
					}

					// Check the codepoint-digits against the expected format.
					$hex = sprintf( '%04X', $cp );
					if ( $m[1] === $hex ) {
						// Keep the conforming escape sequence as-is.
						return $m[0];
					}

					// Print a warning for the token containing the nonconforming
					// escape sequence and replace it with a conforming one.
					$warn = true;
					return '\u{' . $hex . '}';
				},
				$origToken[1]
			);
		}

		if ( $warn ) {
			$fix = $phpcsFile->addFixableWarning(
				'Unicode code points should be expressed using four to six uppercase hex ' .
				'digits, with leading zeros used only as necessary for \u{0FFF} and below',
				$stackPtr,
				'DigitsNotNormalized'
			);
			if ( $fix ) {
				$phpcsFile->fixer->beginChangeset();
				$phpcsFile->fixer->replaceToken( $stackPtr, $content );
				for ( $i = $stackPtr + 1; $i < $endPtr; ++$i ) {
					$phpcsFile->fixer->replaceToken( $i, '' );
				}
				$phpcsFile->fixer->endChangeset();
			}
		}

		return $endPtr;
	}

}
