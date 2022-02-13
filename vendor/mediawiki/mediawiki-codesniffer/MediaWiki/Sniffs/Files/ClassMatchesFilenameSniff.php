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

namespace MediaWiki\Sniffs\Files;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ClassMatchesFilenameSniff implements Sniff {

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_CLASS, T_INTERFACE, T_TRAIT ];
	}

	/**
	 * Check the class name against the filename
	 * This check is only done once, the rest of the file is always ignored.
	 *
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @return int
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$fname = $phpcsFile->getFilename();
		if ( $fname === 'STDIN' ) {
			return $phpcsFile->numTokens;
		}

		$base = basename( $fname );
		$name = $phpcsFile->getDeclarationName( $stackPtr );
		if ( $base !== "$name.php" ) {
			$wrongCase = strcasecmp( $base, "$name.php" ) === 0;
			if ( $wrongCase && $this->isMaintenanceScript( $phpcsFile ) ) {
				// Maintenance scripts follow the class name, but the first
				// letter is lowercase.
				$expected = lcfirst( $name );
				if ( $base === "$expected.php" ) {
					// OK!
					return $phpcsFile->numTokens;
				}
			}
			$phpcsFile->addError(
				'Class name \'%s\' does not match filename \'%s\'',
				$stackPtr,
				$wrongCase ? 'WrongCase' : 'NotMatch',
				[ $name, $base ]
			);
		}

		return $phpcsFile->numTokens;
	}

	/**
	 * Figure out whether the file is a MediaWiki maintenance script
	 *
	 * @param File $phpcsFile
	 *
	 * @return bool
	 */
	private function isMaintenanceScript( File $phpcsFile ) {
		$tokens = $phpcsFile->getTokens();

		// Per convention the line we are looking for is the last in all maintenance scripts
		for ( $i = $phpcsFile->numTokens; $i--; ) {
			if ( $tokens[$i]['level'] !== 0 ) {
				// Only look into the global scope
				return false;
			}
			if ( $tokens[$i]['code'] === T_STRING
				&& $tokens[$i]['content'] === 'RUN_MAINTENANCE_IF_MAIN'
			) {
				return true;
			}
		}

		return false;
	}

}
