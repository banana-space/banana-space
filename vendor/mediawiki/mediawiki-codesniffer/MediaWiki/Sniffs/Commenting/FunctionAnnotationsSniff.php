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
use PHP_CodeSniffer\Util\Tokens;

class FunctionAnnotationsSniff implements Sniff {
	/**
	 * Annotations allowed for functions. This includes bad annotations that we check for
	 * elsewhere.
	 */
	private const ALLOWED_ANNOTATIONS = [
		// Allowed all-lowercase tags
		'@after' => true,
		'@author' => true,
		'@before' => true,
		'@code' => true,
		'@cover' => true,
		'@covers' => true,
		'@depends' => true,
		'@deprecated' => true,
		'@endcode' => true,
		'@fixme' => true,
		'@group' => true,
		'@internal' => true,
		'@license' => true,
		'@link' => true,
		'@note' => true,
		'@par' => true,
		'@param' => true,
		'@requires' => true,
		'@return' => true,
		'@see' => true,
		'@since' => true,
		'@throws' => true,
		'@todo' => true,
		'@warning' => true,

		// Automatically replaced
		'@param[in]' => '@param',
		'@param[in,out]' => '@param',
		'@param[out]' => '@param',
		'@params' => '@param',
		'@returns' => '@return',
		'@throw' => '@throws',
		'@exception' => '@throws',

		// private and protected is needed when functions stay public
		// for deprecation or backward compatibility reasons
		// @see https://www.mediawiki.org/wiki/Deprecation_policy#Scope
		'@private' => true,
		'@protected' => true,

		// Special handling
		'@access' => true,

		// Stable interface policy tags
		// @see https://www.mediawiki.org/wiki/Stable_interface_policy
		'@newable' => true,
		'@stable' => true,
		'@unstable' => true,

		// phan
		'@phan-param' => true,
		'@phan-return' => true,
		'@phan-suppress-next-line' => true,
		'@phan-var' => true,
		'@phan-assert' => true,
		'@phan-assert-true-condition' => true,
		'@phan-assert-false-condition' => true,
		'@suppress' => true,
		'@phan-template' => true,
		// No other consumers for now.
		'@template' => '@phan-template',

		// pseudo-tags from phan-taint-check-plugin
		'@param-taint' => true,
		'@return-taint' => true,

		// T263390
		'@noinspection' => true,

		// Allowed mixed-case tags, mapping from all-lowercase to the expected mixed-case
		'@beforeclass' => '@beforeClass',
		'@afterclass' => '@afterClass',
		'@codecoverageignore' => '@codeCoverageIgnore',
		'@covernothing' => '@coverNothing',
		'@coversnothing' => '@coversNothing',
		'@dataprovider' => '@dataProvider',
		'@expectedexception' => '@expectedException',
		'@expectedexceptioncode' => '@expectedExceptionCode',
		'@expectedexceptionmessage' => '@expectedExceptionMessage',
		'@expectedexceptionmessageregexp' => '@expectedExceptionMessageRegExp',
		'@inheritdoc' => '@inheritDoc',

		// Tags to automatically fix
		'@deprecate' => '@deprecated',
		'@gropu' => '@group',
		'@parma' => '@param',
		'@warn' => '@warning',
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
		$tokens = $phpcsFile->getTokens();

		$tokensToSkip = array_merge( Tokens::$emptyTokens, Tokens::$methodPrefixes );
		unset( $tokensToSkip[T_DOC_COMMENT_CLOSE_TAG] );

		$commentEnd = $phpcsFile->findPrevious( $tokensToSkip, $stackPtr - 1, null, true );
		if ( !$commentEnd || $tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG ) {
			return;
		}

		$commentStart = $tokens[$commentEnd]['comment_opener'];

		foreach ( $tokens[$commentStart]['comment_tags'] as $tag ) {
			$tagContent = $tokens[$tag]['content'];
			$annotation = $this->normalizeAnnotation( $tagContent );
			if ( $annotation === false ) {
				$error = '%s is not a valid function annotation';
				$phpcsFile->addError( $error, $tag, 'UnrecognizedAnnotation', [ $tagContent ] );
			} elseif ( $annotation === '@access' ) {
				$this->handleAccessAnnotation( $phpcsFile, $tokens, $tag, $tagContent );
			} elseif ( $tagContent !== $annotation ) {
				$fix = $phpcsFile->addFixableWarning(
					'Use %s annotation instead of %s',
					$tag,
					'NonNormalizedAnnotation',
					[ $annotation, $tagContent ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->replaceToken( $tag, $annotation );
				}
			}
		}
	}

	/**
	 * Normalizes an annotation
	 *
	 * @param string $anno
	 * @return string|false Tag or false if it's not canonical
	 */
	private function normalizeAnnotation( $anno ) {
		$anno = rtrim( $anno, ':' );
		$lower = mb_strtolower( $anno );
		if ( array_key_exists( $lower, self::ALLOWED_ANNOTATIONS ) ) {
			return is_string( self::ALLOWED_ANNOTATIONS[$lower] )
				? self::ALLOWED_ANNOTATIONS[$lower]
				: $lower;
		}

		if ( preg_match( '/^@code{\W?([a-z]+)}$/', $lower, $matches ) ) {
			return '@code{.' . $matches[1] . '}';
		}

		return false;
	}

	/**
	 * @param File $phpcsFile
	 * @param array[] $tokens
	 * @param int $tag Token position of the annotation tag
	 * @param string $tagContent Content of the annotation
	 */
	private function handleAccessAnnotation( File $phpcsFile, $tokens, $tag, $tagContent ) {
		if ( $tokens[$tag + 2]['code'] === T_DOC_COMMENT_STRING ) {
			$text = strtolower( $tokens[$tag + 2]['content'] );
			if ( $text === 'protected' || $text === 'private' ) {
				$replacement = '@' . $text;
				$fix = $phpcsFile->addFixableWarning(
					'Use %s annotation instead of "%s"',
					$tag,
					'AccessAnnotationReplacement',
					[ $replacement, $phpcsFile->getTokensAsString( $tag, 3 ) ]
				);
				if ( $fix ) {
					$phpcsFile->fixer->beginChangeset();
					$phpcsFile->fixer->replaceToken( $tag, $replacement );
					$phpcsFile->fixer->replaceToken( $tag + 1, '' );
					$phpcsFile->fixer->replaceToken( $tag + 2, '' );
					$phpcsFile->fixer->endChangeset();
				}
				return;
			}
		}
		$error = '%s is not a valid function annotation';
		$phpcsFile->addError( $error, $tag, 'AccessAnnotationInvalid', [ $tagContent ] );
	}
}
