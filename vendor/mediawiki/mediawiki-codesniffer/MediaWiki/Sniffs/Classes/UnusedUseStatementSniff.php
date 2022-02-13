<?php
/**
 * Originally from Drupal's coding standard <https://github.com/klausi/coder>
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

namespace MediaWiki\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * @author Thiemo Kreuz
 */
class UnusedUseStatementSniff implements Sniff {

	/**
	 * Doc tags where a class name is used
	 */
	private const CLASS_TAGS = [
		'@param' => null,
		'@property' => null,
		'@property-read' => null,
		'@property-write' => null,
		'@return' => null,
		'@see' => null,
		'@throws' => null,
		'@var' => null,
		// Deprecated
		'@expectedException' => null,
		'@method' => null,
		'@type' => null,
	];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_USE ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 *
	 * @return int|void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		$tokens = $phpcsFile->getTokens();

		// Only check use statements in the global scope.
		if ( !empty( $tokens[$stackPtr]['conditions'] ) ) {
			// TODO: Use array_key_first() if available
			$scope = key( $tokens[$stackPtr]['conditions'] );
			// This avoids checking other use keywords (in traits and closures) in the same scope
			return $tokens[$scope]['scope_closer'] ?? $stackPtr;
		}

		$afterUseSection = $stackPtr;
		$shortClassNames = $this->findUseStatements( $phpcsFile, $stackPtr, $afterUseSection );
		if ( !$shortClassNames ) {
			return;
		}

		$classNamesPattern = '{(?<!\\\\)\b('
			. implode( '|', array_map( 'preg_quote', array_keys( $shortClassNames ) ) )
			. ')\b}i';

		// Search where the class name is used. PHP treats class names case
		// insensitive, that's why we cannot search for the exact class name string
		// and need to iterate over all T_STRING tokens in the file.
		for ( $i = $afterUseSection; $i < $phpcsFile->numTokens; $i++ ) {
			if ( $tokens[$i]['code'] === T_RETURN_TYPE ) {
				// If the name is used in a PHP 7 function return type declaration
				$className = $tokens[$i]['content'];

			} elseif ( $tokens[$i]['code'] === T_STRING ) {
				if ( !isset( $shortClassNames[ strtolower( $tokens[$i]['content'] ) ] ) ) {
					continue;
				}

				// If a backslash is used before the class name then this is some other
				// use statement.
				// T_STRING also used for $this->property or self::function()
				$before = $phpcsFile->findPrevious( Tokens::$emptyTokens, $i - 1, null, true );
				if ( $tokens[$before]['code'] === T_OBJECT_OPERATOR
					|| $tokens[$before]['code'] === T_NULLSAFE_OBJECT_OPERATOR
					|| $tokens[$before]['code'] === T_DOUBLE_COLON
					|| $tokens[$before]['code'] === T_NS_SEPARATOR
					// Trait use statement within a class.
					|| ( $tokens[$before]['code'] === T_USE
						&& empty( $tokens[$before]['conditions'] )
					)
				) {
					continue;
				}

				$className = $tokens[$i]['content'];

			} elseif ( $tokens[$i]['code'] === T_DOC_COMMENT_TAG ) {
				// Usage in a doc comment
				if ( !array_key_exists( $tokens[$i]['content'], self::CLASS_TAGS )
					|| $tokens[$i + 2]['code'] !== T_DOC_COMMENT_STRING
					|| !preg_match( '/^(?:\$\w+\h+)?(\S+)(?=\s|$)/', $tokens[$i + 2]['content'], $matches )
				) {
					continue;
				}

				// We aren't interested in the later, whitespace-separated parts of comments
				// like `@param (Class1|Class2)[]|Class3<Class4,Class5> $var Description`.
				$docType = $matches[1];
				if ( !preg_match_all( $classNamesPattern, $docType, $matches ) ) {
					continue;
				}
				$className = $matches[1];

			} elseif ( $tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING ) {
				// Ensure class name is followed by a space so that we know its the
				// end of the class name given to phan
				if ( $tokens[$i + 1]['code'] !== T_SEMICOLON
					|| !preg_match( '/\W@phan-var\S*\s+(\S+)\s/i', $tokens[$i]['content'], $matches )
				) {
					continue;
				}

				$phanVarType = $matches[1];
				if ( !preg_match_all( $classNamesPattern, $phanVarType, $matches ) ) {
					continue;
				}
				$className = $matches[1];

			} else {
				continue;
			}

			$this->markAsUsed( $shortClassNames, $className );
			if ( $shortClassNames === [] ) {
				break;
			}
		}

		foreach ( $shortClassNames as $i ) {
			$fix = $phpcsFile->addFixableWarning( 'Unused use statement', $i, 'UnusedUse' );
			if ( $fix ) {
				$this->removeUseStatement( $phpcsFile, $i );
			}
		}

		return $afterUseSection;
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @param int &$afterUseSection Updated to point to the first token after the found section
	 *
	 * @return int[] Array mapping short, lowercased class names to stack pointers
	 */
	private function findUseStatements(
		File $phpcsFile,
		int $stackPtr,
		int &$afterUseSection
	) : array {
		$tokens = $phpcsFile->getTokens();
		$currentUsePtr = $stackPtr;

		$namespace = $this->findNamespace( $phpcsFile, $stackPtr );
		$shortClassNames = [];

		// No need to cache this as we won't execute this often
		$namespaceTokenTypes = Tokens::$emptyTokens;
		$namespaceTokenTypes[] = T_NS_SEPARATOR;
		$namespaceTokenTypes[] = T_STRING;
		$useTokenTypes = array_merge( $namespaceTokenTypes, [ T_AS ] );

		while ( $currentUsePtr && $tokens[$currentUsePtr]['code'] === T_USE ) {
			// Seek to the end of the statement and get the string before the semi colon.
			$semicolon = $phpcsFile->findNext( $useTokenTypes, $currentUsePtr + 1, null, true );
			if ( $tokens[$semicolon]['code'] !== T_SEMICOLON ) {
				break;
			}
			$afterUseSection = $semicolon + 1;

			// Find the unprefixed class name or "as" alias, if there is one
			$classNamePtr = $phpcsFile->findPrevious( T_STRING, $semicolon - 1, $currentUsePtr );
			if ( !$classNamePtr ) {
				break;
			}
			$shortClassNames[ strtolower( $tokens[$classNamePtr]['content'] ) ] = $currentUsePtr;

			// Check if the referenced class is in the same namespace as the current
			// file. If it is then the use statement is not necessary.
			$prev = $phpcsFile->findPrevious( $namespaceTokenTypes, $classNamePtr - 1, null, true );
			// Check if the use statement does aliasing with the "as" keyword. Aliasing
			// is allowed even in the same namespace.
			if ( $tokens[$prev]['code'] !== T_AS ) {
				$useNamespace = $this->readNamespace( $phpcsFile, $prev + 1, $classNamePtr - 2 );
				if ( $useNamespace === $namespace ) {
					$this->addSameNamespaceWarning( $phpcsFile, $currentUsePtr );
				}
			}

			// This intentionally stops at non-empty tokens for performance reasons, and might miss
			// later use statements. The sniff will be called another time for these.
			$currentUsePtr = $phpcsFile->findNext( Tokens::$emptyTokens, $semicolon + 1, null, true );
		}

		return $shortClassNames;
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 *
	 * @return string
	 */
	private function findNamespace( File $phpcsFile, int $stackPtr ) : string {
		// There are probably less tokens between the start of the file and the namespace token
		$namespacePtr = $phpcsFile->findNext( T_NAMESPACE, 1, $stackPtr - 1 );
		if ( !$namespacePtr ) {
			return '';
		}

		return $this->readNamespace( $phpcsFile, $namespacePtr + 2, $stackPtr - 1 );
	}

	/**
	 * @param File $phpcsFile
	 * @param int $start
	 * @param int $end
	 *
	 * @return string
	 */
	private function readNamespace( File $phpcsFile, int $start, int $end ) : string {
		$tokens = $phpcsFile->getTokens();
		$content = '';

		for ( $i = $start; $i <= $end; $i++ ) {
			if ( isset( Tokens::$emptyTokens[ $tokens[$i]['code'] ] ) ) {
				continue;
			}
			if ( $tokens[$i]['code'] !== T_STRING && $tokens[$i]['code'] !== T_NS_SEPARATOR ) {
				break;
			}

			// This skips leading separators as well as a preceding "const" or "function"
			if ( $content || ( $tokens[$i]['code'] === T_STRING && (
				strcasecmp( $tokens[$i]['content'], 'const' ) !== 0 &&
				strcasecmp( $tokens[$i]['content'], 'function' ) !== 0
			) ) ) {
				$content .= $tokens[$i]['content'];
			}
		}

		// Something like "Namespace\ Class" might leave a trailing separator
		return rtrim( $content, '\\' );
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 */
	private function addSameNamespaceWarning( File $phpcsFile, int $stackPtr ) {
		$warning = 'Unnecessary use statement in the same namespace';
		$fix = $phpcsFile->addFixableWarning( $warning, $stackPtr, 'UnnecessaryUse' );
		if ( $fix ) {
			$this->removeUseStatement( $phpcsFile, $stackPtr );
		}
	}

	/**
	 * @param array &$classNames List of class names found in the use section
	 * @param string|string[] $usedClassNames Class name(s) to be marked as used
	 */
	private function markAsUsed( array &$classNames, $usedClassNames ) {
		foreach ( (array)$usedClassNames as $className ) {
			unset( $classNames[ strtolower( $className ) ] );
		}
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 */
	private function removeUseStatement( File $phpcsFile, int $stackPtr ) {
		$tokens = $phpcsFile->getTokens();
		// Remove the whole use statement line.
		$phpcsFile->fixer->beginChangeset();

		$i = $stackPtr;
		do {
			$phpcsFile->fixer->replaceToken( $i, '' );
			$i++;
		} while ( isset( $tokens[$i] ) && $tokens[$i]['code'] !== T_SEMICOLON );

		// Also remove whitespace after the semicolon (new lines).
		while ( isset( $tokens[$i] )
			&& $tokens[$i]['code'] === T_WHITESPACE
			&& $tokens[$i]['line'] === $tokens[$i - 1]['line']
		) {
			$phpcsFile->fixer->replaceToken( $i, '' );
			$i++;
		}

		$phpcsFile->fixer->endChangeset();
	}

}
