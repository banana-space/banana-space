<?php

namespace CirrusSearch\Search;

/**
 * Escapes queries.
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
 */
class Escaper {

	/**
	 * @var string MediaWiki language code
	 */
	private $language;

	/**
	 * Allow leading wildcards?
	 * @var bool
	 */
	private $allowLeadingWildcard;

	/**
	 * @param string $language MediaWiki language code
	 * @param bool $allowLeadingWildcard
	 */
	public function __construct( $language, $allowLeadingWildcard = true ) {
		$this->language = $language;
		$this->allowLeadingWildcard = $allowLeadingWildcard;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function escapeQuotes( $text ) {
		if ( $this->language === 'he' ) {
			// Hebrew uses the double quote (") character as a standin for quotation marks (“”)
			// which delineate phrases.  It also uses double quotes as a standin for another
			// character (״), call a Gershayim, which mark acronyms.  Here we guess if the intent
			// was to mark a phrase, in which case we leave the quotes alone, or to mark an
			// acronym, in which case we escape them.
			return preg_replace( '/(\S+)(?<!\\\\)"(\S)/u', '\1\\"\2', $text );
		}
		return $text;
	}

	/**
	 * Make sure the query string part is well formed by escaping some syntax that we don't
	 * want users to get direct access to and making sure quotes are balanced.
	 * These special characters _aren't_ escaped:
	 * * and ?: Do a wildcard search against the stemmed text which isn't strictly a good
	 * idea but this is so rarely used that adding extra code to flip prefix searches into
	 * real prefix searches isn't really worth it.
	 * ~: Do a fuzzy match against the stemmed text which isn't strictly a good idea but it
	 * gets the job done and fuzzy matches are a really rarely used feature to be creating an
	 * extra index for.
	 * ": Perform a phrase search for the quoted term.  If the "s aren't balanced we insert one
	 * at the end of the term to make sure elasticsearch doesn't barf at us.
	 *
	 * @param string $string
	 * @return string
	 */
	public function fixupQueryStringPart( $string ) {
		// Escape characters that can be escaped with \\
		$string = preg_replace( '/(
				\(|     (?# no user supplied groupings)
				\)|
				\{|     (?# no exclusive range queries)
				}|
				\[|     (?# no inclusive range queries either)
				]|
				\^|     (?# no user supplied boosts at this point, though I cant think why)
				:|		(?# no specifying your own fields)
				\\\(?!") (?# the only acceptable escaping is for quotes)
			)/x', '\\\$1', $string );
		// Forward slash escaping doesn't work properly in all environments so we just eat them.   Nom.
		$string = str_replace( '/', ' ', $string );

		// Elasticsearch's query strings can't abide unbalanced quotes
		return $this->balanceQuotes( $string );
	}

	/**
	 * Make sure that all operators and lucene syntax is used correctly in the query string
	 * and store if this is a fuzzy query.
	 * If it isn't then the syntax escaped so it becomes part of the query text.
	 *
	 * @param string $string
	 * @return string fixed up query string
	 */
	public function fixupWholeQueryString( $string ) {
		$escapeBadSyntax = [ self::class, 'escapeBadSyntax' ];

		// Be careful when editing this method because the ordering of the replacements matters.

		// Escape ~ that don't follow a term or a quote
		$string = preg_replace_callback( '/(?<![\w"])~/u', $escapeBadSyntax, $string );

		// When allow leading wildcard is disabled elasticsearch will report an
		// error if these are unescaped. Escape ? and * that don't follow a term.
		if ( !$this->allowLeadingWildcard ) {
			$string = preg_replace_callback( '/(?<![\w])([?*])/u', $escapeBadSyntax, $string );
		}

		// Reduce token ranges to bare tokens without the < or >
		$string = preg_replace( '/(?:<|>)+([^\s])/u', '$1', $string );

		// Turn bad fuzzy searches into searches that contain a ~ and set $this->fuzzyQuery for good ones.
		$string = preg_replace_callback( '/(?<leading>\w)~(?<trailing>\S*)/u',
			function ( $matches ) use ( &$fuzzyQuery ) {
				if ( preg_match( '/^(|[0-2])$/', $matches[ 'trailing' ] ) ) {
					return $matches[ 0 ];
				} else {
					return $matches[ 'leading' ] . '\\~' .
						preg_replace( '/(?<!\\\\)~/', '\~', $matches[ 'trailing' ] );
				}
			}, $string );

		// Turn bad proximity searches into searches that contain a ~
		$string = preg_replace_callback( '/"~(?<trailing>\S*)/u', function ( $matches ) {
			if ( preg_match( '/\d+/', $matches[ 'trailing' ] ) ) {
				return $matches[ 0 ];
			} else {
				return '"\\~' . $matches[ 'trailing' ];
			}
		}, $string );

		// Escape +, -, and ! when not immediately followed by a term or when immediately
		// prefixed with a term.  Catches "foo-bar", "foo- bar", "foo - bar".  The only
		// acceptable use is "foo -bar" and "-bar foo".
		$string = preg_replace_callback( '/[+\-!]+(?!\w)/u', $escapeBadSyntax, $string );
		$string = preg_replace_callback( '/(?<!^|[ \\\\])[+\-!]+/u', $escapeBadSyntax, $string );

		// Escape || when not between terms
		$string = preg_replace_callback( '/^\s*\|\|/u', $escapeBadSyntax, $string );
		$string = preg_replace_callback( '/\|\|\s*$/u', $escapeBadSyntax, $string );

		// Lowercase AND and OR when not surrounded on both sides by a term.
		// Lowercase NOT when it doesn't have a term after it.
		$string = preg_replace_callback( '/^|(AND|OR|NOT)\s*(?:AND|OR)/u',
			[ self::class, 'lowercaseMatched' ], $string );
		$string = preg_replace_callback( '/(?:AND|OR|NOT)\s*$/u',
			[ self::class, 'lowercaseMatched' ], $string );

		return $string;
	}

	/**
	 * @param string[] $matches
	 * @return string
	 */
	private static function escapeBadSyntax( $matches ) {
		return "\\" . implode( "\\", str_split( $matches[ 0 ] ) );
	}

	/**
	 * @param string[] $matches
	 * @return string
	 */
	private static function lowercaseMatched( $matches ) {
		return strtolower( $matches[ 0 ] );
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function balanceQuotes( $text ) {
		if ( $this->unbalancedQuotes( $text ) ) {
			$text .= '"';
		}
		return $text;
	}

	/**
	 * @param string $text
	 * @param int $from
	 * @param int $to
	 * @return bool true if there are unbalanced quotes in the [$from, $to] range.
	 */
	public function unbalancedQuotes( $text, $from = 0, $to = -1 ) {
		$to = $to < 0 ? strlen( $text ) : $to;
		$inQuote = false;
		$inEscape = false;
		for ( $i = $from; $i < $to; $i++ ) {
			if ( $inEscape ) {
				$inEscape = false;
				continue;
			}
			switch ( $text[ $i ] ) {
				case '"':
					$inQuote = !$inQuote;
					break;
				case '\\':
					$inEscape = true;
			}
		}
		return $inQuote;
	}

	/**
	 * Unescape a given string
	 * @param string $query string to unescape
	 * @param string $escapeChar escape sequence
	 * @return string
	 */
	public function unescape( $query, $escapeChar = '\\' ) {
		$escapeChar = preg_quote( $escapeChar, '/' );
		return preg_replace( "/$escapeChar(.)/u", '$1', $query );
	}

	/**
	 * Is leading wildcard allowed?
	 *
	 * @return bool
	 */
	public function getAllowLeadingWildcard() {
		return $this->allowLeadingWildcard;
	}

	/**
	 * @return string
	 */
	public function getLanguage() {
		return $this->language;
	}
}
