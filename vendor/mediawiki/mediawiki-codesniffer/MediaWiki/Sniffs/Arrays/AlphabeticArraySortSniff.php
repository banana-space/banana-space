<?php

/**
 * Check if the keys of an array are sorted and autofix it.
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

namespace MediaWiki\Sniffs\Arrays;

use ArrayIterator;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class AlphabeticArraySortSniff implements Sniff {

	private const ANNOTATION_NAME = '@phpcs-require-sorted-array';

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_DOC_COMMENT_OPEN_TAG ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$end = $tokens[$stackPtr]['comment_closer'];
		foreach ( $tokens[$stackPtr]['comment_tags'] as $tag ) {
			if ( $tokens[$tag]['content'] === self::ANNOTATION_NAME ) {
				$this->processDocTag( $phpcsFile, $tokens, $tag, $end );
				break;
			}
		}
	}

	/**
	 * @param File $phpcsFile
	 * @param array[] $tokens
	 * @param int $tagPtr Token position of the tag
	 * @param int $docEnd Token position of the end of the doc comment
	 */
	private function processDocTag( File $phpcsFile, array $tokens, $tagPtr, $docEnd ) {
		$arrayToken = $phpcsFile->findNext( [ T_OPEN_SHORT_ARRAY, T_ARRAY ], $docEnd + 1 );
		if ( $arrayToken === false || (
			// On the same line or one line after the doc block
			$tokens[$docEnd]['line'] !== $tokens[$arrayToken]['line'] &&
			$tokens[$docEnd]['line'] !== $tokens[$arrayToken]['line'] - 1 )
		) {
			$phpcsFile->addWarning(
				'No array found after %s',
				$tagPtr,
				'Unsupported',
				[ self::ANNOTATION_NAME, $tokens[$arrayToken]['content'] ]
			);
			return;
		}

		if ( !isset( $tokens[$arrayToken]['bracket_opener'] ) ) {
			// Live coding
			return;
		}

		$endArray = $tokens[$arrayToken]['bracket_closer'] - 1;
		$startArray = $phpcsFile->findNext(
			Tokens::$emptyTokens,
			$tokens[$arrayToken]['bracket_opener'] + 1,
			$endArray,
			true
		);
		if ( $startArray === false ) {
			// Empty array
			return;
		}
		$endArray = $phpcsFile->findPrevious( Tokens::$emptyTokens, $endArray, $startArray, true );
		if ( $tokens[$endArray]['code'] === T_COMMA ) {
			// Ignore trailing commas
			$endArray -= 1;
		}

		$keys = [];
		$duplicateCounter = 0;
		$next = $startArray;
		while ( $next <= $endArray ) {
			$endStatement = $phpcsFile->findEndOfStatement( $next, [ T_DOUBLE_ARROW ] );
			if ( $endStatement >= $endArray ) {
				// Not going ahead on our own end
				$endStatement = $endArray;
				$endItem = $endArray;
			} else {
				// Do not track comma
				$endItem = $endStatement - 1;
			}
			$keyToken = $phpcsFile->findNext( Tokens::$emptyTokens, $next, $endItem + 1, true );

			$arrayKey = $tokens[$keyToken]['content'];
			if ( isset( $keys[$arrayKey] ) ) {
				$phpcsFile->addWarning(
					'Found duplicate key "%s" on array required sorting',
					$keyToken,
					'Duplicate',
					[ $arrayKey ]
				);
				$duplicateCounter++;
				// Make the key unique to get a stable sort result and to handle this token as well
				$arrayKey .= "\0" . $duplicateCounter;
			}

			$keys[$arrayKey] = [
				'key' => $keyToken,
				'end' => $endItem,
				'startLocation' => $next,
				'endLocation' => $endStatement,
			];
			$next = $endStatement + 1;
		}

		$sortedKeys = $this->sortStatements( $keys );
		if ( $sortedKeys === array_keys( $keys ) ) {
			return;
		}

		$fix = $phpcsFile->addFixableWarning(
			'Array is not sorted alphabetically',
			$tagPtr,
			'Unsorted'
		);

		if ( $fix ) {
			$this->rebuildSortedArray( $phpcsFile, $sortedKeys, $keys, $startArray );
		} else {
			$this->warnOnFirstMismatch( $phpcsFile, $sortedKeys, $keys );
		}
	}

	/**
	 * Add a warning on first mismatched key to make it easier found the wrong key in the array.
	 * On each key could make warning on all keys, when the first is already out of order
	 *
	 * @param File $phpcsFile
	 * @param string[] $sorted
	 * @param array[] $unsorted
	 */
	private function warnOnFirstMismatch( File $phpcsFile, $sorted, $unsorted ) {
		$iteratorUnsorted = new ArrayIterator( $unsorted );
		foreach ( $sorted as $sortedKey ) {
			$unsortedKey = $iteratorUnsorted->key();
			if ( $sortedKey !== $unsortedKey ) {
				$unsortedToken = $iteratorUnsorted->current();
				$phpcsFile->addFixableWarning(
					'This key is out of order (Needs %s, got %s)',
					$unsortedToken['key'],
					'UnsortedHint',
					[ $sortedKey, $unsortedKey ]
				);
				break;
			}
			$iteratorUnsorted->next();
		}
	}

	/**
	 * When autofix is wanted, rebuild the content of the array and use it
	 * Get the comma and line indents between each items from the current order.
	 * Add the key and values in sorted order.
	 *
	 * @param File $phpcsFile
	 * @param string[] $sorted
	 * @param array[] $unsorted
	 * @param int $stackPtr
	 */
	private function rebuildSortedArray( File $phpcsFile, $sorted, $unsorted, $stackPtr ) {
		$phpcsFile->fixer->beginChangeset();
		$iteratorSorted = new ArrayIterator( $sorted );
		$newArray = '';
		$lastEnd = false;
		foreach ( $unsorted as $values ) {
			// Add comma and indent between the items
			if ( $lastEnd !== false ) {
				$newArray .= $phpcsFile->getTokensAsString(
					$lastEnd + 1,
					$values['key'] - $lastEnd - 1
				);
			}
			$lastEnd = $values['end'];

			// Add the array item
			$sortedKey = $iteratorSorted->current();
			$unsortedToken = $unsorted[$sortedKey];
			$newArray .= $phpcsFile->getTokensAsString(
				$unsortedToken['key'],
				$unsortedToken['end'] - $unsortedToken['key'] + 1
			);
			$iteratorSorted->next();

			// remove at old location including comma and indent
			for ( $i = $unsortedToken['startLocation']; $i <= $unsortedToken['endLocation']; $i++ ) {
				$phpcsFile->fixer->replaceToken( $i, '' );
			}
		}
		$phpcsFile->fixer->addContent( $stackPtr, $newArray );
		$phpcsFile->fixer->endChangeset();
	}

	/**
	 * This sorts the array keys
	 *
	 * @param array[] $statementList Array mapping keys to tokens
	 * @return string[] Sorted list of keys
	 */
	private function sortStatements( array $statementList ) : array {
		$map = [];
		foreach ( $statementList as $key => $_ ) {
			$map[$key] = trim( $key, "'\"" );
		}
		natcasesort( $map );
		// @phan-suppress-next-line PhanTypeMismatchReturn False positive as array_keys can return list<string>
		return array_keys( $map );
	}

}
