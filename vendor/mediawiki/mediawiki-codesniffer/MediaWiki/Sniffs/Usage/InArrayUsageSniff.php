<?php

namespace MediaWiki\Sniffs\Usage;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Custom sniff that finds unnecessary slow in_array() that can be replaced with array_key_exists()
 * or isset().
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class InArrayUsageSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_STRING ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Continue only if the string we found is wrapped in at least one parenthesis
		if ( empty( $tokens[$stackPtr]['nested_parenthesis'] ) ) {
			return;
		}

		if ( strcasecmp( $tokens[$stackPtr]['content'], 'array_flip' ) !== 0
			&& strcasecmp( $tokens[$stackPtr]['content'], 'array_keys' ) !== 0
		) {
			return;
		}

		end( $tokens[$stackPtr]['nested_parenthesis'] );
		$openParenthesisPtr = key( $tokens[$stackPtr]['nested_parenthesis'] );

		// Continue only if the parenthesis belongs to an in_array() call
		if ( $tokens[$openParenthesisPtr - 1]['code'] !== T_STRING
			|| strcasecmp( $tokens[$openParenthesisPtr - 1]['content'], 'in_array' ) !== 0
		) {
			return;
		}

		$previous = $phpcsFile->findPrevious(
			T_WHITESPACE,
			$stackPtr - 1,
			$openParenthesisPtr + 1,
			true
		);
		if ( $tokens[$previous]['code'] !== T_COMMA ) {
			return;
		}

		$phpcsFile->addError(
			'Found slow in_array( …, %s() ), should be array_key_exists() or isset()',
			$stackPtr,
			'Found',
			[ $tokens[$stackPtr]['content'] ]
		);
	}
}
