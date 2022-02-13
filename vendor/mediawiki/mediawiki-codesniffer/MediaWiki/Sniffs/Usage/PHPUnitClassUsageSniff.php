<?php
/**
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

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Converts PHPUnit_Framework_TestCase to the new
 * PHPUnit 6 namespaced PHPUnit\Framework\Testcase
 *
 * The namespaced classes were backported in 4.8.35,
 * so this is compatible with 4.8.35+ and 5.4.3+
 */
class PHPUnitClassUsageSniff implements Sniff {
	/**
	 * Only look for classes that extend something
	 *
	 * @inheritDoc
	 */
	public function register() {
		return [ T_EXTENDS ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr Position of extends token
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		// Skip the "extends" (1) and the class name (1 or more) surrounded by spaces (2).
		$classPtr = $phpcsFile->findPrevious( Tokens::$ooScopeTokens, $stackPtr - 4 );
		if ( !$classPtr || $tokens[$classPtr]['code'] !== T_CLASS ) {
			// interface Foo extends .. which we don't care about
			return;
		}
		$phpunitPtr = $phpcsFile->findNext( T_STRING, $stackPtr );
		$phpunitToken = $tokens[$phpunitPtr];
		if ( $phpunitToken['content'] !== 'PHPUnit_Framework_TestCase' ) {
			return;
		}

		$fix = $phpcsFile->addFixableWarning(
			'Namespaced PHPUnit TestCase class should be used instead',
			$phpunitPtr,
			'NotNamespaced'
		);
		if ( $fix ) {
			$new = 'PHPUnit\\Framework\\TestCase';
			// If this file is namespaced, we need a leading \
			$inANamespace = $phpcsFile->findPrevious( T_NAMESPACE, $classPtr ) !== false;
			$classNameWithSlash = $phpcsFile->findExtendedClassName( $classPtr );
			// But make sure it doesn't already have a slash...
			$hashLeadingSlash = $classNameWithSlash[0] === '\\';
			if ( $inANamespace && !$hashLeadingSlash ) {
				$new = '\\' . $new;
			}
			$phpcsFile->fixer->replaceToken( $phpunitPtr, $new );
		}
	}
}
