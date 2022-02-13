<?php
/**
 * Verify MediaWiki global variable naming convention.
 * A global name must be prefixed with 'wg'.
 */

namespace MediaWiki\Sniffs\NamingConventions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ValidGlobalNameSniff implements Sniff {

	/**
	 * https://php.net/manual/en/reserved.variables.argv.php
	 */
	private const PHP_RESERVED = [
		'$GLOBALS',
		'$_SERVER',
		'$_GET',
		'$_POST',
		'$_FILES',
		'$_REQUEST',
		'$_SESSION',
		'$_ENV',
		'$_COOKIE',
		'$php_errormsg',
		'$HTTP_RAW_POST_DATA',
		'$http_response_header',
		'$argc',
		'$argv'
	];

	/**
	 * A list of global variable prefixes allowed.
	 *
	 * @var array
	 */
	public $allowedPrefixes = [ 'wg' ];

	/** @var string[] */
	public $ignoreList = [];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_GLOBAL ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr The current token index.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		$nameIndex = $phpcsFile->findNext( T_VARIABLE, $stackPtr + 1 );
		if ( !$nameIndex ) {
			// Avoid possibly running in an endless loop below
			return;
		}

		$semicolonIndex = $phpcsFile->findNext( T_SEMICOLON, $stackPtr + 1 );

		while ( $nameIndex < $semicolonIndex ) {
			if ( $tokens[$nameIndex ]['code'] !== T_WHITESPACE
				&& $tokens[$nameIndex ]['code'] !== T_COMMA
			) {
				$globalName = $tokens[$nameIndex]['content'];

				if ( in_array( $globalName, $this->ignoreList ) ||
					in_array( $globalName, self::PHP_RESERVED )
				) {
					return;
				}

				// Determine if a simple error message can be used

				if ( count( $this->allowedPrefixes ) === 1 ) {
					// Skip '$' and forge a valid global variable name
					$expected = '$' . $this->allowedPrefixes[0] . ucfirst( substr( $globalName, 1 ) );

					// Build message telling you the allowed prefix
					$allowedPrefix = '\'' . $this->allowedPrefixes[0] . '\'';
				// If there are no prefixes specified, don't do anything
				} elseif ( $this->allowedPrefixes === [] ) {
					return;
				} else {
					// Build a list of forged valid global variable names
					$expected = 'one of "$'
						. implode( ucfirst( substr( $globalName, 1 ) . '", "$' ), $this->allowedPrefixes )
						. ucfirst( substr( $globalName, 1 ) )
						. '"';

					// Build message telling you which prefixes are allowed
					$allowedPrefix = 'one of \''
						. implode( '\', \'', $this->allowedPrefixes )
						. '\'';
				}

				// Verify global is prefixed with an allowed prefix
				if ( !in_array( substr( $globalName, 1, 2 ), $this->allowedPrefixes ) ) {
					$phpcsFile->addError(
						'Global variable "%s" is lacking an allowed prefix (%s). Should be "%s".',
						$stackPtr,
						'allowedPrefix',
						[ $globalName, $allowedPrefix, $expected ]
					);
				} else {
					// Verify global is probably CamelCase
					$val = ord( substr( $globalName, 3, 1 ) );
					if ( !( $val >= 65 && $val <= 90 ) && !( $val >= 48 && $val <= 57 ) ) {
						$phpcsFile->addError(
							'Global variable "%s" should use CamelCase: "%s"',
							$stackPtr,
							'CamelCase',
							[ $globalName, $expected ]
						);
					}
				}
			}
			$nameIndex++;
		}
	}
}
