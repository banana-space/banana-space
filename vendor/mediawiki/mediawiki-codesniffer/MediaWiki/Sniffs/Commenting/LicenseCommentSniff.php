<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Sniffs\Commenting;

use Composer\Spdx\SpdxLicenses;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class LicenseCommentSniff implements Sniff {

	/** @var SpdxLicenses */
	private static $licenses = null;

	/**
	 * Common auto-fixable replacements
	 */
	private const REPLACEMENTS = [
		'GNU General Public Licen[sc]e 2(\.0)? or later' => 'GPL-2.0-or-later',
		'GNU GPL v2\+' => 'GPL-2.0-or-later',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register() {
		return [ T_DOC_COMMENT_OPEN_TAG ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		$end = $tokens[$stackPtr]['comment_closer'];
		foreach ( $tokens[$stackPtr]['comment_tags'] as $tag ) {
			$this->processDocTag( $phpcsFile, $tokens, $tag, $end );
		}
	}

	/**
	 * @param File $phpcsFile
	 * @param array[] $tokens
	 * @param int $tag Token position of the tag
	 * @param int $end Token position of the end of the doc comment
	 */
	private function processDocTag( File $phpcsFile, array $tokens, $tag, $end ) {
		$tagText = $tokens[$tag]['content'];

		if ( $tagText === '@licence' ) {
			$fix = $phpcsFile->addFixableWarning(
				'Incorrect wording of @license', $tag, 'LicenceTag'
			);
			if ( $fix ) {
				$phpcsFile->fixer->replaceToken( $tag, '@license' );
			}
		} elseif ( $tagText !== '@license' ) {
			return;
		}

		if ( $tokens[$tag]['level'] !== 0 ) {
			$phpcsFile->addWarning(
				'@license should only be used on the top level',
				$tag, 'LicenseTagNonFileComment'
			);
		}

		// It is okay to have more than one @license

		// Validate text behind @license
		$next = $phpcsFile->findNext( [ T_DOC_COMMENT_WHITESPACE ], $tag + 1, $end, true );
		if ( $tokens[$next]['code'] !== T_DOC_COMMENT_STRING ) {
			$phpcsFile->addWarning(
				'@license not followed by a license',
				$tag, 'LicenseTagEmpty'
			);
			return;
		}
		$license = $tokens[$next]['content'];

		// @license can contain a url, use the text behind it
		$m = [];
		if ( preg_match( '/^https?:\/\/[^\s]+\s+(.*)/', $license, $m ) ) {
			$license = $m[1];
		}

		$licenseValidator = self::getLicenseValidator();
		if ( !$licenseValidator->validate( $license ) ) {
			$fixable = null;
			foreach ( self::REPLACEMENTS as $regex => $identifier ) {
				// Make sure the entire license matches the regex, and
				// then a sanity check that the new replacement is valid too
				if ( preg_match( "/^$regex$/", $license ) === 1
					&& $licenseValidator->validate( $identifier )
				) {
					$fixable = $identifier;
					break;
				}
			}
			if ( $fixable !== null ) {
				$fix = $phpcsFile->addFixableWarning(
					'Invalid SPDX license identifier "%s", see <https://spdx.org/licenses/>',
					$tag, 'InvalidLicenseTag', [ $license ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->replaceToken( $next, $fixable );
				}
			} else {
				$phpcsFile->addWarning(
					'Invalid SPDX license identifier "%s", see <https://spdx.org/licenses/>',
					$tag, 'InvalidLicenseTag', [ $license ]
				);
			}
		} else {
			// Split list to check each license for deprecation
			$singleLicenses = preg_split( '/\s+(?:AND|OR)\s+/i', $license );
			foreach ( $singleLicenses as $singleLicense ) {
				// Check if the splitted license is known to the validator - T195429
				if ( !is_array( $licenseValidator->getLicenseByIdentifier( $singleLicense ) ) ) {
					// @codeCoverageIgnoreStart
					$phpcsFile->addWarning(
						'An error occurred during processing SPDX license identifier "%s"',
						$tag, 'ErrorLicenseTag', [ $license ]
					);
					break;
					// @codeCoverageIgnoreEnd
				}
				if ( $licenseValidator->isDeprecatedByIdentifier( $singleLicense ) ) {
					$phpcsFile->addWarning(
						'Deprecated SPDX license identifier "%s", see <https://spdx.org/licenses/>',
						$tag, 'DeprecatedLicenseTag', [ $singleLicense ]
					);
				}
			}
		}
	}

	/**
	 * @return SpdxLicenses
	 */
	private static function getLicenseValidator() {
		if ( self::$licenses === null ) {
			self::$licenses = new SpdxLicenses();
		}
		return self::$licenses;
	}

}
