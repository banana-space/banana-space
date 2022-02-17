<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use Wikimedia\Assert\Assert;

/**
 * Implements abstract handling of keyword features that are composed of a
 * keyword followed by a colon then an optionally quoted value. For consistency
 * most query features should be implemented this way using the default
 * getValueRegex() where possible.
 */
abstract class SimpleKeywordFeature implements KeywordFeature {
	/**
	 * NOTE: will be removed once all implementations implement getKeywordStrings
	 * (transitional state to change the visibility of getKeywords())
	 * @return string[] The list of keywords this feature is supposed to match
	 */
	abstract protected function getKeywords();

	/**
	 * @return string[]
	 */
	public function getKeywordPrefixes() {
		return $this->getKeywords();
	}

	/**
	 * Whether this keyword allows empty value.
	 * @return bool true to allow the keyword to appear in an empty form
	 */
	public function allowEmptyValue() {
		return false;
	}

	/**
	 * Whether this keyword can have a value
	 * @return bool
	 */
	public function hasValue() {
		return true;
	}

	/**
	 * Whether this keyword is greedy consuming the rest of the string.
	 * NOTE: do not override, greedy keywords will eventually be removed in the future
	 * @return bool
	 */
	public function greedy() {
		return false;
	}

	/**
	 * Whether this keyword can appear only at the beginning of the query
	 * (excluding spaces)
	 * @return bool
	 */
	public function queryHeader() {
		return false;
	}

	/**
	 * Determine the name of the feature being set in SearchContext::addSyntaxUsed
	 * Defaults to $key
	 *
	 * @param string $key
	 * @param string $valueDelimiter the delimiter used to wrap the value
	 * @return string
	 *  '"' when parsing keyword:"test"
	 *  '' when parsing keyword:test
	 */
	public function getFeatureName( $key, $valueDelimiter ) {
		return $key;
	}

	/**
	 * List of value delimiters supported (must be an array of single byte char)
	 * @return string[][] list of delimiters options
	 */
	public function getValueDelimiters() {
		return [ [ 'delimiter' => '"' ] ];
	}

	/**
	 * Parse the value of the keyword.
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array|null|false null when nothing is to be kept, false when the value is refused
	 * (only allowed for keywords that allows empty value)
	 * @see self::allowEmptyValue
	 */
	public function parseValue(
		$key,
		$value,
		$quotedValue,
		$valueDelimiter,
		$suffix,
		WarningCollector $warningCollector
	) {
		return null;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		return CrossSearchStrategy::hostWikiOnlyStrategy();
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param SearchConfig $config
	 * @param WarningCollector $warningCollector
	 * @return array
	 */
	public function expand(
		KeywordFeatureNode $node,
		SearchConfig $config,
		WarningCollector $warningCollector
	) {
		return [];
	}

	/**
	 * Captures either a quoted or unquoted string. Quoted strings may have
	 * escaped (\") quotes embedded in them.
	 *
	 * @return string A piece of a regular expression (not wrapped in //) that
	 * matches the acceptable values for this feature. Must contain quoted and
	 * unquoted capture groups.
	 */
	private function getValueRegex() {
		Assert::invariant( $this->hasValue(), __METHOD__ . ' called but hasValue() is false' );
		if ( $this->greedy() ) {
			Assert::precondition( !$this->allowEmptyValue(), "greedy keywords must not accept empty value" );
			// XXX: we ignore value delimiter for greedy keywords
			Assert::precondition( $this->getValueDelimiters() === [ [ 'delimiter' => '"' ] ],
				"getValueDelimiters() must not be overridden with greedy keywords" );
			// XXX: we send raw value to the keyword
			return '(?<unquoted>.+)';
		} else {
			$quantifier = $this->allowEmptyValue() ? '*' : '+';
			// Collect all quoted vlaue delimiter (usually only " but can be / for regexes)
			$allDelims = '';
			$optionalSuffixes = [];
			foreach ( $this->getValueDelimiters() as $delimConfig ) {
				Assert::precondition( strlen( $delimConfig['delimiter'] ) === 1,
					"Value delimiter must be a single byte char" );
				$delim = preg_quote( $delimConfig['delimiter'], '/' );
				$allDelims .= $delim;
				if ( isset( $delimConfig['suffixes'] ) ) {
					// Use lookbehind to only match the suffix if it was used with the proper delimiter
					// i.e i should only be matched in /regex/i not "regex"i
					$optionalSuffixes[] = "(?<=$delim)" . preg_quote( $delimConfig['suffixes'], '/' );
				}
			}
			$quotedValue = "(?<delim>[$allDelims])" . // Capture the delimiter used to use in backreferences
				// use negative lookbehind to consume any char that is not the captured delimiter
				// but also accept to escape the captured delimiter
				"(?<quoted>(?:\\\\\g{delim}|(?!\g{delim}).)*)" .
				"\g{delim}";
			if ( !empty( $optionalSuffixes ) ) {
				$quotedValue .= "(?<suffixes>" . implode( '|', $optionalSuffixes ) . ')?';
			}
			// XXX: we support only " to break the unquoted value
			$unquotedValue = "(?<unquoted>[^\"\s]$quantifier)";
			return $quotedValue . '|' . $unquotedValue;
		}
	}

	/**
	 * Applies the detected keyword from the search term. May apply changes
	 * either to $context directly, or return a filter to be added.
	 *
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	abstract protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated );

	/**
	 * Fully featured apply method which delegates to doApply by default.
	 *
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped and escaped
	 *  quotes un-escaped.
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @param string $delimiter the delimiter char used to wrap the keyword value ('"' in intitle:"test")
	 * @param string $suffix the optional suffix used after the value ('i' in insource:/regex/i)
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	public function doApplyExtended(
		SearchContext $context,
		$key,
		$value,
		$quotedValue,
		$negated,
		$delimiter,
		$suffix
	) {
		return $this->doApply( $context, $key, $value, $quotedValue, $negated );
	}

	/**
	 * @param SearchContext $context
	 * @param string $term Search query
	 * @return string Remaining search query
	 */
	public function apply( SearchContext $context, $term ) {
		$keyListRegex = implode(
			'|',
			array_map(
				function ( $kw ) {
					return preg_quote( $kw, '/' );
				},
				$this->getKeywords()
			)
		);
		// Hook to the beginning allowing optional spaces if we are a queryHeader
		// otherwise lookbehind allowing begin or space.
		$begin = $this->queryHeader() ? '(?:^\s*)' : '(?<=^|\s)';
		$keywordRegex = '(?<key>-?(?:' . $keyListRegex . '))';
		$valueSideRegex = '';
		if ( $this->hasValue() ) {
			$valueRegex = '(?<value>' . $this->getValueRegex() . ')';
			// If we allow empty values we don't allow spaces between
			// the keyword and its value, a space would mean "empty value"
			$spacesAfterSep = $this->allowEmptyValue() ? '' : '\s*';
			$valueSideRegex = "${spacesAfterSep}{$valueRegex}\\s?";
		}

		$callback = function ( $match ) use ( $context ) {
			$key = $match['key'];
			Assert::invariant( $this->hasValue() === isset( $match['value'] ), 'a value must have matched' );
			$quotedValue = '';
			$value = '';
			$valueDelimiter = '';
			$valueSuffix = '';
			if ( $this->hasValue() ) {
				$quotedValue = $match['value'];
				if ( isset( $match["unquoted"] ) ) {
					$value = $match["unquoted"];
				} else {
					$valueDelimiter = $match['delim'];
					$value = str_replace( "\\$valueDelimiter", $valueDelimiter, $match["quoted"] );
				}
				if ( isset( $match["suffixes"] ) ) {
					$valueSuffix = $match["suffixes"];
					$quotedValue = rtrim( $quotedValue, $valueSuffix );
				}
			}
			if ( $key[0] === '-' ) {
				$negated = true;
				$key = substr( $key, 1 );
			} else {
				$negated = false;
			}

			$context->addSyntaxUsed( $this->getFeatureName( $key, $valueDelimiter ) );
			list( $filter, $keepText ) = $this->doApplyExtended(
				$context,
				$key,
				$value,
				$quotedValue,
				$negated,
				$valueDelimiter,
				$valueSuffix
			);
			if ( $filter !== null ) {
				if ( $negated ) {
					$context->addNotFilter( $filter );
				} else {
					$context->addFilter( $filter );
				}
			}
			// FIXME: this adds a trailing space if this is the last keyword
			return $keepText ? "$quotedValue " : '';
		};

		return preg_replace_callback(
			"/{$begin}{$keywordRegex}:${valueSideRegex}/",
			$callback,
			$term
		);
	}
}
