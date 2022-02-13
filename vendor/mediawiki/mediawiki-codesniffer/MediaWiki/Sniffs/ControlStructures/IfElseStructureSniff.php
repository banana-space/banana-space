<?php
/**
 * Sniff to warn when else and elseif are formatted incorrectly:
 * Pass: } else {
 * Fail: }  else {
 * Pass: } elseif ( $a == 1 ) {
 * Fail: }\nelseif ( $a == 1 ) {
 */

namespace MediaWiki\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class IfElseStructureSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_ELSE,
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
		$prevToken = $tokens[$stackPtr - 1];
		$nextToken = $tokens[$stackPtr + 1];
		$isAlternativeIfSyntax = false;
		if ( isset( $tokens[$stackPtr]['scope_opener'] ) ) {
			$scopeOpener = $tokens[$stackPtr]['scope_opener'];
			$isAlternativeIfSyntax = $tokens[$scopeOpener]['code'] === T_COLON;
		}

		// single space expected before else and elseif structure
		if ( !$isAlternativeIfSyntax &&
			( $prevToken['code'] !== T_WHITESPACE
			|| $prevToken['content'] !== ' ' )
		) {
			$fix = $phpcsFile->addFixableWarning(
				'Single space expected before "%s"',
				$stackPtr - 1,
				'SpaceBeforeElse',
				[ $tokens[$stackPtr]['content'] ]
			);
			if ( $fix ) {
				if ( $prevToken['code'] === T_CLOSE_CURLY_BRACKET ) {
					$phpcsFile->fixer->addContentBefore( $stackPtr, ' ' );
				} else {
					// Replace all previous whitespace with a space
					$phpcsFile->fixer->beginChangeset();
					$phpcsFile->fixer->replaceToken( $stackPtr - 1, ' ' );
					for ( $i = $stackPtr - 2; $tokens[$i]['code'] === T_WHITESPACE; $i-- ) {
						$phpcsFile->fixer->replaceToken( $i, '' );
					}
					$phpcsFile->fixer->endChangeset();
				}
			}
		}
		// space after elseif structure be processed in SpaceAfterControlStructureSniff
		if ( $tokens[$stackPtr]['code'] === T_ELSEIF ) {
			return;
		}
		// single space expected after else structure
		if ( !$isAlternativeIfSyntax &&
			( $nextToken['code'] !== T_WHITESPACE
			|| $nextToken['content'] !== ' ' )
		) {
			$fix = $phpcsFile->addFixableWarning(
				'Single space expected after "%s"',
				$stackPtr + 1,
				'SpaceAfterElse',
				[ $tokens[$stackPtr]['content'] ]
			);
			if ( $fix ) {
				if ( $nextToken['code'] === T_OPEN_CURLY_BRACKET ) {
					$phpcsFile->fixer->addContent( $stackPtr, ' ' );
				} else {
					// Replace all after whitespace with a space
					$phpcsFile->fixer->beginChangeset();
					$phpcsFile->fixer->replaceToken( $stackPtr + 1, ' ' );
					for ( $i = $stackPtr + 2; $tokens[$i]['code'] === T_WHITESPACE; $i++ ) {
						$phpcsFile->fixer->replaceToken( $i, '' );
					}
					$phpcsFile->fixer->endChangeset();
				}
			}
		}
		// no space expected after else structure, when it is alternative syntax
		if ( $isAlternativeIfSyntax
			&& $nextToken['code'] === T_WHITESPACE
		) {
			$fix = $phpcsFile->addFixableWarning(
				'No space expected after "%s"',
				$stackPtr + 1,
				'SpaceAfterElse',
				[ $tokens[$stackPtr]['content'] ]
			);
			if ( $fix ) {
				// Replace all after whitespace with no space
				$phpcsFile->fixer->beginChangeset();
				for ( $i = $stackPtr + 1; $tokens[$i]['code'] === T_WHITESPACE; $i++ ) {
					$phpcsFile->fixer->replaceToken( $i, '' );
				}
				$phpcsFile->fixer->endChangeset();
			}
		}
	}
}
