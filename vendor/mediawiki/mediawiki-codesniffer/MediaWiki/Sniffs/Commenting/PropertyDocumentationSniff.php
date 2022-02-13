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
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class PropertyDocumentationSniff implements Sniff {

	/**
	 * Mapping for swap short types
	 */
	private const SHORT_TYPE_MAPPING = [
		'boolean' => 'bool',
		'boolean[]' => 'bool[]',
		'integer' => 'int',
		'integer[]' => 'int[]',
	];

	/**
	 * Mapping for primitive types to case correct
	 * Cannot just detect case due to classes being uppercase
	 *
	 * @var string[]
	 */
	private const PRIMITIVE_TYPE_MAPPING = [
		'Array' => 'array',
		'Array[]' => 'array[]',
		'Bool' => 'bool',
		'Bool[]' => 'bool[]',
		'Float' => 'float',
		'Float[]' => 'float[]',
		'Int' => 'int',
		'Int[]' => 'int[]',
		'Mixed' => 'mixed',
		'Mixed[]' => 'mixed[]',
		'Null' => 'null',
		'Null[]' => 'null[]',
		'Object' => 'object',
		'Object[]' => 'object[]',
		'String' => 'string',
		'String[]' => 'string[]',
		'Callable' => 'callable',
		'Callable[]' => 'callable[]',
	];

	/**
	 * @inheritDoc
	 */
	public function register() {
		return [ T_VARIABLE ];
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		if ( substr( $phpcsFile->getFilename(), -8 ) === 'Test.php' ) {
			// Don't check documentation for test cases
			return;
		}
		$tokens = $phpcsFile->getTokens();

		// Only for class properties
		$scopes = array_keys( $tokens[$stackPtr]['conditions'] );
		$scope = array_pop( $scopes );
		if ( isset( $tokens[$stackPtr]['nested_parenthesis'] )
			|| $scope === null
			|| ( $tokens[$scope]['code'] !== T_CLASS && $tokens[$scope]['code'] !== T_TRAIT )
		) {
			return;
		}

		$find = Tokens::$scopeModifiers;
		$find[] = T_WHITESPACE;
		$find[] = T_STATIC;
		$find[] = T_VAR;
		$find[] = T_NULLABLE;
		$find[] = T_STRING;
		$commentEnd = $phpcsFile->findPrevious( $find, $stackPtr - 1, null, true );
		if ( $tokens[$commentEnd]['code'] === T_COMMENT ) {
			// Inline comments might just be closing comments for
			// control structures or functions instead of function comments
			// using the wrong comment type. If there is other code on the line,
			// assume they relate to that code.
			$prev = $phpcsFile->findPrevious( $find, $commentEnd - 1, null, true );
			if ( $prev !== false && $tokens[$prev]['line'] === $tokens[$commentEnd]['line'] ) {
				$commentEnd = $prev;
			}
		}
		if ( $tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG
			&& $tokens[$commentEnd]['code'] !== T_COMMENT
		) {
			$methodProps = $phpcsFile->getMemberProperties( $stackPtr );
			$phpcsFile->addError(
				'Missing class property doc comment',
				$stackPtr,
				// Messages used: MissingDocumentationPublic, MissingDocumentationProtected,
				// MissingDocumentationPrivate
				'MissingDocumentation' . ucfirst( $methodProps['scope'] )
			);
			return;
		}
		if ( $tokens[$commentEnd]['code'] === T_COMMENT ) {
			$phpcsFile->addError( 'You must use "/**" style comments for a class property comment',
			$stackPtr, 'WrongStyle' );
			return;
		}
		if ( $tokens[$commentEnd]['line'] !== $tokens[$stackPtr]['line'] - 1 ) {
			$error = 'There must be no blank lines after the class property comment';
			$phpcsFile->addError( $error, $commentEnd, 'SpacingAfter' );
		}
		$commentStart = $tokens[$commentEnd]['comment_opener'];
		foreach ( $tokens[$commentStart]['comment_tags'] as $tag ) {
			$tagText = $tokens[$tag]['content'];
			if ( strcasecmp( $tagText, '@inheritDoc' ) === 0 || $tagText === '@deprecated' ) {
				// No need to validate deprecated properties or those that inherit
				// their documentation
				return;
			}
		}

		$this->processVar( $phpcsFile, $commentStart );
	}

	/**
	 * Process the var doc comments.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $commentStart The position in the stack where the comment started.
	 */
	private function processVar( File $phpcsFile, $commentStart ) {
		$tokens = $phpcsFile->getTokens();
		$var = null;
		foreach ( $tokens[$commentStart]['comment_tags'] as $ptr ) {
			$tag = $tokens[$ptr]['content'];
			if ( $tag !== '@var' ) {
				continue;
			}
			if ( $var ) {
				$error = 'Only 1 @var tag is allowed in a class property comment';
				$phpcsFile->addError( $error, $ptr, 'DuplicateVar' );
				return;
			}
			$var = $ptr;
		}
		if ( $var !== null ) {
			$varTypeSpacing = $var + 1;
			if ( $tokens[$varTypeSpacing]['code'] === T_DOC_COMMENT_WHITESPACE ) {
				$expectedSpaces = 1;
				$currentSpaces = strlen( $tokens[$varTypeSpacing]['content'] );
				if ( $currentSpaces !== $expectedSpaces ) {
					$fix = $phpcsFile->addFixableWarning(
						'Expected %s spaces before var type; %s found',
						$varTypeSpacing,
						'SpacingBeforeVarType',
						[ $expectedSpaces, $currentSpaces ]
					);
					if ( $fix ) {
						$phpcsFile->fixer->replaceToken( $varTypeSpacing, ' ' );
					}
				}
			}
			$varType = $var + 2;
			$content = '';
			if ( $tokens[$varType]['code'] === T_DOC_COMMENT_STRING ) {
				$content = $tokens[$varType]['content'];
			}
			if ( $content === '' ) {
				$error = 'Var type missing for @var tag in class property comment';
				$phpcsFile->addError( $error, $var, 'MissingvarType' );
				return;
			}
			// The first word of the var type is the actual type
			$exploded = explode( ' ', $content, 2 );
			$type = $exploded[0];
			$comment = $exploded[1] ?? null;
			$fixType = false;
			// Check for unneeded punctation
			$type = $this->fixTrailingPunctation(
				$phpcsFile,
				$varType,
				$type,
				$fixType,
				'var type'
			);
			$type = $this->fixWrappedParenthesis(
				$phpcsFile,
				$varType,
				$type,
				$fixType,
				'var type'
			);
			// Check the type for short types
			$type = $this->fixShortTypes( $phpcsFile, $varType, $type, $fixType, 'var' );
			// Check spacing after type
			if ( $comment !== null ) {
				$expectedSpaces = 1;
				$currentSpaces = strspn( $comment, ' ' ) + 1;
				if ( $currentSpaces !== $expectedSpaces ) {
					$fix = $phpcsFile->addFixableWarning(
						'Expected %s spaces after var type; %s found',
						$varType,
						'SpacingAfterVarType',
						[ $expectedSpaces, $currentSpaces ]
					);
					if ( $fix ) {
						$fixType = true;
						$comment = substr( $comment, $currentSpaces - 1 );
					}
				}
			}
			if ( $fixType ) {
				$phpcsFile->fixer->replaceToken(
					$varType,
					$type . ( $comment !== null ? ' ' . $comment : '' )
				);
			}
		} else {
			$error = 'Missing @var tag in class property comment';
			$phpcsFile->addError( $error, $tokens[$commentStart]['comment_closer'], 'MissingVar' );
		}
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @param string $typesString
	 * @param bool &$fix Set when autofix is needed
	 * @param string $annotation Either "param" or "return"
	 * @return string Updated $typesString
	 */
	private function fixShortTypes( File $phpcsFile, $stackPtr, $typesString, &$fix, $annotation ) {
		$typeList = explode( '|', $typesString );
		foreach ( $typeList as &$type ) {
			// Corrects long types from both upper and lowercase to lowercase shorttype
			$key = lcfirst( $type );
			if ( isset( self::SHORT_TYPE_MAPPING[$key] ) ) {
				$type = self::SHORT_TYPE_MAPPING[$key];
				$code = 'NotShort' . str_replace( '[]', 'Array', ucfirst( $type ) ) . ucfirst( $annotation );
				$fix = $phpcsFile->addFixableError(
					'Short type of "%s" should be used for @%s tag',
					$stackPtr,
					$code,
					[ $type, $annotation ]
				) || $fix;
			} elseif ( isset( self::PRIMITIVE_TYPE_MAPPING[$type] ) ) {
				$type = self::PRIMITIVE_TYPE_MAPPING[$type];
				$code = 'UppercasePrimitive' . str_replace( '[]', 'Array', ucfirst( $type ) ) . ucfirst( $annotation );
				$fix = $phpcsFile->addFixableError(
					'Lowercase type of "%s" should be used for @%s tag',
					$stackPtr,
					$code,
					[ $type, $annotation ]
				) || $fix;
			}
		}
		return implode( '|', $typeList );
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @param string $typesString
	 * @param bool &$fix Set when autofix is needed
	 * @param string $annotation Either "param" or "return" + "name" or "type"
	 * @return string Updated $typesString
	 */
	private function fixTrailingPunctation( File $phpcsFile, $stackPtr, $typesString, &$fix, $annotation ) {
		if ( preg_match( '/^(.*)((?:(?![\[\]_{}])\p{P})+)$/', $typesString, $matches ) ) {
			$typesString = $matches[1];
			$fix = $phpcsFile->addFixableError(
				'%s should not end with punctuation "%s"',
				$stackPtr,
				'NotPunctuation' . str_replace( ' ', '', ucwords( $annotation ) ),
				[ ucfirst( $annotation ), $matches[2] ]
			) || $fix;
		}
		return $typesString;
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @param string $typesString
	 * @param bool &$fix Set when autofix is needed
	 * @param string $annotation Either "param" or "return" + "name" or "type"
	 * @return string Updated $typesString
	 */
	private function fixWrappedParenthesis( File $phpcsFile, $stackPtr, $typesString, &$fix, $annotation ) {
		if ( preg_match( '/^([{\[]+)(.*)([\]}]+)$/', $typesString, $matches ) ) {
			$typesString = $matches[2];
			$fix = $phpcsFile->addFixableError(
				'%s should not be wrapped in parenthesis; %s and %s found',
				$stackPtr,
				'NotParenthesis' . str_replace( ' ', '', ucwords( $annotation ) ),
				[ ucfirst( $annotation ), $matches[1], $matches[3] ]
			) || $fix;
		}
		return $typesString;
	}

}
