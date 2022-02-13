<?php
/**
 * Verify alternative syntax is not being used
 */

namespace MediaWiki\Sniffs\AlternativeSyntax;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class AlternativeSyntaxSniff implements Sniff {
	/**
	 * @inheritDoc
	 */
	public function register() {
		// Per https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP
		// section on alternative syntax.
		return [
			T_ENDDECLARE,
			T_ENDFOR,
			T_ENDFOREACH,
			T_ENDIF,
			T_ENDSWITCH,
			T_ENDWHILE,
		];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$error = 'Alternative syntax such as "%s" should not be used';
		$data = [ $tokens[$stackPtr]['content'] ];
		$phpcsFile->addWarning( $error, $stackPtr, 'AlternativeSyntax', $data );
	}
}
