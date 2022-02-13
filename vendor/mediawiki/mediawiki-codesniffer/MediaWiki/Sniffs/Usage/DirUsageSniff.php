<?php
/**
 * Report error when dirname(__FILE__) is used instead of __DIR__
 *
 * Fail: dirname( __FILE__ )
 * Pass: dirname( __FILE__ . "/.." )
 * Pass: dirname( __FILE__, 2 )
 * Pass: dirname( joinpaths( __FILE__, ".." ) )
 * Pass: $abc->dirname( __FILE__ )
 * Pass: parent::dirname( __FILE__ )
 */

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DirUsageSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		// As per https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#Other
		return [ T_STRING ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Check if the function is dirname()
		if ( strcasecmp( $tokens[$stackPtr]['content'], 'dirname' ) !== 0 ) {
			return;
		}

		// Find the paranthesis for the function
		$nextToken = $phpcsFile->findNext( T_WHITESPACE, $stackPtr + 1, null, true );
		if ( $tokens[$nextToken]['code'] !== T_OPEN_PARENTHESIS ) {
			return;
		}

		// Check if __FILE__ is inside it
		$nextToken = $phpcsFile->findNext( T_WHITESPACE, $nextToken + 1, null, true );
		if ( $tokens[$nextToken]['code'] !== T_FILE ) {
			return;
		}

		// Check if it's a PHP function
		$prevToken = $phpcsFile->findPrevious( T_WHITESPACE, $stackPtr - 1, null, true );
		if ( $tokens[$prevToken]['code'] === T_OBJECT_OPERATOR
			|| $tokens[$prevToken]['code'] === T_NULLSAFE_OBJECT_OPERATOR
			|| $tokens[$prevToken]['code'] === T_DOUBLE_COLON
			|| $tokens[$prevToken]['code'] === T_FUNCTION
			|| $tokens[$prevToken]['code'] === T_CONST
		) {
			return;
		}

		// Find close paranthesis
		$nextToken = $phpcsFile->findNext( T_WHITESPACE, $nextToken + 1, null, true );
		if ( $tokens[$nextToken]['code'] !== T_CLOSE_PARENTHESIS ) {
			return;
		}

		$fix = $phpcsFile->addFixableError(
			'Use __DIR__ constant instead of calling dirname(__FILE__)',
			$stackPtr,
			'FunctionFound'
		);
		if ( $fix ) {
			$curToken = $stackPtr;
			while ( $curToken <= $nextToken ) {
				if ( $tokens[$curToken]['code'] === T_FILE ) {
					$phpcsFile->fixer->replaceToken( $curToken, '__DIR__' );
				} else {
					$phpcsFile->fixer->replaceToken( $curToken, '' );
				}
				$curToken += 1;
			}
		}
	}
}
