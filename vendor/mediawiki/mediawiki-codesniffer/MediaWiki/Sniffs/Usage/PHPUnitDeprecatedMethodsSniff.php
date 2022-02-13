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
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids usage of PHPUnit methods deprecated/removed in PHPUnit8
 * @fixme Avoid duplication with other PHPUnit-related sniffs
 */
class PHPUnitDeprecatedMethodsSniff implements Sniff {
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

	private const FORBIDDEN_ATTRIBUTE_METHODS = [
		'assertAttributeContains',
		'assertAttributeNotContains',
		'assertAttributeContainsOnly',
		'assertAttributeNotContainsOnly',
		'assertAttributeCount',
		'assertAttributeNotCount',
		'assertAttributeEquals',
		'assertAttributeNotEquals',
		'assertAttributeEmpty',
		'assertAttributeNotEmpty',
		'assertAttributeGreaterThan',
		'assertAttributeGreaterThanOrEqual',
		'assertAttributeLessThan',
		'assertAttributeLessThanOrEqual',
		'assertAttributeSame',
		'assertAttributeNotSame',
		'assertAttributeInstanceOf',
		'assertAttributeNotInstanceOf',
		'assertAttributeInternalType',
		'assertAttributeNotInternalType',
		'attribute',
		'attributeEqualTo',
		'readAttribute',
		'getStaticAttribute',
		'getObjectAttribute',
	];

	private const CHECK_METHODS = [
		// FORBIDDEN_ATTRIBUTE_METHODS
		'assertInternalType',
		'assertNotInternalType',
		'assertType',
		'assertArraySubset',
		// No assertEquals because we cannot do type checking
	];

	/**
	 * Replacements for assertInternalType, see IsType::KNOWN_TYPES.
	 * The * inside should be replaced either with 'Not' or '' depending
	 * on whether the call is to assertInternalType or assertNotInternalType
	 */
	private const INTERNAL_TYPES_REPLACEMENTS = [
		'array' => 'assertIs*Array',
		'boolean' => 'assertIs*Bool',
		'bool' => 'assertIs*Bool',
		'double' => 'assertIs*Float',
		'float' => 'assertIs*Float',
		'integer' => 'assertIs*Int',
		'int' => 'assertIs*Int',
		'null' => 'assert*Null',
		'numeric' => 'assertIs*Numeric',
		'object' => 'assertIs*Object',
		'real' => 'assertIs*Float',
		'resource' => 'assertIs*Resource',
		'string' => 'assertIs*String',
		'scalar' => 'assertIs*Scalar',
		'callable' => 'assertIs*Callable',
		'iterable' => 'assertIs*Iterable',
	];

	/** @var File */
	private $file;

	/** @var array */
	private $tokens;

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_CLASS ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr Position of extends token
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$this->file = $phpcsFile;
		$this->tokens = $phpcsFile->getTokens();

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
		$checkMethods = array_merge( self::CHECK_METHODS, self::FORBIDDEN_ATTRIBUTE_METHODS );
		$nextToken = [ T_PAAMAYIM_NEKUDOTAYIM, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR ];

		$cur = $phpcsFile->findNext( $nextToken, $cur + 1, $end );
		while ( $cur !== false ) {
			$prev = $phpcsFile->findPrevious( Tokens::$emptyTokens, $cur - 1, null, true );
			if (
				$prev && (
					( $tokens[$prev]['code'] === T_VARIABLE && $tokens[$prev]['content'] === '$this' ) ||
					in_array( $tokens[$prev]['code'], [ T_STATIC, T_SELF ], true )
				)
			) {
				$funcTok = $phpcsFile->findNext( Tokens::$emptyTokens, $cur + 1, null, true );

				if (
					$tokens[$funcTok]['code'] === T_STRING &&
					in_array( $tokens[$funcTok]['content'], $checkMethods, true )
				) {
					$fname = $tokens[$funcTok]['content'];
					switch ( $fname ) {
						case 'assertArraySubset':
							$this->handleAssertArraySubset( $funcTok );
							break;
						case 'assertType':
							// MediaWiki's own variant to make things more complicated.
						case 'assertInternalType':
						case 'assertNotInternalType':
							$this->handleAssertInternalType( $fname, $funcTok );
							break;
						default:
							if ( in_array( $fname, self::FORBIDDEN_ATTRIBUTE_METHODS, true ) ) {
								$this->handleAttributeMethod( $fname, $funcTok );
							} else {
								throw new \LogicException( "Unhandled case $fname" );
							}
					}
				}
				$cur = $funcTok;
			}

			$cur = $phpcsFile->findNext( $nextToken, $cur + 1, $end );
		}
	}

	/**
	 * @param string $funcName Either assertInternalType, assertNotInternalType, or assertType
	 * @param int $funcPos Token position of the function call
	 */
	private function handleAssertInternalType( string $funcName, int $funcPos ) {
		$not = $funcName === 'assertNotInternalType' ? 'Not' : '';
		if ( $funcName === 'assertType' ) {
			$err = 'MediaWikiIntegrationTestCase::assertType was deprecated in MW 1.35.';
			$data = [];
		} else {
			$err = 'The PHPUnit method assert%sInternalType() was deprecated in PHPUnit 8.';
			$data = [ $not ];
		}

		$parensToken = $this->file->findNext( T_WHITESPACE, $funcPos + 1, null, true );
		if ( $this->tokens[$parensToken]['code'] !== T_OPEN_PARENTHESIS ) {
			return;
		}

		$argToken = $this->file->findNext( T_WHITESPACE, $parensToken + 1, null, true );
		if ( $this->tokens[$argToken]['code'] !== T_CONSTANT_ENCAPSED_STRING ) {
			// Probably a variable.
			$this->file->addError( $err, $funcPos, 'AssertInternalTypeGeneric', $data );
			return;
		}

		$type = trim( $this->tokens[$argToken]['content'], '"\'' );
		if ( !array_key_exists( $type, self::INTERNAL_TYPES_REPLACEMENTS ) ) {
			// If it happens for assert(Not)InternalType, it's likely a bug, so report it.
			// If it happens for assertType, report it all the same because the method is deprecated.
			$this->file->addError( $err, $funcPos, 'AssertInternalTypeGeneric', $data );
			return;
		}

		$commaToken = $this->file->findNext( T_WHITESPACE, $argToken + 1, null, true );
		if ( $this->tokens[$commaToken]['code'] !== T_COMMA ) {
			// WTF? This will fail anyway.
			$this->file->addError( $err, $funcPos, 'AssertInternalTypeGeneric', $data );
			return;
		}

		$replacement = str_replace( '*', $not, self::INTERNAL_TYPES_REPLACEMENTS[$type] );
		$err .= ' Use %s() instead.';
		$data[] = $replacement;
		$fix = $this->file->addFixableError(
			$err,
			$funcPos,
			'AssertInternalTypeLiteral',
			$data
		);
		if ( $fix ) {
			$this->file->fixer->replaceToken( $funcPos, $replacement );
			$this->file->fixer->replaceToken( $argToken, '' );
			$this->file->fixer->replaceToken( $commaToken, '' );
		}
	}

	/**
	 * Deprecated in PHPUnit8 with no alternative. People should either use a workaround,
	 * or we should start requiring phpunit-arraysubset-asserts
	 *
	 * @param int $funcPos
	 * @suppress PhanUnusedPrivateMethodParameter Refers to the fixme
	 */
	private function handleAssertArraySubset( int $funcPos ) {
		// FIXME: What to do here? Remove/update/re-enable... T192167#5685401
		/*
		$this->file->addError(
			'The PHPUnit method assertArraySubset() was deprecated in PHPUnit 8.',
			$funcPos,
			'AssertArraySubset'
		);
		*/
	}

	/**
	 * This huge list of methods was deprecated in PHPUnit8 with no alternative.
	 *
	 * @param string $funcName
	 * @param int $funcPos
	 */
	private function handleAttributeMethod( string $funcName, int $funcPos ) {
		$this->file->addError(
			'The PHPUnit method %s() was deprecated in PHPUnit 8.',
			$funcPos,
			'AttributeMethods',
			[ $funcName ]
		);
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
