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

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Check for tags with nothing after them
 *
 * @author DannyS712
 */
class EmptyTagSniff implements Sniff {

	private const MSG_MAP = [
		T_FUNCTION => 'function',
		T_VARIABLE => 'property'
	];

	private const DISALLOWED_EMPTY_TAGS = [
		'@see' => '@see'
	];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return array_keys( self::MSG_MAP );
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		switch ( $tokens[$stackPtr]['code'] ) {
			case T_FUNCTION:
				$find = Tokens::$methodPrefixes;
				$find[] = T_WHITESPACE;
				break;
			case T_VARIABLE:
				// Only for class properties
				$scopes = array_keys( $tokens[$stackPtr]['conditions'] );
				$scope = array_pop( $scopes );
				if ( isset( $tokens[$stackPtr]['nested_parenthesis'] )
					|| $scope === null
					|| ( $tokens[$scope]['code'] !== T_CLASS && $tokens[$scope]['code'] !== T_TRAIT )
				) {
					return;
				}

				$find = Tokens::$scopeModifiers;
				$find[] = T_WHITESPACE;
				$find[] = T_STATIC;
				$find[] = T_VAR;
				$find[] = T_NULLABLE;
				$find[] = T_STRING;
				break;
			default:
				throw new \LogicException( "Unhandled case " . $tokens[$stackPtr]['code'] );
		}
		$commentEnd = $phpcsFile->findPrevious( $find, $stackPtr - 1, null, true );
		if ( $tokens[$commentEnd]['code'] === T_COMMENT ) {
			// Inline comments might just be closing comments for
			// control structures or functions/properties instead of function/properties comments
			// using the wrong comment type. If there is other code on the line,
			// assume they relate to that code.
			$prev = $phpcsFile->findPrevious( $find, $commentEnd - 1, null, true );
			if ( $prev !== false && $tokens[$prev]['line'] === $tokens[$commentEnd]['line'] ) {
				$commentEnd = $prev;
			}
		}

		if ( $tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG ) {
			// Not multiline documentation, won't have @see tags
			return;
		}

		$commentStart = $tokens[$commentEnd]['comment_opener'];

		foreach ( $tokens[$commentStart]['comment_tags'] as $tag ) {
			$tagText = $tokens[$tag]['content'];
			if ( isset( self::DISALLOWED_EMPTY_TAGS[$tagText] ) ) {
				// Make sure the tag isn't empty.
				$string = $phpcsFile->findNext( T_DOC_COMMENT_STRING, $tag, $commentEnd );
				if ( $string === false || $tokens[$string]['line'] !== $tokens[$tag]['line'] ) {
					$phpcsFile->addError(
						'Content missing for %s tag in %s comment',
						$tag,
						ucfirst( self::MSG_MAP[$tokens[$stackPtr]['code']] ) . ucfirst( substr( $tagText, 1 ) ),
						[ $tagText, self::MSG_MAP[$tokens[$stackPtr]['code']] ]
					);
				}
			}
		}
	}

}
