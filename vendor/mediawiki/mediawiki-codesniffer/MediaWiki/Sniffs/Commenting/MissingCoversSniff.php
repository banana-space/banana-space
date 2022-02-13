<?php
/**
 * Copyright (C) 2015 WordPoints
 * Copyright (C) 2018 Kunal Mehta <legoktm@member.fsf.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Identify Test classes that do not have
 * any @covers tags
 */
class MissingCoversSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_CLASS ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr Position of T_CLASS
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$name = $phpcsFile->getDeclarationName( $stackPtr );
		if ( substr( $name, -4 ) !== 'Test' ) {
			// Only want to validate classes that end in test
			return;
		}
		$props = $phpcsFile->getClassProperties( $stackPtr );
		if ( $props['is_abstract'] ) {
			// No point in requiring @covers from an abstract class
			return;
		}

		$classCovers = $this->hasCoversTags( $phpcsFile, $stackPtr );
		if ( $classCovers ) {
			// The class has a @covers tag, awesome.
			return;
		}

		// Check each individual test function
		$tokens = $phpcsFile->getTokens();
		$classCloser = $tokens[$stackPtr]['scope_closer'];
		$funcPtr = $stackPtr;
		while ( true ) {
			$funcPtr = $phpcsFile->findNext( [ T_FUNCTION ], $funcPtr + 1, $classCloser );
			if ( !$funcPtr ) {
				// No more
				break;
			}

			$name = $phpcsFile->getDeclarationName( $funcPtr );
			if ( substr( $name, 0, 4 ) !== 'test' ) {
				// If it doesn't start with "test", skip
				continue;
			}

			$hasCovers = $this->hasCoversTags( $phpcsFile, $funcPtr );
			if ( !$hasCovers ) {
				$phpcsFile->addWarning(
					'The %s test method has no @covers tags',
					$funcPtr, 'MissingCovers', [ $name ]
				);
			}
		}
	}

	/**
	 * Whether the statement has @covers tags
	 *
	 * @param File $phpcsFile
	 * @param int $stackPtr Position of T_CLASS/T_FUNCTION
	 *
	 * @return bool
	 */
	protected function hasCoversTags( File $phpcsFile, $stackPtr ) {
		$exclude = array_merge(
			Tokens::$methodPrefixes,
			[ T_WHITESPACE ]
		);
		$closer = $phpcsFile->findPrevious( $exclude, $stackPtr - 1, 0, true );
		if ( $closer === false ) {
			return false;
		}
		$tokens = $phpcsFile->getTokens();
		$token = $tokens[$closer];
		if ( $token['code'] !== T_DOC_COMMENT_CLOSE_TAG ) {
			// No doc comment
			return false;
		}

		$opener = $tokens[$closer]['comment_opener'];
		$tags = $tokens[$opener]['comment_tags'];
		foreach ( $tags as $tag ) {
			$name = $tokens[$tag]['content'];
			if ( $name === '@covers' || $name === '@coversNothing' ) {
				return true;
			}
		}

		return false;
	}

}
