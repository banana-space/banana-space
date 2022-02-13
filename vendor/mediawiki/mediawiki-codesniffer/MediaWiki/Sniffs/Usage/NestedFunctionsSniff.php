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

class NestedFunctionsSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_FUNCTION ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$functionName = $phpcsFile->getDeclarationName( $stackPtr );
		if ( $functionName === false ) {
			return;
		}

		// Walk through parent scopes, looking for functions/closures that are not in an
		// anonymous class
		if (
			$phpcsFile->hasCondition( $stackPtr, [ T_FUNCTION, T_CLOSURE ] ) &&
			!$phpcsFile->hasCondition( $stackPtr, [ T_ANON_CLASS ] )
		) {
			$error = 'Function %s is nested inside of another function or closure';
			$phpcsFile->addError( $error, $stackPtr, 'NestedFunction', [ $functionName ] );
		}
	}
}
