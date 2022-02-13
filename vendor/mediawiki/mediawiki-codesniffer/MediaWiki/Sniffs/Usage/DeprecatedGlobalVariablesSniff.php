<?php
/**
 * Detect use of deprecated global variables.
 *
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

namespace MediaWiki\Sniffs\Usage;

use MediaWiki\Sniffs\Utils\ExtensionInfo;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DeprecatedGlobalVariablesSniff implements Sniff {

	/**
	 * Deprecated global and last
	 * MW version that old global can still be used
	 */
	private const DEPRECATED_GLOBALS = [
		// Deprecation done (T89459)
		'$wgAuth' => '1.27',
		// Deprecation done (T160815)
		'$wgContLang' => '1.32',
		// Deprecation done (T160811)
		'$wgParser' => '1.32',
		// Deprecation done (T159284)
		'$wgTitle' => '1.19',
		// Deprecation done (no task)
		'$parserMemc' => '1.30',
		// Deprecation done (T160813)
		'$wgMemc' => '1.35',
		// Deprecation done (T159299)
		'$wgUser' => '1.35',
		// Deprecation done (T212738)
		'$wgVersion' => '1.35',

		// Deprecation planned (T212739)
		// '$wgConf' => '',
		// Deprecation planned (T160814)
		// '$wgLang' => '',
		// Deprecation planned (T160812)
		// '$wgOut' => '',
		// Deprecation planned (T160810)
		// '$wgRequest' => '',
	];

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

		$next = $stackPtr++;
		$endOfGlobal = $phpcsFile->findEndOfStatement( $next, T_COMMA );
		$extensionInfo = ExtensionInfo::newFromFile( $phpcsFile );

		for ( ; $next < $endOfGlobal; $next++ ) {
			if ( $tokens[$next]['code'] !== T_VARIABLE ) {
				continue;
			}

			$globalVar = $tokens[$next]['content'];
			if ( !isset( self::DEPRECATED_GLOBALS[$globalVar] ) ||
				$extensionInfo->supportsMediaWiki( self::DEPRECATED_GLOBALS[$globalVar] )
			) {
				continue;
			}

			$phpcsFile->addWarning(
				"Deprecated global $globalVar used",
				$next,
				'Deprecated' . $globalVar
			);
		}
	}
}
