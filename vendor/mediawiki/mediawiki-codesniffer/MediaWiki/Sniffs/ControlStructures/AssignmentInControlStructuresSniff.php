<?php
/**
 * Sniff to suppress the use of:
 * Fail: if ( $a = 0 )
 * Fail: if ( $a *= foo() )
 * Fail: if ( $a += foo() )
 * Fail: while ( $a = foo() )
 * Pass: if ( $a == 0 )
 * Pass: if ( $a === 0 )
 * Pass: if ( $a === array( 1 => 0 ) )
 * Pass: while ( $a < 0 )
 */

namespace MediaWiki\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class AssignmentInControlStructuresSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_IF,
			T_WHILE,
			T_ELSEIF,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$token = $tokens[$stackPtr];

		$next = $token['parenthesis_opener'] + 1;
		$end = $token['parenthesis_closer'];
		while ( $next < $end ) {
			$code = $tokens[$next]['code'];
			// Check if any assignment operator was used. Allow T_DOUBLE_ARROW as that can
			// be used in an array like `if ( $foo === array( 'foo' => 'bar' ) )`
			if ( array_key_exists( $code, Tokens::$assignmentTokens )
				&& $code !== T_DOUBLE_ARROW
			) {
				$phpcsFile->addError(
					'Assignment expression not allowed within "%s".',
					$stackPtr,
					'AssignmentInControlStructures',
					[ $token['content'] ]
				);
				break;
			}
			$next++;
		}
	}
}
