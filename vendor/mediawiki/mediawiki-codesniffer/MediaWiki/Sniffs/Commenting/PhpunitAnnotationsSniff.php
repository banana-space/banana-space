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

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class PhpunitAnnotationsSniff implements Sniff {

	/**
	 * Annotations this sniff should work on
	 * Maybe specify a replacement and the sniff code
	 * - Ignore @author, because it also used outside of tests
	 * - Ignore @codeCoverageIgnore, because it can be used outside of tests
	 * - Ignore @uses, because it is also a PHPDoc annotation
	 *
	 * If an annotation is found outside of a test classes, it is reported.
	 *
	 * @see https://phpunit.de/manual/current/en/appendixes.annotations.html
	 */
	private const ALLOWED_ANNOTATIONS = [
		'@after' => true,
		'@afterClass' => true,

		'@before' => true,
		'@beforeClass' => true,

		'@covers' => true,
		'@cover' => [ '@covers', 'SingularCover' ],

		'@coversDefaultClass' => true,
		'@coverDefaultClass' => [ '@coversDefaultClass', 'SingularCoverDefaultClass' ],

		'@coversNothing' => true,
		'@coverNothing' => [ '@coversNothing', 'SingularCoverNothing' ],

		'@dataProvider' => true,

		'@group' => true,
		'@requires' => true,
		'@depends' => true,

		'@small' => [ '@group small', 'GroupAliasSmall' ],
		'@medium' => [ '@group medium', 'GroupAliasMedium' ],
		'@large' => [ '@group large', 'GroupAliasLarge' ],

		'@testWith' => true,
		'@doesNotPerformAssertions' => true,

		// From johnkary/phpunit-speedtrap
		'@slowThreshold' => true,
	];

	/**
	 * A list of forbidden annotations. True as message will use a default message.
	 *
	 * If an annotation is found outside of a test classes, it is reported with another message.
	 */
	private const FORBIDDEN_ANNOTATIONS = [
		// Name the function with test prefix, some other sniffs depends on that
		'@test' => 'Do not use %s, name the function to begin with "test".',

		'@testdox' => true,
		'@backupGlobals' => true,
		'@backupStaticAttributes' => true,
		'@preserveGlobalState' => true,

		'@runTestsInSeparateProcesses' => true,
		'@runInSeparateProcess' => true,

		// Removed in PHPUnit 7, T234597
		'@expectedException' => 'Do not use %s, use $this->expectException().',
		'@expectedExceptionCode' => 'Do not use %s, use $this->expectExceptionCode().',
		'@expectedExceptionMessage' => 'Do not use %s, use $this->expectExceptionMessage().',
		'@expectedExceptionMessageRegExp' => 'Do not use %s, use $this->expectExceptionMessageRegExp().',
	];

	private const EMPTY_ANNOTATIONS = [
		'@coversNothing',
		'@coverNothing',
		'@doesNotPerformAssertions',
		'@small',
		'@medium',
		'@large',
		'@after',
		'@afterClass',
		'@before',
		'@beforeClass',
	];

	/**
	 * A list of naming patterns for annotations
	 * Annotations not found here using default test* name
	 */
	private const FUNCTION_NAMING_PATTERN = [
		'@after' => [
			'regex' => '/TearDown$/',
			'message' => 'tearDown functions (*TearDown)',
			'code' => 'NotTearDownFunction',
		],
		'@afterClass' => [
			'regex' => '/TearDownAfterClass$/',
			'message' => 'tearDown functions (*TearDownAfterClass)',
			'code' => 'NotTearDownAfterClassFunction',
		],
		'@before' => [
			'regex' => '/SetUp$/',
			'message' => 'setUp functions (*SetUp)',
			'code' => 'NotSetUpFunction',
		],
		'@beforeClass' => [
			'regex' => '/SetUpBeforeClass$/',
			'message' => 'setUp functions (*SetUpBeforeClass)',
			'code' => 'NotSetUpBeforeClassFunction',
		],
		'*' => [
			'regex' => '/^(?:test|provide)|Provider$/',
			'message' => 'test functions',
			'code' => 'NotTestFunction',
		],
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
	 * @param File $phpcsFile
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
	 * @param int $end Token position of the end of the comment
	 */
	private function processDocTag( File $phpcsFile, array $tokens, $tag, $end ) {
		$tagText = $tokens[$tag]['content'];
		$forbidden = array_key_exists( $tagText, self::FORBIDDEN_ANNOTATIONS );

		// Check for forbidden annotations
		if ( $forbidden ) {
			$message = self::FORBIDDEN_ANNOTATIONS[$tagText] === true
				? 'The phpunit annotation %s should not be used.'
				: self::FORBIDDEN_ANNOTATIONS[$tagText];
			$phpcsFile->addWarning(
				$message,
				$tag, $this->createSniffCode( 'Forbidden', $tagText ), [ $tagText ]
			);
			return;
		}

		$allowed = array_key_exists( $tagText, self::ALLOWED_ANNOTATIONS );
		if ( !$allowed ) {
			// Nothing to work in this sniff
			return;
		}

		if ( $tokens[$end]['level'] === 0 ) {
			$objectToken = $this->findClassToken( $phpcsFile, $tokens, $end );
			if ( !$objectToken ) {
				$phpcsFile->addWarning(
					'The phpunit annotation %s should only be used in class level comments.',
					$tag, 'NotClass', [ $tagText ]
				);
				return;
			}
		} else {
			$objectToken = $this->findObjectStructureTokenFunctionLevel( $tokens, $end );
			if ( !$objectToken ) {
				$phpcsFile->addWarning(
					'The phpunit annotation %s should only be used inside classes or traits.',
					$tag, 'NotInClassTrait', [ $tagText ]
				);
				return;
			}
		}
		if ( $tokens[$objectToken]['code'] === T_CLASS &&
			!$this->isTestClass( $phpcsFile, $objectToken )
		) {
			$phpcsFile->addWarning(
				'The phpunit annotation %s should only be used inside test classes.',
				$tag, 'NotTestClass', [ $tagText ]
			);
			return;
		}

		// Normalize some tags
		if ( is_array( self::ALLOWED_ANNOTATIONS[$tagText] ) ) {
			$replacement = self::ALLOWED_ANNOTATIONS[$tagText][0];
			$sniffCode = self::ALLOWED_ANNOTATIONS[$tagText][1];
			$fix = $phpcsFile->addFixableWarning(
				'Use %s annotation instead of %s',
				$tag, $sniffCode, [ $replacement, $tagText ]
			);
			if ( $fix ) {
				$phpcsFile->fixer->replaceToken( $tag, $replacement );
			}
		}

		// Check if there is some text behind or not
		if ( !in_array( $tagText, self::EMPTY_ANNOTATIONS ) ) {
			$next = $phpcsFile->findNext( [ T_DOC_COMMENT_WHITESPACE ], $tag + 1, $end, true );
			if ( $tokens[$next]['code'] !== T_DOC_COMMENT_STRING ) {
				$phpcsFile->addWarning(
					'The phpunit annotation %s must be followed by text.',
					$tag, $this->createSniffCode( 'Empty', $tagText ), [ $tagText ]
				);
			}
		}

		// Check the name of the function
		if ( $tokens[$tag]['level'] > 0 ) {
			if ( isset( self::FUNCTION_NAMING_PATTERN[$tagText] ) ) {
				$namingPattern = self::FUNCTION_NAMING_PATTERN[$tagText];
			} else {
				$namingPattern = self::FUNCTION_NAMING_PATTERN['*'];
			}

			$functionToken = $this->findFunctionToken( $phpcsFile, $tokens, $end );
			if ( !$functionToken ||
				!$this->isFunctionOkay( $phpcsFile, $functionToken, $namingPattern['regex'] )
			) {
				$phpcsFile->addWarning(
					'The phpunit annotation %s should only be used for %s.',
					$tag, $namingPattern['code'], [ $tagText, $namingPattern['message'] ]
				);
			}
		}
	}

	/**
	 * @param string $prefix
	 * @param string $annotation
	 *
	 * @return string
	 */
	private function createSniffCode( $prefix, $annotation ) {
		return $prefix . ucfirst( ltrim( $annotation, '@' ) );
	}

	/**
	 * Find the class this class level comment depends on.
	 *
	 * @param File $phpcsFile
	 * @param array[] $tokens
	 * @param int $commentEnd
	 * @return int|false
	 */
	private function findClassToken( File $phpcsFile, array $tokens, $commentEnd ) {
		$next = $phpcsFile->findNext( [ T_CLASS ], $commentEnd + 1 );

		// Only process class directly located after the comment
		if ( $next &&
			$tokens[$commentEnd]['line'] + 1 === $tokens[$next]['line']
		) {
			return $next;
		}

		return false;
	}

	/**
	 * Find the class or trait this function level comment depends on.
	 *
	 * @param array[] $tokens
	 * @param int $commentEnd
	 * @return int|false
	 */
	private function findObjectStructureTokenFunctionLevel( array $tokens, $commentEnd ) {
		foreach ( $tokens[$commentEnd]['conditions'] as $ptr => $type ) {
			if ( $type === T_CLASS || $type === T_TRAIT ) {
				return $ptr;
			}
		}

		return false;
	}

	/**
	 * Find the function this comment is for
	 *
	 * @param File $phpcsFile
	 * @param array[] $tokens
	 * @param int $commentEnd
	 * @return int|false
	 */
	private function findFunctionToken( File $phpcsFile, array $tokens, $commentEnd ) {
		$next = $phpcsFile->findNext( [ T_FUNCTION ], $commentEnd + 1 );

		// Only process class directly located after the comment
		if ( $next &&
			$tokens[$commentEnd]['line'] + 1 === $tokens[$next]['line']
		) {
			return $next;
		}

		return false;
	}

	/**
	 * @param File $phpcsFile
	 * @param int $classPtr Token position of the class declaration
	 * @return int
	 */
	private function isTestClass( File $phpcsFile, $classPtr ) {
		return preg_match(
			'/(?:Test(?:Case)?(?:Base)?|Suite)$/', $phpcsFile->getDeclarationName( $classPtr )
		);
	}

	/**
	 * @param File $phpcsFile
	 * @param int $functionPtr Token position of the function declaration
	 * @param string $pattern Regex to match against the name of the function
	 * @return int
	 */
	private function isFunctionOkay( File $phpcsFile, $functionPtr, $pattern ) {
		return preg_match(
			$pattern, $phpcsFile->getDeclarationName( $functionPtr )
		);
	}

}
