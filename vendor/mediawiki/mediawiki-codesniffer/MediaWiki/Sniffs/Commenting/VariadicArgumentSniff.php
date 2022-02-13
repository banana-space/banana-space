<?php
/**
 * Warn if any comment containing hints of a variadic argument is found within the arguments list.
 * This includes comment only containing "...", or containing variable names preceded by "...",
 * or ",...".
 * Actual variadic arguments should be used instead.
 */

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class VariadicArgumentSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_FUNCTION ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		if ( !isset( $tokens[$stackPtr]['parenthesis_opener'] ) ) {
			return;
		}

		$end = $tokens[$stackPtr]['parenthesis_closer'];
		$commentPos = $phpcsFile->findNext( T_COMMENT, $tokens[$stackPtr]['parenthesis_opener'] + 1, $end );
		while ( $commentPos !== false ) {
			$comment = $tokens[$commentPos]['content'];
			if ( substr( $comment, 0, 2 ) === '/*' ) {
				$content = substr( $comment, 2, -2 );
				if ( preg_match( '/^[,\s]*\.\.\.\s*$|\.\.\.\$|\$[a-z_][a-z0-9_]*,\.\.\./i', $content ) ) {
					// An autofix would be trivial to write, but we shouldn't offer that. Removing the
					// comment is not enough, because people should also add the actual variadic parameter.
					// For some methods, variadic parameters are only documented via this inline comment,
					// hence an autofixer would effectively remove any documentation about them.
					$phpcsFile->addError(
						'Comments indicating variadic arguments are superfluous and should be replaced ' .
							'with actual variadic arguments',
						$commentPos,
						'SuperfluousVariadicArgComment'
					);
				}
			}
			$commentPos = $phpcsFile->findNext( T_COMMENT, $commentPos + 1, $end );
		}
	}

}
