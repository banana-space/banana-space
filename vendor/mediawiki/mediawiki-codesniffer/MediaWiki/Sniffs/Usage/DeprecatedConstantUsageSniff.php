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

namespace MediaWiki\Sniffs\Usage;

use MediaWiki\Sniffs\Utils\ExtensionInfo;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DeprecatedConstantUsageSniff implements Sniff {

	/**
	 * Deprecated constant => Replacement, and last
	 * MW version that old constant should still be used
	 */
	private const DEPRECATED_CONSTANTS = [
		'DB_SLAVE' => [
			'replace' => 'DB_REPLICA',
			'version' => '1.27.3',
		],
		'NS_IMAGE' => [
			'replace' => 'NS_FILE',
			'version' => '1.13',
		],
		'NS_IMAGE_TALK' => [
			'replace' => 'NS_FILE_TALK',
			'version' => '1.13',
		],
		'DO_MAINTENANCE' => [
			'replace' => 'RUN_MAINTENANCE_IF_MAIN',
			'version' => '1.16.3',
		]
	];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [
			T_STRING,
		];
	}

	/**
	 * Check for any deprecated constants
	 *
	 * @param File $phpcsFile Current file
	 * @param int $stackPtr Position
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$token = $phpcsFile->getTokens()[$stackPtr];
		$current = $token['content'];
		if ( isset( self::DEPRECATED_CONSTANTS[$current] ) ) {
			$extensionInfo = ExtensionInfo::newFromFile( $phpcsFile );
			if ( $extensionInfo->supportsMediaWiki( self::DEPRECATED_CONSTANTS[$current]['version'] ) ) {
				return;
			}
			$fix = $phpcsFile->addFixableWarning(
				'Deprecated constant %s used',
				$stackPtr,
				$current,
				[ $current ]
			);
			if ( $fix ) {
				$phpcsFile->fixer->replaceToken( $stackPtr, self::DEPRECATED_CONSTANTS[$current]['replace'] );
			}
		}
	}
}
