<?php
/**
 * make sure a space between closing parenthesis and opening brace
 * during if,while,for,foreach,switch,catch statement
 * fail: if ( $a == 1 ){
 * fail: if ( $a == 1 )\eol\t{
 * fail: switch ( $a ){
 * pass: if ( $a == 1 ) {
 * pass: switch ( $a ) {
 */

namespace MediaWiki\Sniffs\WhiteSpace;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class SpaceBeforeControlStructureBraceSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
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
	 * @param int $stackPtr The index of current token.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		if ( !isset( $tokens[$stackPtr]['scope_opener'] ) ) {
			return;
		}
		$openBrace = $tokens[$stackPtr]['scope_opener'];
		if ( $tokens[$openBrace]['code'] !== T_OPEN_CURLY_BRACKET ) {
			return;
		}
		if ( $tokens[$stackPtr + 1]['code'] !== T_WHITESPACE
			|| $tokens[$stackPtr + 2]['code'] !== T_OPEN_PARENTHESIS
			|| $tokens[$stackPtr + 2]['parenthesis_closer'] === null
		) {
			return;
		}

		$closeBracket = $tokens[$stackPtr + 2]['parenthesis_closer'];
		$closeBracketLine = $tokens[$closeBracket]['line'];
		$openBraceLine = $tokens[$openBrace]['line'];
		$lineDifference = ( $openBraceLine - $closeBracketLine );
		if ( $lineDifference > 0 ) {
			// if brace on new line
			$fix = $this->processLineDiff( $phpcsFile, $openBrace );
		} else {
			// if brace on the same line as closing parenthesis
			$fix = $this->processLineSame( $phpcsFile, $openBrace, $closeBracket );
		}

		if ( $fix ) {
			$phpcsFile->fixer->beginChangeset();
			for ( $i = $closeBracket + 1; $i < $openBrace; $i++ ) {
				$phpcsFile->fixer->replaceToken( $i, '' );
			}
			$phpcsFile->fixer->addContent( $closeBracket, ' ' );
			$phpcsFile->fixer->endChangeset();
		}
	}

	/**
	 * Process The close parenthesis on the same line as open brace.
	 *
	 * @param File $phpcsFile
	 * @param int $openBrace The index of open brace.
	 * @return bool
	 */
	protected function processLineDiff( File $phpcsFile, $openBrace ) {
		$error = 'Opening brace should be on the same line as the declaration';
		return $phpcsFile->addFixableError( $error, $openBrace, 'BraceOnNewLine' );
	}

	/**
	 * Process The close parenthesis on the different line with open brace.
	 *
	 * @param File $phpcsFile
	 * @param int $openBrace The index of open brace.
	 * @param int $closeBracket The index of close bracket.
	 * @return bool
	 */
	protected function processLineSame( File $phpcsFile, $openBrace, $closeBracket ) {
		$tokens = $phpcsFile->getTokens();
		$content = $phpcsFile->getTokensAsString( $closeBracket + 1, $openBrace - $closeBracket - 1 );
		$length = strlen( $content );
		if ( $length === 1 && $tokens[$closeBracket + 1]['content'] === ' ' ) {
			return false;
		}

		return $phpcsFile->addFixableWarning(
			'Expected 1 space between closing parenthesis and opening brace; find %s characters',
			$openBrace,
			'SpaceBeforeControl',
			[ $length ]
		);
	}
}
