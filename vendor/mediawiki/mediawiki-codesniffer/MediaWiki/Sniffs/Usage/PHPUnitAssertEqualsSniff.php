<?php

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Discourages the use of PHPUnit's relaxed, not type-safe assertEquals() in favor of strict
 * alternatives like assertSame(), assertNull(), and such. Please note: The auto-fixes done by this
 * sniff can make PHPUnit test cases fail. These should be fixed not by reverting the fix, but by
 * finding better expected values or better assertions.
 *
 * @author Thiemo Kreuz
 * @license GPL-2.0-or-later
 */
class PHPUnitAssertEqualsSniff implements Sniff {

	private const ASSERTIONS = [
		'assertEquals' => true,
		'assertNotEquals' => true,
		'assertNotSame' => true,
	];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_STRING ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 *
	 * @return void|int
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$assertion = $tokens[$stackPtr]['content'];

		// We don't care about stuff that's not in a method in a class
		if ( $tokens[$stackPtr]['level'] < 2 || !isset( self::ASSERTIONS[$assertion] ) ) {
			return;
		}

		$opener = $phpcsFile->findNext( T_WHITESPACE, $stackPtr + 1, null, true );
		// Looks like this string is not a method call
		if ( !isset( $tokens[$opener]['parenthesis_closer'] ) ) {
			return $opener;
		}

		$isAssertEquals = $assertion === 'assertEquals';
		$expected = $phpcsFile->findNext( T_WHITESPACE, $opener + 1, null, true );
		$msg = '%s accepts many non-%s values, please use strict alternatives like %s';
		/** @var bool|string $fix */
		$fix = false;

		switch ( $tokens[$expected]['code'] ) {
			case T_NULL:
				if ( !$isAssertEquals ) {
					break;
				}

				$msgParams = [ $assertion, 'null', 'assertNull' ];
				if ( $phpcsFile->addFixableWarning( $msg, $stackPtr, 'Null', $msgParams ) ) {
					$fix = 'assertNull';
				}
				break;

			case T_FALSE:
				$replacement = $isAssertEquals ? 'assertFalse' : 'assertTrue';
				$msgParams = [ $assertion, $isAssertEquals ? 'false' : 'true', $replacement ];
				if ( $phpcsFile->addFixableWarning( $msg, $stackPtr, 'False', $msgParams ) ) {
					$fix = $replacement;
				}
				break;

			case T_TRUE:
				$replacement = $isAssertEquals ? 'assertTrue' : 'assertFalse';
				$msgParams = [ $assertion, $isAssertEquals ? 'true' : 'false', $replacement ];
				if ( $phpcsFile->addFixableWarning( $msg, $stackPtr, 'True', $msgParams ) ) {
					$fix = $replacement;
				}
				break;

			case T_LNUMBER:
				if ( !$isAssertEquals ) {
					break;
				}

				$number = (int)$tokens[$expected]['content'];
				if ( $number === 0 || $number === 1 ) {
					$msgParams = [ $assertion, $number ? 'numeric' : 'zero', 'assertSame' ];
					$fix = $phpcsFile->addFixableWarning( $msg, $stackPtr, 'Int', $msgParams );
				}
				break;

			case T_DNUMBER:
				if ( !$isAssertEquals ) {
					break;
				}

				$number = (float)$tokens[$expected]['content'];
				if ( $number === 0.0 || $number === 1.0 ) {
					$msgParams = [ $assertion, $number ? 'numeric' : 'zero', 'assertSame' ];
					$fix = $phpcsFile->addFixableWarning( $msg, $stackPtr, 'Float', $msgParams );
				}
				break;

			case T_CONSTANT_ENCAPSED_STRING:
				if ( !$isAssertEquals ) {
					break;
				}

				$msgParams = [ $assertion, 'string', 'assertSame' ];

				// The empty string as well as "0" are among PHP's "falsy" values
				if ( strlen( $tokens[$expected]['content'] ) <= 2 ||
					$tokens[$expected]['content'][1] === '0'
				) {
					$fix = $phpcsFile->addFixableWarning( $msg, $stackPtr, 'FalsyString', $msgParams );
					break;
				}

				$string = trim( substr( $tokens[$expected]['content'], 1, -1 ) );
				if ( ctype_digit( $string ) ) {
					$fix = $phpcsFile->addFixableWarning( $msg, $stackPtr, 'IntegerString', $msgParams );
				} elseif ( is_numeric( $string ) ) {
					$fix = $phpcsFile->addFixableWarning( $msg, $stackPtr, 'NumericString', $msgParams );
				}
		}

		$fixer = $phpcsFile->fixer;
		// Fall back to assertSame instead of blindly removing unknown tokens
		if ( is_string( $fix ) && $tokens[$expected + 1]['code'] === T_COMMA ) {
			$fixer->replaceToken( $stackPtr, $fix );
			$fixer->replaceToken( $expected, '' );
			$fixer->replaceToken( $expected + 1, '' );
		} elseif ( $fix ) {
			$fixer->replaceToken( $stackPtr, 'assertSame' );
		}

		// There is no way the next assertEquals() can be closer than this
		return $tokens[$opener]['parenthesis_closer'] + 4;
	}

}
