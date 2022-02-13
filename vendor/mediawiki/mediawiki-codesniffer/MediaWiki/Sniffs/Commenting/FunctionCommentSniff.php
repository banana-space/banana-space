<?php
/**
 * This file was copied from PHP_CodeSniffer before being modified
 * File: Standards/PEAR/Sniffs/Commenting/FunctionCommentSniff.php
 * From repository: https://github.com/squizlabs/PHP_CodeSniffer
 *
 * Parses and verifies the doc comments for functions.
 *
 * PHP version 5
 *
 * @category PHP
 * @package PHP_CodeSniffer
 * @author Greg Sherwood <gsherwood@squiz.net>
 * @author Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD-3-Clause
 * @link http://pear.php.net/package/PHP_CodeSniffer
 */

namespace MediaWiki\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class FunctionCommentSniff implements Sniff {

	/**
	 * Standard class methods that
	 * don't require documentation
	 */
	private const SKIP_STANDARD_METHODS = [
		'__toString', '__destruct',
		'__sleep', '__wakeup',
		'__clone'
	];

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
		return [ T_FUNCTION ];
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

		$funcName = $phpcsFile->getDeclarationName( $stackPtr );
		if ( $funcName === null || in_array( $funcName, self::SKIP_STANDARD_METHODS ) ) {
			// Don't require documentation for an obvious method
			return;
		}

		$tokens = $phpcsFile->getTokens();
		$find = Tokens::$methodPrefixes;
		$find[] = T_WHITESPACE;
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
			// Don't require documentation for functions with no parameters, except getters
			if ( substr( $funcName, 0, 3 ) === 'get' || $phpcsFile->getMethodParameters( $stackPtr ) ) {
				$methodProps = $phpcsFile->getMethodProperties( $stackPtr );
				$phpcsFile->addError(
					'Missing function doc comment',
					$stackPtr,
					// Messages used: MissingDocumentationPublic, MissingDocumentationProtected,
					// MissingDocumentationPrivate
					'MissingDocumentation' . ucfirst( $methodProps['scope'] )
				);
			}
			return;
		}
		if ( $tokens[$commentEnd]['code'] === T_COMMENT ) {
			$phpcsFile->addError( 'You must use "/**" style comments for a function comment',
			$stackPtr, 'WrongStyle' );
			return;
		}
		if ( $tokens[$commentEnd]['line'] !== $tokens[$stackPtr]['line'] - 1 ) {
			$error = 'There must be no blank lines after the function comment';
			$phpcsFile->addError( $error, $commentEnd, 'SpacingAfter' );
		}
		$commentStart = $tokens[$commentEnd]['comment_opener'];

		foreach ( $tokens[$commentStart]['comment_tags'] as $tag ) {
			$tagText = $tokens[$tag]['content'];
			if ( strcasecmp( $tagText, '@inheritDoc' ) === 0 || $tagText === '@deprecated' ) {
				// No need to validate deprecated functions or those that inherit
				// their documentation
				return;
			}
		}

		$this->processReturn( $phpcsFile, $stackPtr, $commentStart );
		$this->processThrows( $phpcsFile, $commentStart );
		$this->processParams( $phpcsFile, $stackPtr, $commentStart );
	}

	/**
	 * Process the return comment of this function comment.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 * @param int $commentStart The position in the stack where the comment started.
	 */
	protected function processReturn( File $phpcsFile, $stackPtr, $commentStart ) {
		$tokens = $phpcsFile->getTokens();
		// Return if no scope_opener.
		if ( !isset( $tokens[$stackPtr]['scope_opener'] ) ) {
			return;
		}

		// Skip constructors
		if ( $phpcsFile->getDeclarationName( $stackPtr ) === '__construct' ) {
			return;
		}

		$endFunction = $tokens[$stackPtr]['scope_closer'];
		$found = false;
		for ( $i = $endFunction - 1; $i > $stackPtr; $i-- ) {
			$token = $tokens[$i];
			if ( isset( $token['scope_condition'] ) && (
				$tokens[$token['scope_condition']]['code'] === T_CLOSURE ||
				$tokens[$token['scope_condition']]['code'] === T_FUNCTION ||
				$tokens[$token['scope_condition']]['code'] === T_ANON_CLASS
			) ) {
				// Skip to the other side of the closure/inner function and continue
				$i = $token['scope_condition'];
				continue;
			}
			if ( $token['code'] === T_RETURN ) {
				if ( isset( $tokens[$i + 1] ) && $tokens[$i + 1]['code'] === T_SEMICOLON ) {
					// This is a `return;` so it doesn't need documentation
					continue;
				}
				$found = true;
				break;
			}
		}

		if ( !$found ) {
			return;
		}

		$return = null;
		foreach ( $tokens[$commentStart]['comment_tags'] as $ptr ) {
			$tag = $tokens[$ptr]['content'];
			if ( $tag !== '@return' ) {
				continue;
			}
			if ( $return ) {
				$error = 'Only 1 @return tag is allowed in a function comment';
				$phpcsFile->addError( $error, $ptr, 'DuplicateReturn' );
				return;
			}
			$return = $ptr;
		}
		if ( $return !== null ) {
			$retTypeSpacing = $return + 1;
			if ( $tokens[$retTypeSpacing]['code'] === T_DOC_COMMENT_WHITESPACE ) {
				$expectedSpaces = 1;
				$currentSpaces = strlen( $tokens[$retTypeSpacing]['content'] );
				if ( $currentSpaces !== $expectedSpaces ) {
					$fix = $phpcsFile->addFixableWarning(
						'Expected %s spaces before return type; %s found',
						$retTypeSpacing,
						'SpacingBeforeReturnType',
						[ $expectedSpaces, $currentSpaces ]
					);
					if ( $fix ) {
						$phpcsFile->fixer->replaceToken( $retTypeSpacing, ' ' );
					}
				}
			}
			$retType = $return + 2;
			$content = '';
			if ( $tokens[$retType]['code'] === T_DOC_COMMENT_STRING ) {
				$content = $tokens[$retType]['content'];
			}
			if ( $content === '' ) {
				$error = 'Return type missing for @return tag in function comment';
				$phpcsFile->addError( $error, $return, 'MissingReturnType' );
				return;
			}
			// The first word of the return type is the actual type
			$exploded = explode( ' ', $content, 2 );
			$type = $exploded[0];
			$comment = $exploded[1] ?? null;
			$fixType = false;
			// Check for unneeded punctation
			$type = $this->fixTrailingPunctation(
				$phpcsFile,
				$retType,
				$type,
				$fixType,
				'return type'
			);
			$type = $this->fixWrappedParenthesis(
				$phpcsFile,
				$retType,
				$type,
				$fixType,
				'return type'
			);
			// Check the type for short types
			$type = $this->fixShortTypes( $phpcsFile, $retType, $type, $fixType, 'return' );
			$this->maybeAddObjectTypehintError(
				$phpcsFile,
				$retType,
				$type,
				'return'
			);
			// Check spacing after type
			if ( $comment !== null ) {
				$expectedSpaces = 1;
				$currentSpaces = strspn( $comment, ' ' ) + 1;
				if ( $currentSpaces !== $expectedSpaces ) {
					$fix = $phpcsFile->addFixableWarning(
						'Expected %s spaces after return type; %s found',
						$retType,
						'SpacingAfterReturnType',
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
					$retType,
					$type . ( $comment !== null ? ' ' . $comment : '' )
				);
			}
		} else {
			$error = 'Missing @return tag in function comment';
			$phpcsFile->addError( $error, $tokens[$commentStart]['comment_closer'], 'MissingReturn' );
		}
	}

	/**
	 * Process any throw tags that this function comment has.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $commentStart The position in the stack where the comment started.
	 */
	protected function processThrows( File $phpcsFile, $commentStart ) {
		$tokens = $phpcsFile->getTokens();
		foreach ( $tokens[$commentStart]['comment_tags'] as $tag ) {
			$tagContent = $tokens[$tag]['content'];
			if ( $tagContent !== '@throws' ) {
				continue;
			}
			$exception = null;
			$comment = null;
			if ( $tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING ) {
				preg_match( '/([^\s]+)(?:\s+(.*))?/', $tokens[$tag + 2]['content'], $matches );
				$exception = $matches[1];
				$comment = $matches[2] ?? null;
			}
			if ( $exception === null ) {
				$error = 'Exception type missing for @throws tag in function comment';
				$phpcsFile->addError( $error, $tag, 'InvalidThrows' );
			} else {
				$fix = false;
				$exception = $this->fixWrappedParenthesis(
					$phpcsFile,
					$tag,
					$exception,
					$fix,
					'exception type'
				);
				if ( $fix ) {
					$phpcsFile->fixer->replaceToken(
						$tag + 2,
						$exception . ( $comment === null ? '' : ' ' . $comment )
					);
				}
			}
		}
	}

	/**
	 * Process the function parameter comments.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int $stackPtr The position of the current token in the stack passed in $tokens.
	 * @param int $commentStart The position in the stack where the comment started.
	 */
	protected function processParams( File $phpcsFile, $stackPtr, $commentStart ) {
		$tokens = $phpcsFile->getTokens();
		$params = [];
		foreach ( $tokens[$commentStart]['comment_tags'] as $pos => $tag ) {
			$tagContent = $tokens[$tag]['content'];

			if ( $tagContent !== '@param' ) {
				continue;
			}

			$paramSpace = 0;
			$type = '';
			$typeSpace = 0;
			$var = '';
			$varSpace = 0;
			$comment = '';
			$commentFirst = '';
			if ( $tokens[$tag + 1]['code'] === T_DOC_COMMENT_WHITESPACE ) {
				$paramSpace = strlen( $tokens[$tag + 1]['content'] );
			}
			if ( $tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING ) {
				preg_match( '/^
						# Match parameter type and separator as a group of
						((?:
							# plain letters
							[^&$.\{\[]
							|
							# or pairs of braces around plain letters, never single braces
							\{ [^&$.\{\}]* \}
							|
							# or pairs of brackets around plain letters, never single brackets
							\[ [^&$.\[\]]* \]
						)*) (?:
							# Match parameter name with variadic arg or surround by {} or []
							( (?: \.\.\. | [\[\{] )? [&$] \S+ )
							# Match optional rest of line
							(?: (\s+) (.*) )?
						)? /x',
					$tokens[$tag + 2]['content'],
					$matches
				);
				$untrimmedType = $matches[1] ?? '';
				$type = rtrim( $untrimmedType );
				$typeSpace = strlen( $untrimmedType ) - strlen( $type );
				if ( isset( $matches[2] ) ) {
					$var = $matches[2];
					if ( isset( $matches[4] ) ) {
						$varSpace = strlen( $matches[3] );
						$commentFirst = $matches[4];
						$comment = $commentFirst;
						// Any strings until the next tag belong to this comment.
						$end = $tokens[$commentStart]['comment_tags'][$pos + 1] ??
							$tokens[$commentStart]['comment_closer'];
						for ( $i = $tag + 3; $i < $end; $i++ ) {
							if ( $tokens[$i]['code'] === T_DOC_COMMENT_STRING ) {
								$comment .= ' ' . $tokens[$i]['content'];
							}
						}
					}
				} else {
					$phpcsFile->addError( 'Missing parameter name', $tag, 'MissingParamName' );
				}
			} else {
				$phpcsFile->addError( 'Missing parameter type', $tag, 'MissingParamType' );
			}

			$isPassByReference = substr( $var, 0, 1 ) === '&';
			// Remove the pass by reference to allow compare with varargs
			if ( $isPassByReference ) {
				$var = substr( $var, 1 );
			}

			$isLegacyVariadicArg = substr( $var, -4 ) === ',...';
			$isVariadicArg = substr( $var, 0, 4 ) === '...$';
			// Remove the variadic indicator from the doc name to compare it against the real
			// name, so that we can allow both formats.
			if ( $isLegacyVariadicArg ) {
				$var = substr( $var, 0, -4 );
			} elseif ( $isVariadicArg ) {
				$var = substr( $var, 3 );
			}

			$params[] = [
				'tag' => $tag,
				'type' => $type,
				'var' => $var,
				'variadic_arg' => $isVariadicArg,
				'legacy_variadic_arg' => $isLegacyVariadicArg,
				'pass_by_reference' => $isPassByReference,
				'comment' => $comment,
				'comment_first' => $commentFirst,
				'param_space' => $paramSpace,
				'type_space' => $typeSpace,
				'var_space' => $varSpace,
			];
		}
		$realParams = $phpcsFile->getMethodParameters( $stackPtr );
		$foundParams = [];
		// We want to use ... for all variable length arguments, so added
		// this prefix to the variable name so comparisons are easier.
		foreach ( $realParams as $pos => $param ) {
			if ( $param['variable_length'] === true ) {
				$realParams[$pos]['name'] = '...' . $param['name'];
			}
		}
		foreach ( $params as $pos => $param ) {
			if ( $param['var'] === '' ) {
				continue;
			}
			// Check number of spaces before type (after @param)
			$spaces = 1;
			if ( $param['param_space'] !== $spaces ) {
				$fix = $phpcsFile->addFixableWarning(
					'Expected %s spaces before parameter type; %s found',
					$param['tag'],
					'SpacingBeforeParamType',
					[ $spaces, $param['param_space'] ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->replaceToken( $param['tag'] + 1, str_repeat( ' ', $spaces ) );
				}
			}
			// Check if type is provided
			if ( $param['type'] === '' ) {
				$phpcsFile->addError(
					'Expected parameter type before parameter name "%s"',
					$param['tag'],
					'NoParamType',
					[ $param['var'] ]
				);
			} else {
				// Check number of spaces after the type.
				$spaces = 1;
				if ( $param['type_space'] !== $spaces ) {
					$fix = $phpcsFile->addFixableWarning(
						'Expected %s spaces after parameter type; %s found',
						$param['tag'],
						'SpacingAfterParamType',
						[ $spaces, $param['type_space'] ]
					);
					if ( $fix ) {
						$this->replaceParamComment(
							$phpcsFile,
							$param,
							[ 'type_space' => $spaces ]
						);
					}
				}

			}
			$fixVar = false;
			$var = $this->fixTrailingPunctation(
				$phpcsFile,
				$param['tag'],
				$param['var'],
				$fixVar,
				'param name'
			);
			$var = $this->fixWrappedParenthesis(
				$phpcsFile,
				$param['tag'],
				$var,
				$fixVar,
				'param name'
			);
			if ( $fixVar ) {
				$this->replaceParamComment(
					$phpcsFile,
					$param,
					[ 'var' => $var ]
				);
			}
			// Make sure the param name is correct.
			$defaultNull = false;
			if ( isset( $realParams[$pos] ) ) {
				$realName = $realParams[$pos]['name'];
				// If difference is pass by reference, add or remove & from documentation
				if ( $param['pass_by_reference'] !== $realParams[$pos]['pass_by_reference'] ) {
					$fix = $phpcsFile->addFixableError(
						'Pass-by-reference for parameter %s does not match ' .
							'pass-by-reference of variable name %s',
						$param['tag'],
						'ParamPassByReference',
						[ $var, $realName ]
					);
					if ( $fix ) {
						$this->replaceParamComment(
							$phpcsFile,
							$param,
							[ 'pass_by_reference' => $realParams[$pos]['pass_by_reference'] ]
						);
					}
					$param['pass_by_reference'] = $realParams[$pos]['pass_by_reference'];
				}
				if ( $realName !== $var ) {
					if (
						substr( $realName, 0, 4 ) === '...$' &&
						( $param['legacy_variadic_arg'] || $param['variadic_arg'] )
					) {
						// Mark all variants as found
						$foundParams[] = "...$var";
						$foundParams[] = "$var,...";
					} else {
						$code = 'ParamNameNoMatch';
						$error = 'Doc comment for parameter %s does not match ';
						if ( strcasecmp( $var, $realName ) === 0 ) {
							$error .= 'case of ';
							$code = 'ParamNameNoCaseMatch';
						}
						$error .= 'actual variable name %s';
						$phpcsFile->addError( $error, $param['tag'], $code, [ $var, $realName ] );
					}
				}
				$defaultNull = ( $realParams[$pos]['default'] ?? '' ) === 'null';
			} elseif ( $param['variadic_arg'] || $param['legacy_variadic_arg'] ) {
				$error = 'Variadic parameter documented but not present in the signature';
				$phpcsFile->addError( $error, $param['tag'], 'VariadicDocNotSignature' );
			} else {
				$error = 'Superfluous parameter comment';
				$phpcsFile->addError( $error, $param['tag'], 'ExtraParamComment' );
			}
			$foundParams[] = $var;
			$fixType = false;
			// Check for unneeded punctation on parameter type
			$type = $this->fixWrappedParenthesis(
				$phpcsFile,
				$param['tag'],
				$param['type'],
				$fixType,
				'param type'
			);
			// Check the short type of boolean and integer
			$type = $this->fixShortTypes( $phpcsFile, $param['tag'], $type, $fixType, 'param' );
			$this->maybeAddObjectTypehintError(
				$phpcsFile,
				$param['tag'],
				$type,
				'param'
			);
			$explodedType = $type === '' ? [] : explode( '|', $type );
			$nullableDoc = substr( $type, 0, 1 ) === '?';
			$nullFound = false;
			foreach ( $explodedType as $index => $singleType ) {
				$singleType = lcfirst( $singleType );
				// Either an explicit null, or mixed, which null is a
				// part of (T218324)
				if ( $singleType === 'null' || $singleType === 'mixed' ) {
					$nullFound = true;
				} elseif ( substr( $singleType, -10 ) === '[optional]' ) {
					$fix = $phpcsFile->addFixableError(
						'Key word "[optional]" on "%s" should not be used',
						$param['tag'],
						'NoOptionalKeyWord',
						[ $param['type'] ]
					);
					if ( $fix ) {
						$explodedType[$index] = substr( $singleType, 0, -10 );
						$fixType = true;
					}
				}
			}
			if (
				isset( $realParams[$pos] ) && $nullableDoc && $defaultNull &&
				!$realParams[$pos]['nullable_type']
			) {
				// Don't offer autofix, as changing a signature is somewhat delicate
				$phpcsFile->addError(
					'Use nullable type("%s") for parameters documented as nullable',
					$realParams[$pos]['token'],
					'PHP71NullableDocOptionalArg',
					[ $type ]
				);
			} elseif ( $defaultNull && !( $nullFound || $nullableDoc ) ) {
				// Check if the default of null is in the type list
				$fix = $phpcsFile->addFixableError(
					'Default of null should be declared in @param tag',
					$param['tag'],
					'DefaultNullTypeParam'
				);
				if ( $fix ) {
					$explodedType[] = 'null';
					$fixType = true;
				}
			}

			if ( $fixType ) {
				$this->replaceParamComment(
					$phpcsFile,
					$param,
					[ 'type' => implode( '|', $explodedType ) ]
				);
			}
			if ( $param['comment'] === '' ) {
				continue;
			}
			// Check number of spaces after the var name.
			$spaces = 1;
			if ( $param['var_space'] !== $spaces &&
				ltrim( $param['comment'] ) !== ''
			) {
				$fix = $phpcsFile->addFixableWarning(
					'Expected %s spaces after parameter name; %s found',
					$param['tag'],
					'SpacingAfterParamName',
					[ $spaces, $param['var_space'] ]
				);
				if ( $fix ) {
					$this->replaceParamComment(
						$phpcsFile,
						$param,
						[ 'var_space' => $spaces ]
					);
				}
			}
			// Warn if the parameter is documented as variadic, but the signature doesn't have
			// the splat operator
			if (
				( $param['variadic_arg'] || $param['legacy_variadic_arg'] ) &&
				isset( $realParams[$pos] ) &&
				$realParams[$pos]['variable_length'] === false
			) {
				$legacyName = $param['legacy_variadic_arg'] ? "$var,..." : "...$var";
				$phpcsFile->addError(
					'Splat operator missing for documented variadic parameter "%s"',
					$realParams[$pos]['token'],
					'MissingSplatVariadicArg',
					[ $legacyName ]
				);
			}
		}
		// Report missing comments.
		$missing = array_diff( array_column( $realParams, 'name' ), $foundParams );
		foreach ( $missing as $neededParam ) {
			$error = 'Doc comment for parameter "%s" missing';
			$phpcsFile->addError( $error, $commentStart, 'MissingParamTag', [ $neededParam ] );
		}
	}

	/**
	 * Replace a {@}param comment
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param array $param Array of the @param
	 * @param array $fixParam Array with fixes to @param. Only provide keys to replace
	 */
	protected function replaceParamComment( File $phpcsFile, array $param, array $fixParam ) {
		// Use the old value for unchanged keys
		$fixParam += $param;

		// Build the new line
		$content = $fixParam['type'] .
			str_repeat( ' ', $fixParam['type_space'] ) .
			( $fixParam['pass_by_reference'] ? '&' : '' ) .
			( $fixParam['variadic_arg'] ? '...' : '' ) .
			$fixParam['var'] .
			( $fixParam['legacy_variadic_arg'] ? ',...' : '' ) .
			str_repeat( ' ', $fixParam['var_space'] ) .
			$fixParam['comment_first'];
		$phpcsFile->fixer->replaceToken( $fixParam['tag'] + 2, $content );
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

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 * @param string $typesString
	 * @param string $annotation Either "param" or "return"
	 */
	private function maybeAddObjectTypehintError( File $phpcsFile, $stackPtr, $typesString, $annotation ) {
		$typeList = explode( '|', $typesString );
		foreach ( $typeList as $type ) {
			if ( $type === 'object' || $type === 'object[]' ) {
				$phpcsFile->addError(
					'`object` should not be used as a typehint. If the types are known, list the relevant ' .
						'classes; if this is meant to refer to stdClass, use `stdClass` directly.',
					$stackPtr,
					'ObjectTypeHint' . ucfirst( $annotation )
				);
			}
		}
	}
}
