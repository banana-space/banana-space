<?php

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Custom sniff that requires all classes, interfaces, and traits in a codebase to have the same
 * license doc tag. Note this sniff doesn't do anything by default. You need to enable it in your
 * .phpcs.xml if you want to use it:
 * <rule ref="MediaWiki.Commenting.ClassLevelLicense">
 *     <properties>
 *         <property name="license" value="GPL-2.0-or-later" />
 *     </properties>
 * </rule>
 *
 * Doing so makes the LicenseComment sniff obsolete. You might want to disable it:
 * <exclude name="MediaWiki.Commenting.LicenseComment" />
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class ClassLevelLicenseSniff implements Sniff {

	/**
	 * @var string Typically "GPL-2.0-or-later", empty by default
	 */
	public $license = '';

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_CLASS, T_INTERFACE, T_TRAIT ];
	}

	/**
	 * @inheritDoc
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		// This sniff requires you to set a <property name="license" …> in your .phpcs.xml
		if ( !$this->license ) {
			return;
		}

		$tokens = $phpcsFile->getTokens();

		// All auto-fixes below assume we are on the top level
		if ( $tokens[$stackPtr]['level'] !== 0 ) {
			return;
		}

		$skip = Tokens::$methodPrefixes;
		$skip[] = T_WHITESPACE;
		$closer = $phpcsFile->findPrevious( $skip, $stackPtr - 1, null, true );

		if ( $tokens[$closer]['code'] !== T_DOC_COMMENT_CLOSE_TAG ) {
			$fix = $phpcsFile->addFixableError(
				'All code in this codebase should have a @license tag',
				$stackPtr,
				'Missing'
			);
			if ( $fix ) {
				$phpcsFile->fixer->addContentBefore(
					$stackPtr,
					"/**\n * @license $this->license\n */\n"
				);
			}
			return;
		}

		if ( !isset( $tokens[$closer]['comment_opener'] ) ) {
			return;
		}

		$opener = $tokens[$closer]['comment_opener'];
		foreach ( $tokens[$opener]['comment_tags'] as $ptr ) {
			$tag = $tokens[$ptr]['content'];
			if ( strncasecmp( $tag, '@licen', 6 ) !== 0 ) {
				continue;
			}

			if ( !isset( $tokens[$ptr + 2] )
				|| $tokens[$ptr + 2]['code'] !== T_DOC_COMMENT_STRING
			) {
				$fix = $phpcsFile->addFixableError(
					'All code in this codebase should be tagged with "%s %s", found empty "%s" instead',
					$ptr,
					'Empty',
					[ $tag, $this->license, $tag ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->addContent( $ptr, " $this->license" );
				}
			} elseif ( $tokens[$ptr + 2]['content'] !== $this->license ) {
				$fix = $phpcsFile->addFixableError(
					'All code in this codebase should be tagged with "%s %s", found "%s %s" instead',
					$ptr + 2,
					'WrongLicense',
					[ $tag, $this->license, $tag, $tokens[$ptr + 2]['content'] ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->replaceToken( $ptr + 2, $this->license );
				}
			}

			// This sniff intentionally checks the first @license tag only
			return;
		}

		$fix = $phpcsFile->addFixableError(
			'All code in this codebase should have a @license tag',
			$opener,
			'Missing'
		);
		if ( $fix ) {
			$phpcsFile->fixer->addContentBefore( $closer, "* @license $this->license\n " );
		}
	}

}
