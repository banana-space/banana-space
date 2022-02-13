<?php
/**
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

/**
 * Ensure that setUp() and tearDown() have a void return type hint, since
 * they were added in PHPUnit 8
 */
class PHPUnitTypeHintsSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_CLASS ];
	}

	/**
	 * Set of PHPUnit base classes, without leading backslash
	 */
	private const PHPUNIT_CLASSES = [
		'MediaWikiTestCase' => true,
		'MediaWikiUnitTestCase' => true,
		'MediaWikiIntegrationTestCase' => true,
		'PHPUnit_Framework_TestCase' => true,
		// This class may be 'use'd, but checking for that would be complicated
		'PHPUnit\\Framework\\TestCase' => true,
	];

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$extendedClass = ltrim( $phpcsFile->findExtendedClassName( $stackPtr ), '\\' );
		if (
			!$this->isTestClass( $phpcsFile, $stackPtr ) &&
			!array_key_exists( $extendedClass, self::PHPUNIT_CLASSES )
		) {
			return;
		}

		$tokens = $phpcsFile->getTokens();
		$startTok = $tokens[$stackPtr];
		$cur = $startTok['scope_opener'];
		$end = $startTok['scope_closer'];

		$functions = [
			'setUp' => false,
			'tearDown' => false,
			'setUpBeforeClass' => false,
			'tearDownAfterClass' => false,
			'assertPreConditions' => false,
			'assertPostConditions' => false,
			'onNotSuccessfulTest' => false,
		];

		$cur = $phpcsFile->findNext( T_FUNCTION, $cur + 1, $end );
		while ( $cur !== false && $functions ) {
			$funcNamePos = $phpcsFile->findNext( T_STRING, $cur );
			$funcName = $tokens[$funcNamePos]['content'];
			if ( isset( $functions[$funcName] ) ) {
				unset( $functions[$funcName] );
				$props = $phpcsFile->getMethodProperties( $cur );
				$retTypeHint = $props['return_type'];
				$retTypeTok = $props['return_type_token'];

				$err = 'The PHPUnit method %s() should have a return typehint of "void"';
				if ( $retTypeHint !== 'void' ) {
					if ( $retTypeTok === false ) {
						// Easy case, no return type specified. Offer autofix
						$fix = $phpcsFile->addFixableError(
							$err,
							$cur,
							'MissingTypehint',
							[ $funcName ]
						);
						if ( $fix ) {
							$phpcsFile->fixer->addContent( $tokens[$cur]['parenthesis_closer'], ' : void ' );
						}
					} else {
						// There's already a return type hint. No autofix, as the method must be manually checked
						$phpcsFile->addError( $err, $cur, 'WrongTypehint', [ $funcName ] );
					}
				}
			}
			$cur = $phpcsFile->findNext( T_FUNCTION, $cur + 1, $end );
		}
	}

	/**
	 * @see PhpunitAnnotationsSniff::isTestClass
	 * @todo It would be great to have a common interface
	 *
	 * @param File $phpcsFile
	 * @param int $classPtr
	 * @return bool
	 */
	private function isTestClass( File $phpcsFile, $classPtr ) {
		return (bool)preg_match(
			'/(?:Test(?:Case)?(?:Base)?|Suite)$/', $phpcsFile->getDeclarationName( $classPtr )
		);
	}
}
