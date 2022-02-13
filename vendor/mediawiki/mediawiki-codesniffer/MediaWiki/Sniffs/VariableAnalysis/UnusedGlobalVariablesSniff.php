<?php
/**
 * Detect unused MediaWiki global variable.
 * Unused global variables should be removed.
 */

namespace MediaWiki\Sniffs\VariableAnalysis;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class UnusedGlobalVariablesSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_FUNCTION, T_CLOSURE ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		if ( !isset( $tokens[$stackPtr]['scope_opener'] ) ) {
			// An interface or abstract function which doesn't have a body
			return;
		}

		$scopeOpener = $tokens[$stackPtr]['scope_opener'] + 1;
		$scopeCloser = $tokens[$stackPtr]['scope_closer'];

		$endOfGlobal = 0;
		$globalVariables = [];
		$matches = [];
		$delayedSkip = [];

		for ( $i = $scopeOpener; $i < $scopeCloser; $i++ ) {
			// Process a delayed skip
			if ( isset( $delayedSkip[$i] ) ) {
				$i = $delayedSkip[$i];
				continue;
			}
			$code = $tokens[$i]['code'];
			if ( ( $code === T_CLOSURE || $code === T_FUNCTION || $code === T_ANON_CLASS )
				&& isset( $tokens[$i]['scope_closer'] )
			) {
				if ( $code === T_CLOSURE && isset( $tokens[$i]['parenthesis_closer'] ) ) {
					// Cannot skip directly to the end of closure
					// The use statement needs to be processed
					$delayedSkip[$tokens[$i]['scope_opener']] = $tokens[$i]['scope_closer'];

					// Skip the argument list of the closure
					$i = $tokens[$i]['parenthesis_closer'];
				} else {
					// Skip to the end of the inner function/anon class and continue
					$i = $tokens[$i]['scope_closer'];
				}
				continue;
			}

			if ( $code === T_GLOBAL ) {
				$endOfGlobal = $phpcsFile->findEndOfStatement( $i, T_COMMA );
			} elseif ( $code === T_VARIABLE ) {
				$variableName = $tokens[$i]['content'];
				if ( $i < $endOfGlobal ) {
					$globalVariables[$variableName] = $i;
				} else {
					unset( $globalVariables[$variableName] );
				}
			} elseif ( ( $code === T_DOUBLE_QUOTED_STRING || $code === T_HEREDOC )
				// Avoid the regex below when there are no globals to look for anyway
				&& $globalVariables
			) {
				preg_match_all( '/\${?(\w+)/', $tokens[$i]['content'], $matches );
				foreach ( $matches[1] as $variableName ) {
					unset( $globalVariables[ '$' . $variableName ] );
				}
			}
		}

		foreach ( $globalVariables as $variableName => $stackPtr ) {
			$phpcsFile->addWarning(
				'Global %s is never used.',
				$stackPtr,
				'UnusedGlobal' . $variableName,
				[ $variableName ]
			);
		}
	}
}
