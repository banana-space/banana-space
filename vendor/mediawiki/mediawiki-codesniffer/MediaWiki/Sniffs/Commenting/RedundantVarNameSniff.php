<?php

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Custom sniff that reports and repairs documentations of class properties that repeat the
 * variable name, which is unnecessary.
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class RedundantVarNameSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_DOC_COMMENT_TAG ];
	}

	/**
	 * @inheritDoc
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		if ( $tokens[$stackPtr]['level'] !== 1 || $tokens[$stackPtr]['content'] !== '@var' ) {
			return;
		}

		$docPtr = $phpcsFile->findNext( T_DOC_COMMENT_WHITESPACE, $stackPtr + 1, null, true );
		if ( !$docPtr || $tokens[$docPtr]['code'] !== T_DOC_COMMENT_STRING ) {
			return;
		}

		// This assumes there is always a variable somewhere after a @var, which should be the case
		$variablePtr = $phpcsFile->findNext( T_VARIABLE, $docPtr + 1 );
		if ( !$variablePtr ) {
			return;
		}

		$visibilityPtr = $phpcsFile->findPrevious(
			// This is already compatible with `public int $var;` available since PHP 7.4
			Tokens::$emptyTokens + [ T_NULLABLE, T_STRING ],
			$variablePtr - 1,
			$docPtr + 1,
			true
		);
		if ( !$visibilityPtr || ( $tokens[$visibilityPtr]['code'] !== T_VAR &&
			!isset( Tokens::$scopeModifiers[ $tokens[$visibilityPtr]['code'] ] ) )
		) {
			return;
		}

		$variableName = $tokens[$variablePtr]['content'];
		if ( !preg_match(
			'{^([^\s$]+\s)?\s*' . preg_quote( $variableName ) . '\b\s*(.*)}is',
			$tokens[$docPtr]['content'],
			$matches
		) ) {
			return;
		}

		$fix = $phpcsFile->addFixableError(
			'Found redundant variable name %s in @var',
			$docPtr,
			'Found',
			[ $variableName ]
		);
		if ( $fix ) {
			$phpcsFile->fixer->replaceToken( $docPtr, trim( $matches[1] . $matches[2] ) );
		}
	}

}
