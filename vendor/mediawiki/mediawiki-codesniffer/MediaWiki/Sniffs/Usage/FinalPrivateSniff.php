<?php
/**
 * Sniff to suppress the use of `final` on private methods
 *
 * Once PHP 8 is required, using both `final` and `private` on
 * a function will produce a warning, and this sniff should be
 * removed.
 *
 * @author DannyS712
 */

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class FinalPrivateSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_FINAL,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Find the next non-empty token
		$next = $phpcsFile->findNext(
			Tokens::$emptyTokens,
			$stackPtr + 1,
			null,
			true
		);
		if ( $next === false ) {
			// Nothing after this, must be live coding
			return;
		}

		$nextToken = $tokens[$next];
		if ( $nextToken['code'] !== T_PRIVATE ) {
			// Not a private function
			return;
		}

		$fix = $phpcsFile->addFixableError(
			'The `final` modifier should not be used for private methods',
			$stackPtr,
			'Found'
		);
		if ( $fix ) {
			$nextNonWhitespace = $phpcsFile->findNext(
				T_WHITESPACE,
				$stackPtr + 1,
				null,
				true
			);

			$phpcsFile->fixer->beginChangeset();
			$phpcsFile->fixer->replaceToken( $stackPtr, '' );
			for ( $i = $stackPtr + 1; $i < $nextNonWhitespace; $i++ ) {
				$phpcsFile->fixer->replaceToken( $i, '' );
			}
			$phpcsFile->fixer->endChangeset();
		}
	}
}
