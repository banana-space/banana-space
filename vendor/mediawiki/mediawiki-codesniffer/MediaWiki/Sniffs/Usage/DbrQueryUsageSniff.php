<?php
/**
 * Report warnings when $dbr->query() is used instead of $dbr->select()
 */

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DbrQueryUsageSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_OBJECT_OPERATOR ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		$dbrPtr = $phpcsFile->findPrevious( Tokens::$emptyTokens, $stackPtr - 1, null, true );
		if ( !$dbrPtr
			|| $tokens[$dbrPtr]['code'] !== T_VARIABLE
			|| $tokens[$dbrPtr]['content'] !== '$dbr'
		) {
			return;
		}

		$methodPtr = $phpcsFile->findNext( Tokens::$emptyTokens, $stackPtr + 1, null, true );
		if ( $methodPtr
			&& $tokens[$methodPtr]['code'] === T_STRING
			&& $tokens[$methodPtr]['content'] === 'query'
		) {
			$phpcsFile->addWarning(
				'Call $dbr->select() wrapper instead of $dbr->query()',
				$stackPtr,
				'DbrQueryFound'
			);
		}
	}
}
