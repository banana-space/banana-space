<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants as Consts;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Tokens\Token;

/**
 * This file contains general utilities for token transforms.
 */
class Utils {
	/**
	 * Regular expression fragment for matching wikitext comments.
	 * Meant for inclusion in other regular expressions.
	 */
	// Maintenance note: this is used in /x regexes so all whitespace and # should be escaped
	public const COMMENT_REGEXP_FRAGMENT = '<!--(?>[\s\S]*?-->)';
	/** Regular fragment for matching a wikitext comment */
	public const COMMENT_REGEXP = '/' . self::COMMENT_REGEXP_FRAGMENT . '/';

	/**
	 * Strip Parsoid id prefix from aboutID
	 *
	 * @param string $aboutId aboud ID string
	 * @return string
	 */
	public static function stripParsoidIdPrefix( string $aboutId ): string {
		// 'mwt' is the prefix used for new ids
		return preg_replace( '/^#?mwt/', '', $aboutId );
	}

	/**
	 * Strip PHP namespace from the fully qualified class name
	 * @param string $className
	 * @return string
	 */
	public static function stripNamespace( string $className ): string {
		return preg_replace( '/.*\\\\/', '', $className );
	}

	/**
	 * Check for Parsoid id prefix in an aboutID string
	 *
	 * @param string $aboutId aboud ID string
	 * @return bool
	 */
	public static function isParsoidObjectId( string $aboutId ): bool {
		// 'mwt' is the prefix used for new ids
		return (bool)preg_match( '/^#mwt/', $aboutId );
	}

	/**
	 * Determine if the named tag is void (can not have content).
	 *
	 * @param string $name tag name
	 * @return bool
	 */
	public static function isVoidElement( string $name ): bool {
		return isset( Consts::$HTML['VoidTags'][$name] );
	}

	/**
	 * recursive deep clones helper function
	 *
	 * @param object $el object
	 * @return object
	 */
	private static function recursiveClone( $el ) {
		return self::clone( $el, true );
	}

	/**
	 * deep clones by default.
	 * FIXME, see T161647
	 * @param object|array $obj any plain object not tokens or DOM trees
	 * @param bool $deepClone
	 * @return object|array
	 */
	public static function clone( $obj, $deepClone = true ) {
		if ( !$deepClone && is_object( $obj ) ) {
			return clone $obj;
		}
		return unserialize( serialize( $obj ) );
	}

	/**
	 * Extract the last *unicode* character of the string.
	 * This might be more than one byte, if the last character
	 * is non-ASCII.
	 * @param string $str
	 * @param ?int $idx The index *after* the character to extract; defaults
	 *   to the length of $str, which will extract the last character in
	 *   $str.
	 * @return string
	 */
	public static function lastUniChar( string $str, ?int $idx = null ): string {
		if ( $idx === null ) {
			$idx = strlen( $str );
		} elseif ( $idx <= 0 || $idx > strlen( $str ) ) {
			return '';
		}
		$c = $str[--$idx];
		while ( ( ord( $c ) & 0xC0 ) === 0x80 ) {
			$c = $str[--$idx] . $c;
		}
		return $c;
	}

	/**
	 * Return true if the first character in $s is a unicode word character.
	 * @param string $s
	 * @return bool
	 */
	public static function isUniWord( string $s ): bool {
		return preg_match( '#^\w#u', $s ) === 1;
	}

	/**
	 * This should not be used.
	 * @param string $txt URL to encode using PHP encoding
	 * @return string
	 */
	public static function phpURLEncode( $txt ) {
		throw new \BadMethodCallException( 'Use urlencode( $txt ) instead' );
	}

	/**
	 * Percent-decode only valid UTF-8 characters, leaving other encoded bytes alone.
	 *
	 * Distinct from `decodeURIComponent` in that certain escapes are not decoded,
	 * matching the behavior of JavaScript's decodeURI().
	 *
	 * @see https://www.ecma-international.org/ecma-262/6.0/#sec-decodeuri-encodeduri
	 * @param string $s URI to be decoded
	 * @return string
	 */
	public static function decodeURI( string $s ): string {
		// Escape the '%' in sequences for the reserved characters, then use decodeURIComponent.
		$s = preg_replace( '/%(?=2[346bcfBCF]|3[abdfABDF]|40)/', '%25', $s );
		return self::decodeURIComponent( $s );
	}

	/**
	 * Percent-decode only valid UTF-8 characters, leaving other encoded bytes alone.
	 *
	 * @param string $s URI to be decoded
	 * @return string
	 */
	public static function decodeURIComponent( string $s ): string {
		// Most of the time we should have valid input
		$ret = rawurldecode( $s );
		if ( mb_check_encoding( $ret, 'UTF-8' ) ) {
			return $ret;
		}

		// Extract each encoded character and decode it individually
		return preg_replace_callback(
			// phpcs:ignore Generic.Files.LineLength.TooLong
			'/%[0-7][0-9A-F]|%[CD][0-9A-F]%[89AB][0-9A-F]|%E[0-9A-F](?:%[89AB][0-9A-F]){2}|%F[0-4](?:%[89AB][0-9A-F]){3}/i',
			function ( $match ) {
				$ret = rawurldecode( $match[0] );
				return mb_check_encoding( $ret, 'UTF-8' ) ? $ret : $match[0];
			}, $s
		);
	}

	/**
	 * Extract extension source from the token
	 *
	 * @param Token $token token
	 * @return string
	 */
	public static function extractExtBody( Token $token ): string {
		$src = $token->getAttribute( 'source' );
		$extTagOffsets = $token->dataAttribs->extTagOffsets;
		'@phan-var \Wikimedia\Parsoid\Core\DomSourceRange $extTagOffsets';
		return $extTagOffsets->stripTags( $src );
	}

	/**
	 * Helper function checks numeric values
	 *
	 * @param ?int $n checks parameters for numeric type and value zero or positive
	 * @return bool
	 */
	private static function isValidOffset( ?int $n ): bool {
		return $n !== null && $n >= 0;
	}

	/**
	 * Check for valid DSR range(s)
	 * DSR = "DOM Source Range".
	 *
	 * @param DomSourceRange|null $dsr DSR source range values
	 * @param bool $all Also check the widths of the container tag
	 * @return bool
	 */
	public static function isValidDSR( ?DomSourceRange $dsr, bool $all = false ): bool {
		return $dsr !== null &&
			self::isValidOffset( $dsr->start ) &&
			self::isValidOffset( $dsr->end ) &&
			( !$all || ( self::isValidOffset( $dsr->openWidth ) &&
				self::isValidOffset( $dsr->closeWidth )
				) );
	}

	/**
	 * Cannonicalizes a namespace name.
	 *
	 * @param string $name Non-normalized namespace name.
	 * @return string
	 */
	public static function normalizeNamespaceName( string $name ): string {
		return strtr( mb_strtolower( $name ), ' ', '_' );
	}

	/**
	 * Decode HTML5 entities in wikitext.
	 *
	 * NOTE that wikitext only allows semicolon-terminated entities, while
	 * HTML allows a number of "legacy" entities to be decoded without
	 * a terminating semicolon.  This function deliberately does not
	 * decode these HTML-only entity forms.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function decodeWtEntities( string $text ): string {
		// There are some entities disallowed by wikitext (T106578,T113194)
		$text = preg_replace( [
			'/&#(0*12|x0*c);/i',
			'/&#(0*1114110|x0*10fffe);/i',
			'/&#(0*1114111|x0*10ffff);/i',
		], [
			'&amp;#$1;',  // \u000C is disallowed
			"\u{10FFFE}", // \u10FFFE is allowed but not decoded (weird)
			"\u{10FFFF}", // \u10FFFF is allowed but not decoded (again, weird)
		], $text );
		// HTML5 allows semicolon-less entities which wikitext does not:
		// in wikitext all entities must end in a semicolon.
		// PHP currently doesn't decode semicolon-less entities (see
		// https://bugs.php.net/bug.php?id=77769 ) but we've got a
		// unit test which would fail if it ever started to.
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'utf-8' );
	}

	/**
	 * Entity-escape anything that would decode to a valid wikitext entity.
	 *
	 * Note that HTML5 allows certain "semicolon-less" entities, like
	 * `&para`; these aren't allowed in wikitext and won't be escaped
	 * by this function.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function escapeWtEntities( string $text ): string {
		// We just want to encode ampersands that precede valid entities.
		// (And note that semicolon-less entities aren't valid wikitext.)
		return preg_replace_callback( '/&[#0-9a-zA-Z]+;/', function ( $match ) {
			$m = $match[0];
			$decodedChar = self::decodeWtEntities( $m );
			if ( $decodedChar !== $m ) {
				// Escape the ampersand
				return '&amp;' . substr( $m, 1 );
			} else {
				// Not an entity, just return the string
				return $m;
			}
		}, $text );
	}

	/**
	 * Convert special characters to HTML entities
	 *
	 * @param string $s
	 * @return string
	 */
	public static function escapeHtml( string $s ): string {
		// Only encodes five characters: " ' & < >
		return htmlspecialchars( $s, ENT_QUOTES | ENT_HTML5 );
	}

	/**
	 * Encode all characters as entity references.  This is done to make
	 * characters safe for wikitext (regardless of whether they are
	 * HTML-safe). Typically only called with single-codepoint strings.
	 * @param string $s
	 * @return string
	 */
	public static function entityEncodeAll( string $s ): string {
		// This is Unicode aware.
		static $conventions = [
			// We always use at least two characters for the hex code
			'&#x0;' => '&#x00;', '&#x1;' => '&#x01;', '&#x2;' => '&#x02;', '&#x3;' => '&#x03;',
			'&#x4;' => '&#x04;', '&#x5;' => '&#x05;', '&#x6;' => '&#x06;', '&#x7;' => '&#x07;',
			'&#x8;' => '&#x08;', '&#x9;' => '&#x09;', '&#xA;' => '&#x0A;', '&#xB;' => '&#x0B;',
			'&#xC;' => '&#x0C;', '&#xD;' => '&#x0D;', '&#xE;' => '&#x0E;', '&#xF;' => '&#x0F;',
			// By convention we use &nbsp; where possible
			'&#xA0;' => '&nbsp;',
		];

		return strtr( mb_encode_numericentity(
			$s, [ 0, 0x10ffff, 0, ~0 ], 'utf-8', true
		), $conventions );
	}

	/**
	 * Determine whether the protocol of a link is potentially valid. Use the
	 * environment's per-wiki config to do so.
	 *
	 * @param mixed $linkTarget
	 * @param Env $env
	 * @return bool
	 */
	public static function isProtocolValid( $linkTarget, Env $env ): bool {
		$siteConf = $env->getSiteConfig();
		if ( is_string( $linkTarget ) ) {
			return $siteConf->hasValidProtocol( $linkTarget );
		} else {
			return true;
		}
	}

	/**
	 * Get argument information for an extension tag token.
	 *
	 * @param Token $extToken
	 * @return \stdClass
	 */
	public static function getExtArgInfo( Token $extToken ): \stdClass {
		$name = $extToken->getAttribute( 'name' );
		$options = $extToken->getAttribute( 'options' );
		return (object)[
			'dict' => (object)[
				'name' => $name,
				'attrs' => PHPUtils::arrayToObject( TokenUtils::kvToHash( $options ) ),
				'body' => (object)[
					'extsrc' => self::extractExtBody( $extToken )
				],
			],
		];
	}

	/**
	 * Parse media dimensions
	 *
	 * @param string $str media dimension string to parse
	 * @param bool $onlyOne If set, returns null if multiple dimenstions are present
	 * @return array{x:int,y?:int}|null
	 */
	public static function parseMediaDimensions( string $str, bool $onlyOne = false ): ?array {
		$dimensions = null;
		if ( preg_match( '/^(\d*)(?:x(\d+))?\s*(?:px\s*)?$/D', $str, $match ) ) {
			$dimensions = [ 'x' => null, 'y' => null ];
			if ( !empty( $match[1] ) ) {
				$dimensions['x'] = intval( $match[1], 10 );
			}
			if ( !empty( $match[2] ) ) {
				if ( $onlyOne ) {
					return null;
				}
				$dimensions['y'] = intval( $match[2], 10 );
			}
		}
		return $dimensions;
	}

	/**
	 * Validate media parameters
	 * More generally, this is defined by the media handler in core
	 *
	 * @param int|null $num
	 * @return bool
	 */
	public static function validateMediaParam( ?int $num ): bool {
		return $num !== null && $num > 0;
	}

	/**
	 * FIXME: Is this needed??
	 *
	 * Extract content in a backwards compatible way
	 *
	 * @param object $revision
	 * @return object
	 */
	public static function getStar( $revision ) {
		/*
		$content = $revision;
		if ( $revision && isset( $revision->slots ) ) {
			$content = $revision->slots->main;
		}
		return $content;
		*/
		throw new \BadMethodCallException( "This method shouldn't be needed. " .
			"But, port this if you really need it." );
	}

	/**
	 * Magic words masquerading as templates.
	 * @return array
	 */
	public static function magicMasqs() {
		return PHPUtils::makeSet( [ 'defaultsort', 'displaytitle' ] );
	}

	/**
	 * This regex was generated by running through *all unicode characters* and
	 * testing them against *all regexes* for linktrails in a default MW install.
	 * We had to treat it a little bit, here's what we changed:
	 *
	 * 1. A-Z, though allowed in Walloon, is disallowed.
	 * 2. '"', though allowed in Chuvash, is disallowed.
	 * 3. '-', though allowed in Icelandic (possibly due to a bug), is disallowed.
	 * 4. '1', though allowed in Lak (possibly due to a bug), is disallowed.
	 */
	public static $linkTrailRegex =
		'/^[^\0-`{÷ĀĈ-ČĎĐĒĔĖĚĜĝĠ-ĪĬ-įĲĴ-ĹĻ-ĽĿŀŅņŉŊŌŎŏŒŔŖ-ŘŜŝŠŤŦŨŪ-ŬŮŲ-ŴŶŸ' .
		'ſ-ǤǦǨǪ-Ǯǰ-ȗȜ-ȞȠ-ɘɚ-ʑʓ-ʸʽ-̂̄-΅·΋΍΢Ϗ-ЯѐѝѠѢѤѦѨѪѬѮѰѲѴѶѸѺ-ѾҀ-҃҅-ҐҒҔҕҘҚҜ-ҠҤ-ҪҬҭҰҲ' .
		'Ҵ-ҶҸҹҼ-ҿӁ-ӗӚ-ӜӞӠ-ӢӤӦӪ-ӲӴӶ-ՠֈ-׏׫-ؠً-ٳٵ-ٽٿ-څڇ-ڗڙ-ڨڪ-ڬڮڰ-ڽڿ-ۅۈ-ۊۍ-۔ۖ-਀਄਋-਎਑਒' .
		'਩਱਴਷਺਻਽੃-੆੉੊੎-੘੝੟-੯ੴ-჏ჱ-ẼẾ-\x{200b}\x{200d}-‒—-‗‚‛”--\x{fffd}]+$/D';

	/**
	 * Check whether some text is a valid link trail.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function isLinkTrail( string $text ): bool {
		return $text !== '' && preg_match( self::$linkTrailRegex, $text );
	}

	/**
	 * Convert mediawiki-format language code to a BCP47-compliant language
	 * code suitable for including in HTML.  See
	 * `GlobalFunctions.php::wfBCP47()` in mediawiki sources.
	 *
	 * @param string $code Mediawiki language code.
	 * @return string BCP47 language code.
	 */
	public static function bcp47n( $code ) {
		$codeSegment = explode( '-', $code );
		$codeBCP = [];
		foreach ( $codeSegment as $segNo => $seg ) {
			// when previous segment is x, it is a private segment and should be lc
			if ( $segNo > 0 && strtolower( $codeSegment[( $segNo - 1 )] ) == 'x' ) {
				$codeBCP[$segNo] = strtolower( $seg );
			// ISO 3166 country code
			} elseif ( ( strlen( $seg ) == 2 ) && ( $segNo > 0 ) ) {
				$codeBCP[$segNo] = strtoupper( $seg );
			// ISO 15924 script code
			} elseif ( ( strlen( $seg ) == 4 ) && ( $segNo > 0 ) ) {
				$codeBCP[$segNo] = ucfirst( strtolower( $seg ) );
			// Use lowercase for other cases
			} else {
				$codeBCP[$segNo] = strtolower( $seg );
			}
		}
		return implode( '-', $codeBCP );
	}
}
