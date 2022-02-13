<?php

/**
 * Report error when __METHOD__ is used in closures,
 * because it does not reporting the correct method used in logs
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
use PHP_CodeSniffer\Sniffs\AbstractScopeSniff;

class MagicConstantClosureSniff extends AbstractScopeSniff {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct(
			[ T_CLOSURE ],
			// https://php.net/manual/en/language.constants.predefined.php
			// T_CLASS_C, T_TRAIT_C and T_NS_C works in closures
			// ::class also works in closures
			[ T_METHOD_C, T_FUNC_C ]
		);
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @param int $currScope The position of the current scope.
	 * @return void
	 * @suppress PhanUnusedProtectedMethodParameter Inherit from parent class
	 */
	protected function processTokenWithinScope( File $phpcsFile, $stackPtr, $currScope ) {
		$tokens = $phpcsFile->getTokens();
		$constant = $tokens[$stackPtr]['content'];
		$phpcsFile->addWarning(
			'Avoid use of %s magic constant in closure',
			$stackPtr,
			$this->createSniffCode( 'FoundConstant', $constant ),
			[ $constant ]
		);
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 * @suppress PhanUnusedProtectedMethodParameter Inherit from parent class
	 */
	protected function processTokenOutsideScope( File $phpcsFile, $stackPtr ) {
	}

	/**
	 * @param string $prefix
	 * @param string $constant
	 *
	 * @return string
	 */
	private function createSniffCode( $prefix, $constant ) {
		return $prefix . ucfirst( strtolower( trim( $constant, '_' ) ) );
	}
}
