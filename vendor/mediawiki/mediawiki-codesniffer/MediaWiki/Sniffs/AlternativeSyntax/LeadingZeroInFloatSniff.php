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

class LeadingZeroInFloatSniff implements Sniff {

	/**
	 * T_DNUMBER is any floating point number
	 *
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_DNUMBER,
		];
	}

	/**
	 * If the float starts with a period, it needs
	 * a zero in front
	 *
	 * @param File $phpcsFile File
	 * @param int $stackPtr Location
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$content = $tokens[$stackPtr]['content'];
		if ( $content[0] === '.' ) {
			// Starts with a ., needs a leading 0.
			$fix = $phpcsFile->addFixableWarning(
				'Floats should have a leading 0',
				$stackPtr,
				'Found'
			);
			if ( $fix ) {
				$phpcsFile->fixer->addContentBefore( $stackPtr, '0' );
			}
		}
	}

}
