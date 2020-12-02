<?php
/**
 * Utilities used in the tokenizer.
 * @module wt2html/tokenizer_utils
 */

// phpcs:disable MediaWiki.WhiteSpace.SpaceBeforeSingleLineComment.SingleSpaceBeforeSingleLineComment
// phpcs:disable Generic.Functions.OpeningFunctionBraceKernighanRitchie.BraceOnNewLine
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingParamTag
// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingReturn

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Tokens\CommentTk;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

class TokenizerUtils {
	private static $protectAttrsRegExp;

	/**
	 * @param mixed $e
	 * @param ?array &$res
	 */
	private static function internalFlatten( $e, ?array &$res ) {
		// Don't bother flattening if we dont have an array
		if ( !is_array( $e ) ) {
			return $e;
		}

		for ( $i = 0;  $i < count( $e );  $i++ ) {
			$v = $e[$i];
			if ( is_array( $v ) ) {
				// Change in assumption from a shallow array to a nested array.
				if ( $res === null ) {
					$res = array_slice( $e, 0, $i );
				}
				self::internalFlatten( $v, $res );
			} elseif ( $v !== null ) {
				if ( $res !== null ) {
					$res[] = $v;
				}
			} else {
				throw new \Exception( __METHOD__ . ": found falsy element $i" );
			}
		}

		if ( $res !== null ) {
			$e = $res;
		}
		return $e;
	}

	public static function flattenIfArray( $a ) {
		return self::internalFlatten( $a, $res );
	}

	public static function flattenString( $c ) {
		$out = self::flattenStringlist( $c );
		if ( count( $out ) === 1 && is_string( $out[0] ) ) {
			return $out[0];
		} else {
			return $out;
		}
	}

	public static function flattenStringlist( $c ) {
		$out = [];
		$text = '';
		// c will always be an array
		$c = self::flattenIfArray( $c );
		for ( $i = 0,  $l = count( $c );  $i < $l;  $i++ ) {
			$ci = $c[$i];
			if ( is_string( $ci ) ) {
				if ( $ci !== '' ) {
					$text .= $ci;
				}
			} else {
				if ( $text !== '' ) {
					$out[] = $text;
					$text = '';
				}
				$out[] = $ci;
			}
		}
		if ( $text !== '' ) {
			$out[] = $text;
		}
		return $out;
	}

	public static function getAttrVal( $value, int $start, int $end ) {
		return [ 'value' => $value, 'srcOffsets' => new SourceRange( $start, $end ) ];
	}

	public static function buildTableTokens(
		string $tagName, string $wtChar, $attrInfo, SourceRange $tsr,
		int $endPos, $content, bool $addEndTag = false ): array
	{
		$a = null;
		$dp = (object)[ 'tsr' => $tsr ];

		if ( !$attrInfo ) {
			$a = [];
			if ( $tagName === 'td' || $tagName === 'th' ) {
				// Add a flag that indicates that the tokenizer didn't
				// encounter a "|...|" attribute box. This is useful when
				// deciding which <td>/<th> cells need attribute fixups.
				$dp->tmp = PHPUtils::arrayToObject( [ 'noAttrs' => true ] );
			}
		} else {
			$a = $attrInfo[0];
			if ( count( $a ) === 0 ) {
				$dp->startTagSrc = $wtChar . $attrInfo[1];
			}
			if ( ( count( $a ) === 0 && $attrInfo[2] ) || $attrInfo[2] !== '|' ) {
				// Variation from default
				// 1. Separator present with an empty attribute block
				// 2. Not "|"
				$dp->attrSepSrc = $attrInfo[2];
			}
		}

		$dataAttribs = (object)[ 'tsr' => new SourceRange( $endPos, $endPos ) ];
		$endTag = null;
		if ( $addEndTag ) {
			$endTag = new EndTagTk( $tagName, [], $dataAttribs );
		} else {
			// We rely on our tree builder to close the table cell (td/th) as needed.
			// We cannot close the cell here because cell content can come from
			// multiple parsing contexts and we cannot close the tag in the same
			// parsing context in which the td was opened:
			//   Ex: {{1x|{{!}}foo}}{{1x|bar}} has to output <td>foobar</td>
			//
			// But, add a marker meta-tag to capture tsr info.
			// SSS FIXME: Unsure if this is actually helpful, but adding it in just in case.
			// Can test later and strip it out if it doesn't make any diff to rting.
			$endTag = new SelfclosingTagTk( 'meta', [
					new KV( 'typeof', 'mw:TSRMarker' ),
					new KV( 'data-etag', $tagName )
				], $dataAttribs
			);
		}

		return array_merge(
			[ new TagTk( $tagName, $a, $dp ) ],
			$content,
			[ $endTag ] );
	}

	public static function buildXMLTag( string $name, string $lcName, array $attribs, $endTag,
		bool $selfClose, SourceRange $tsr
	) {
		$tok = null;
		$da = (object)[ 'tsr' => $tsr, 'stx' => 'html' ];

		if ( $name !== $lcName ) {
			$da->srcTagName = $name;
		}

		if ( $endTag !== null ) {
			$tok = new EndTagTk( $lcName, $attribs, $da );
		} elseif ( $selfClose ) {
			$da->selfClose = true;
			$tok = new SelfclosingTagTk( $lcName, $attribs, $da );
		} else {
			$tok = new TagTk( $lcName, $attribs, $da );
		}

		return $tok;
	}

	/**
	 * Inline breaks, flag-enabled rule which detects end positions for
	 * active higher-level rules in inline and other nested rules.
	 * Those inner rules are then exited, so that the outer rule can
	 * handle the end marker.
	 */
	public static function inlineBreaks( string $input, int $pos, array $stops ) {
		$c = $input[$pos];
		$c2 = $input[$pos + 1] ?? '';

		switch ( $c ) {
			case '=':
				if ( $stops['arrow'] && $c2 === '>' ) {
					return true;
				}
				return $stops['equal']
					|| $stops['h']
					&& ( $pos === strlen( $input ) - 1
					// possibly more equals followed by spaces or comments
					|| preg_match( '/^=*(?:[ \t]|<\!--(?:(?!-->).)*-->)*(?:[\r\n]|$)/sD',
						substr( $input, $pos + 1 )
					) );

			case '|':
				return ( $stops['templateArg'] && !$stops['extTag'] )
					|| $stops['tableCellArg']
					|| $stops['linkdesc']
					|| ( $stops['table']
						&& $pos < strlen( $input ) - 1
						&& preg_match( '/[}|]/', $input[$pos + 1] ) );

			case '!':
				return $stops['th']
					&& !$stops['templatedepth']
					&& $c2 === '!';

			case '{':
				// {{!}} pipe templates..
				// FIXME: Presumably these should mix with and match | above.
				// phpcs:ignore Squiz.WhiteSpace.LanguageConstructSpacing.IncorrectSingle
				return
					( $stops['tableCellArg']
						&& substr( $input, $pos, 5 ) === '{{!}}' )
					|| ( $stops['table']
						&& substr( $input, $pos, 10 ) === '{{!}}{{!}}' );

			case '}':
				$preproc = $stops['preproc'];
				return ( $c2 === '}' && $preproc === '}}' )
					|| ( $c2 === '-' && $preproc === '}-' );

			case ':':
				return $stops['colon']
					&& !$stops['extlink']
					&& !$stops['templatedepth']
					&& !$stops['linkdesc']
					&& !( $stops['preproc'] === '}-' );

			case ';':
				return $stops['semicolon'];

			case "\r":
				return $stops['table']
					&& preg_match( '/\r\n?\s*[!|]/', substr( $input, $pos ) );

			case "\n":
				// The code below is just a manual / efficient
				// version of this check.
				//
				// stops.table && /^\n\s*[!|]/.test(input.substr(pos));
				//
				// It eliminates a substr on the string and eliminates
				// a potential perf problem since "\n" and the inline_breaks
				// test is common during tokenization.
				if ( !$stops['table'] ) {
					return false;
				}

				// Allow leading whitespace in tables

				// Since we switched on 'c' which is input[pos],
				// we know that input[pos] is "\n".
				// So, the /^\n/ part of the regexp is already satisfied.
				// Look for /\s*[!|]/ below.
				$n = strlen( $input );
				for ( $i = $pos + 1;  $i < $n;  $i++ ) {
					$d = $input[$i];
					if ( preg_match( '/[!|]/', $d ) ) {
						return true;
					} elseif ( !( preg_match( '/\s/', $d ) ) ) {
						return false;
					}
				}
				return false;
			case '[':
				// This is a special case in php's doTableStuff, added in
				// response to T2553.  If it encounters a `[[`, it bails on
				// parsing attributes and interprets it all as content.
				return $stops['tableCellArg'] && $c2 === '[';

			case '-':
				// Same as above: a special case in doTableStuff, added
				// as part of T153140
				return $stops['tableCellArg'] && $c2 === '{';

			case ']':
				if ( $stops['extlink'] ) {
					return true;
				}
				return $stops['preproc'] === ']]'
					&& $c2 === ']';

			default:
				throw new \Exception( 'Unhandled case!' );
		}
	}

	/** Pop off the end comments, if any. */
	public static function popComments( array &$attrs ) {
		$buf = [];
		for ( $i = count( $attrs ) - 1;  $i > -1;  $i-- ) {
			$kv = $attrs[$i];
			if ( is_string( $kv->k ) && !$kv->v && preg_match( '/^\s*$/D', $kv->k ) ) {
				// permit whitespace
				array_unshift( $buf, $kv->k );
			} elseif ( is_array( $kv->k ) && !$kv->v ) {
				// all should be comments
				foreach ( $kv->k as $k ) {
					if ( !( $k instanceof CommentTk ) ) {
						break 2;
					}
				}
				array_splice( $buf, 0, 0, $kv->k );
			} else {
				break;
			}
		}
		// ensure we found a comment
		while ( count( $buf ) && !( $buf[0] instanceof CommentTk ) ) {
			array_shift( $buf );
		}
		if ( count( $buf ) ) {
			array_splice( $attrs, -count( $buf ), count( $buf ) );
			return [ 'buf' => $buf, 'commentStartPos' => $buf[0]->dataAttribs->tsr->start ];
		} else {
			return null;
		}
	}

	public static function enforceParserResourceLimits( Env $env, $token ) {
		if ( $token && ( $token instanceof TagTk || $token instanceof SelfclosingTagTk ) ) {
			switch ( $token->getName() ) {
				case 'listItem':
					$env->bumpWt2HtmlResourceUse( 'listItem' );
					break;

				case 'template':
					$env->bumpWt2HtmlResourceUse( 'transclusion' );
					break;

				case 'td':
				case 'th':
					$env->bumpWt2HtmlResourceUse( 'tableCell' );
					break;
			}
		}
	}

	public static function protectAttrs( string $name ) {
		if ( self::$protectAttrsRegExp === null ) {
			self::$protectAttrsRegExp = "/^(about|data-mw.*|data-parsoid.*|data-x.*|" .
				DOMDataUtils::DATA_OBJECT_ATTR_NAME .
				'|property|rel|typeof)$/i';
		}
		return preg_replace( self::$protectAttrsRegExp, 'data-x-$1', $name );
	}

	public static function isIncludeTag( $name ) {
		return $name === 'includeonly' || $name === 'noinclude' || $name === 'onlyinclude';
	}
}
