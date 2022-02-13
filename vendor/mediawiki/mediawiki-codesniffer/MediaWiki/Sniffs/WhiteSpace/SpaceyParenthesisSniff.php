<?php
/**
 * Make sure calling functions is spacey:
 * $this->foo( $arg, $arg2 );
 * wfFoo( $arg, $arg2 );
 *
 * But, wfFoo() is ok.
 *
 * Also disallow wfFoo( ) and wfFoo(  $param )
 */

namespace MediaWiki\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class SpaceyParenthesisSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_OPEN_PARENTHESIS,
			T_CLOSE_PARENTHESIS,
			T_OPEN_SHORT_ARRAY,
			T_CLOSE_SHORT_ARRAY
		];
	}

	/**
	 * @param int $token PHPCS token code.
	 * @return bool Whether the token code is closed.
	 */
	private function isClosed( $token ) {
		return $token === T_CLOSE_PARENTHESIS
			|| $token === T_CLOSE_SHORT_ARRAY;
	}

	/**
	 * @param int $token PHPCS token code.
	 * @return bool Whether the token code is parenthesis.
	 */
	private function isParenthesis( $token ) {
		return $token === T_OPEN_PARENTHESIS
			|| $token === T_CLOSE_PARENTHESIS;
	}

	/**
	 * @param int $token PHPCS token code.
	 * @return bool Whether the token code is a comment.
	 */
	private function isComment( $token ) {
		return $token === T_COMMENT
			|| $token === T_PHPCS_ENABLE
			|| $token === T_PHPCS_DISABLE
			|| $token === T_PHPCS_SET
			|| $token === T_PHPCS_IGNORE
			|| $token === T_PHPCS_IGNORE_FILE;
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void|int
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$currentToken = $tokens[$stackPtr];

		if ( $this->isClosed( $currentToken['code'] ) ) {
			$this->processCloseParenthesis( $phpcsFile, $stackPtr );
			return;
		}

		if ( $tokens[$stackPtr - 1]['code'] === T_WHITESPACE
			&& ( $tokens[$stackPtr - 2]['code'] === T_STRING
				|| $tokens[$stackPtr - 2]['code'] === T_ARRAY )
		) {
			// String (or 'array') followed by whitespace followed by
			// opening brace is probably a function call.
			$bracketType = $this->isParenthesis( $currentToken['code'] )
				? 'parenthesis of function call'
				: 'bracket of array';
			$fix = $phpcsFile->addFixableWarning(
				'Space found before opening %s',
				$stackPtr - 1,
				'SpaceBeforeOpeningParenthesis',
				[ $bracketType ]
			);
			if ( $fix ) {
				$phpcsFile->fixer->replaceToken( $stackPtr - 1, '' );
			}
		}

		if ( !isset( $tokens[$stackPtr + 2] ) ) {
			// Syntax error or live coding, bow out.
			return;
		}

		// Shorten out as early as possible on empty parenthesis
		if ( $this->isClosed( $tokens[$stackPtr + 1]['code'] ) ) {
			// Intentionally do not process the closing parenthesis again
			return $stackPtr + 2;
		}

		// Check for space between parentheses without any arguments
		if ( $tokens[$stackPtr + 1]['code'] === T_WHITESPACE
			&& $this->isClosed( $tokens[$stackPtr + 2]['code'] )
		) {
			$bracketType = $this->isParenthesis( $currentToken['code'] ) ? 'parentheses' : 'brackets';
			$fix = $phpcsFile->addFixableWarning(
				'Unnecessary space found within %s',
				$stackPtr + 1,
				'UnnecessarySpaceBetweenParentheses',
				[ $bracketType ]
			);
			if ( $fix ) {
				$phpcsFile->fixer->replaceToken( $stackPtr + 1, '' );
			}

			// Intentionally do not process the closing parenthesis again
			return $stackPtr + 3;
		}

		$this->processOpenParenthesis( $phpcsFile, $stackPtr );
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 */
	protected function processOpenParenthesis( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$nextToken = $tokens[$stackPtr + 1];
		// No space or not single space
		if ( ( $nextToken['code'] === T_WHITESPACE &&
				$nextToken['line'] === $tokens[$stackPtr + 2]['line']
				&& $nextToken['content'] !== ' ' )
			|| $nextToken['code'] !== T_WHITESPACE
		) {
			$fix = $phpcsFile->addFixableWarning(
				'Single space expected after opening parenthesis',
				$stackPtr + 1,
				'SingleSpaceAfterOpenParenthesis'
			);
			if ( $fix ) {
				if ( $nextToken['code'] === T_WHITESPACE
					&& $nextToken['line'] === $tokens[$stackPtr + 2]['line']
					&& $nextToken['content'] !== ' '
				) {
					$phpcsFile->fixer->replaceToken( $stackPtr + 1, ' ' );
				} else {
					$phpcsFile->fixer->addContent( $stackPtr, ' ' );
				}
			}
		}
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 */
	protected function processCloseParenthesis( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$previousToken = $tokens[$stackPtr - 1];

		if ( ( $previousToken['code'] === T_WHITESPACE
				&& $previousToken['content'] === ' ' )
			|| ( $this->isComment( $previousToken['code'] )
				&& substr( $previousToken['content'], -1, 1 ) === "\n" )
		) {
			// If previous token was
			// '(' or ' ' or a comment ending with a newline
			return;
		}

		// If any of the whitespace tokens immediately before this token is a newline
		$ptr = $stackPtr - 1;
		while ( $tokens[$ptr]['code'] === T_WHITESPACE ) {
			if ( $tokens[$ptr]['content'] === $phpcsFile->eolChar ) {
				return;
			}
			$ptr--;
		}

		// If the comment before all the whitespaces immediately preceding the ')' ends with a newline
		if ( $this->isComment( $tokens[$ptr]['code'] )
			&& substr( $tokens[$ptr]['content'], -1, 1 ) === "\n"
		) {
			return;
		}

		$fix = $phpcsFile->addFixableWarning(
			'Single space expected before closing parenthesis',
			$stackPtr,
			'SingleSpaceBeforeCloseParenthesis'
		);
		if ( $fix ) {
			if ( $previousToken['code'] === T_WHITESPACE ) {
				$phpcsFile->fixer->replaceToken( $stackPtr - 1, ' ' );
			} else {
				$phpcsFile->fixer->addContentBefore( $stackPtr, ' ' );
			}
		}
	}
}
