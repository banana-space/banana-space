<?php
/**
 * Disallow empty line at the begin of function.
 */

namespace MediaWiki\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowEmptyLineFunctionsSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_FUNCTION,
			T_CLOSURE,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$current = $tokens[$stackPtr];
		if ( !isset( $current['scope_opener'] ) ||
			!isset( $current['parenthesis_closer'] )
		) {
			return;
		}
		$openBrace = $current['scope_opener'];
		$next = $phpcsFile->findNext( T_WHITESPACE, $openBrace + 1, null, true );
		if ( $next === false ) {
			return;
		}
		if ( $tokens[$next]['line'] > $tokens[$openBrace]['line'] + 1 ) {
			$fix = $phpcsFile->addFixableError(
				'Unexpected empty line at the begin of function.',
				$stackPtr,
				'NoEmptyLine'
			);
			if ( $fix ) {
				$phpcsFile->fixer->beginChangeset();
				$i = $openBrace + 1;
				while ( $tokens[$i]['line'] !== $tokens[$next]['line'] ) {
					$phpcsFile->fixer->replaceToken( $i, '' );
					$i++;
				}
				$phpcsFile->fixer->addNewlineBefore( $i );
				$phpcsFile->fixer->endChangeset();
			}
		}
	}
}
