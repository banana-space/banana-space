<?php
/**
 * Verify MediaWiki global function naming convention.
 * A global function's name must be prefixed with 'wf' or 'ef'.
 * Per https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#Naming
 */

namespace MediaWiki\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class PrefixedGlobalFunctionsSniff implements Sniff {

	/** @var string[] */
	public $ignoreList = [];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_FUNCTION ];
	}

	/**
	 * @var int[] array containing the first locations of namespaces in files that we have seen so far.
	 */
	private $firstNamespaceLocations = [];

	/**
	 * @param File $phpcsFile
	 * @param int $ptr The current token index.
	 *
	 * @return bool Does a namespace statement exist before this position in the file?
	 */
	private function tokenIsNamespaced( File $phpcsFile, $ptr ) {
		$fileName = $phpcsFile->getFilename();

		// Check if we already know if the token is namespaced or not
		if ( !isset( $this->firstNamespaceLocations[$fileName] ) ) {
			// If not scan the whole file at once looking for namespacing or lack of and set in the statics.
			$tokens = $phpcsFile->getTokens();
			$numTokens = $phpcsFile->numTokens;
			for ( $tokenIndex = 0; $tokenIndex < $numTokens; $tokenIndex++ ) {
				$token = $tokens[$tokenIndex];
				if ( $token['code'] === T_NAMESPACE && !isset( $token['scope_opener'] ) ) {
					// In the format of "namespace Foo;", which applies to everything below
					$this->firstNamespaceLocations[$fileName] = $tokenIndex;
					break;
				} elseif ( isset( $token['scope_closer'] ) ) {
					// Skip any non-zero level code as it can not contain a relevant namespace
					$tokenIndex = $token['scope_closer'];
					continue;
				}
			}

			// Nothing found, just save unreachable token index
			if ( !isset( $this->firstNamespaceLocations[$fileName] ) ) {
				$this->firstNamespaceLocations[$fileName] = $numTokens;
			}
		}

		// Return if the token was namespaced.
		return $ptr > $this->firstNamespaceLocations[$fileName];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Check if function is global
		if ( $tokens[$stackPtr]['level'] !== 0 ) {
			return;
		}

		$name = $phpcsFile->getDeclarationName( $stackPtr );
		if ( $name === null || in_array( $name, $this->ignoreList ) ) {
			return;
		}

		$prefix = substr( $name, 0, 2 );
		if ( $prefix === 'wf' || $prefix === 'ef' ) {
			return;
		}

		if ( $this->tokenIsNamespaced( $phpcsFile, $stackPtr ) ) {
			return;
		}

		$phpcsFile->addError(
			'Global function "%s" is lacking a \'wf\' prefix. Should be "%s".',
			$stackPtr,
			'wfPrefix',
			[ $name, 'wf' . ucfirst( $name ) ]
		);
	}
}
