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
use PHP_CodeSniffer\Util\Tokens;

class NestedInlineTernarySniff implements Sniff {

	/**
	 * Tokens that can end an inline ternary statement.
	 *
	 * @var array
	 */
	private $endTokens = [];

	/**
	 * @inheritDoc
	 */
	public function register() {
		$this->endTokens = Tokens::$assignmentTokens + Tokens::$includeTokens + [
			// Operators having a lower precedence than the ternary operator,
			// or left associative operators having the same precedence, can
			// end inline ternary statements. This includes all assignment and
			// include statements.
			//
			// In the PHP source code, the order of precedence can be found
			// in the file Zend/zend_language_parser.y. To find the ternary
			// operator in the list, search for "%left '?' ':'".
			T_INLINE_THEN => T_INLINE_THEN,
			T_INLINE_ELSE => T_INLINE_ELSE,
			T_YIELD_FROM => T_YIELD_FROM,
			T_YIELD => T_YIELD,
			T_PRINT => T_PRINT,
			T_LOGICAL_AND => T_LOGICAL_AND,
			T_LOGICAL_XOR => T_LOGICAL_XOR,
			T_LOGICAL_OR => T_LOGICAL_OR,

			// Obviously, right brackets, right parentheses, commas, colons,
			// and semicolons can also end inline ternary statements. There is
			// a list of corresponding tokens in File::findEndOfStatement(),
			// which we duplicate here.
			T_COLON => T_COLON,
			T_COMMA => T_COMMA,
			T_SEMICOLON => T_SEMICOLON,
			T_CLOSE_PARENTHESIS => T_CLOSE_PARENTHESIS,
			T_CLOSE_SQUARE_BRACKET => T_CLOSE_SQUARE_BRACKET,
			T_CLOSE_CURLY_BRACKET => T_CLOSE_CURLY_BRACKET,
			T_CLOSE_SHORT_ARRAY => T_CLOSE_SHORT_ARRAY,

			// Less obviously, a foreach loop's array_expression can be
			// an inline ternary statement, and would be followed by "as".
			T_AS => T_AS,
		];

		return [ T_INLINE_THEN ];
	}

	/**
	 * @param File $phpcsFile File
	 * @param int $stackPtr Location
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$elsePtr = null;
		$thenNestingLevel = 1;
		$thenNestedPtr = null;
		$elseNestedPtr = null;
		for ( $i = $stackPtr + 1; $i < $phpcsFile->numTokens; ++$i ) {
			// Skip bracketed and parenthesized subexpressions.
			$inBrackets = isset( $tokens[$i]['bracket_closer'] );
			if ( $inBrackets && $tokens[$i]['bracket_opener'] === $i ) {
				$i = $tokens[$i]['bracket_closer'];
				continue;
			}
			$inParentheses = isset( $tokens[$i]['parenthesis_closer'] );
			if ( $inParentheses && $tokens[$i]['parenthesis_opener'] === $i ) {
				$i = $tokens[$i]['parenthesis_closer'];
				continue;
			}

			if ( $elsePtr === null ) {
				// In the "then" part of the inline ternary statement:
				if ( $tokens[$i]['code'] === T_INLINE_THEN ) {
					// Let $thenNestedPtr point to the T_INLINE_THEN token
					// of the outermost inline ternary statement forming the
					// "then" part of the current inline ternary statement.
					// Example: $a ? $b ? $c ? $d : $e : $f : $g
					// -           ^ stackPtr
					// -                ^ thenNestedPtr
					if ( ++$thenNestingLevel === 2 ) {
						$thenNestedPtr = $i;
					}
				} elseif ( $tokens[$i]['code'] === T_INLINE_ELSE ) {
					// Let $elsePtr point to the T_INLINE_ELSE token of the
					// current inline ternary statement. See below example.
					if ( --$thenNestingLevel === 0 ) {
						$elsePtr = $i;
					}
				}
				// Strictly speaking, checking if the entire "then" part
				// is an inline ternary statement would involve checking the
				// token, whenever $thenNestingLevel is 1, against the
				// list of operators of lower precedence.
				//
				// However, we omit this check in order to allow additional
				// cases to be flagged as needing parentheses for clarity.

			} else {
				// In the "else" part of the inline ternary statement:
				if ( isset( $this->endTokens[$tokens[$i]['code']] ) ) {
					if ( $tokens[$i]['code'] === T_INLINE_THEN ) {
						// Let $elseNestedPtr point to the T_INLINE_THEN token
						// of the inline ternary statement having the current
						// inline ternary statement as its "if" part.
						// Example: $a ? $b : $c ? $d : $e ? $f : $g
						// -           ^ stackPtr
						// -                ^ elsePtr
						// -                     ^ elseNestedPtr
						$elseNestedPtr = $i;
					}
					break;
				}
			}
		}

		// The "then" part of the current inline ternary statement should not
		// be another inline ternary statement, unless that other inline
		// ternary statement is parenthesized.
		if ( $thenNestedPtr !== null && $elsePtr !== null ) {
			$fix = $phpcsFile->addFixableWarning(
				'Nested inline ternary statements can be difficult to read without parentheses',
				$thenNestedPtr,
				'UnparenthesizedThen'
			);
			if ( $fix ) {
				$phpcsFile->fixer->beginChangeset();
				$phpcsFile->fixer->addContent( $stackPtr, ' (' );
				$phpcsFile->fixer->addContentBefore( $elsePtr, ') ' );
				$phpcsFile->fixer->endChangeset();
			}
		}

		// The current inline ternary statement must not be the "if" part of
		// another inline ternary statement, unless the current inline
		// ternary statement is parenthesized.
		if ( $elseNestedPtr !== null && !(
			// Exception: Stacking is permitted when only the short form of
			// the ternary operator is used. In this case, the operator's
			// left associativity is unlikely to matter.
			$this->isShortTernary( $phpcsFile, $stackPtr ) &&
			$this->isShortTernary( $phpcsFile, $elseNestedPtr )
		) ) {
			// Report this violation as an error, because it looks like a bug.
			// For the same reason, don't offer to fix it automatically.
			$phpcsFile->addError(
				'Nested inline ternary statements, in PHP, may not behave as you intend ' .
				'without parentheses',
				$stackPtr,
				'UnparenthesizedTernary'
			);
		}
	}

	/**
	 * @param File $phpcsFile File
	 * @param int $i Location of T_INLINE_THEN
	 * @return bool
	 */
	private function isShortTernary( File $phpcsFile, $i ) {
		$tokens = $phpcsFile->getTokens();
		$i = $phpcsFile->findNext( Tokens::$emptyTokens, $i + 1, null, true );
		return $i !== false && $tokens[$i]['code'] === T_INLINE_ELSE;
	}

}
