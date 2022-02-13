<?php
/**
 * Verify specific control structures are followed by a single space.
 */

namespace MediaWiki\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class SpaceAfterControlStructureSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		// Per https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#Spaces
		return [
			T_IF,
			T_ELSEIF,
			T_WHILE,
			T_FOR,
			T_FOREACH,
			T_SWITCH,
			T_CATCH,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		if ( !isset( $tokens[$stackPtr + 1] ) ) {
			// Syntax error or live coding, bow out.
			return;
		}

		$nextToken = $tokens[$stackPtr + 1];
		if ( $nextToken['code'] !== T_WHITESPACE || $nextToken['content'] !== ' ' ) {
			$fix = $phpcsFile->addFixableWarning(
				'Control structure "%s" must be followed by a single space',
				$stackPtr,
				'Incorrect',
				[ $tokens[ $stackPtr ]['content'] ]
			);
			if ( $fix ) {
				if ( $nextToken['code'] !== T_WHITESPACE ) {
					$phpcsFile->fixer->addContent( $stackPtr, ' ' );
				} else {
					// Too many spaces
					$phpcsFile->fixer->replaceToken( $stackPtr + 1, ' ' );
				}
			}
		}
	}
}
