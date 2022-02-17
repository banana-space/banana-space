<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\ParsedNode;
use CirrusSearch\Parser\AST\ParseWarning;
use CirrusSearch\Query\KeywordFeature;
use CirrusSearch\WarningCollector;
use Wikimedia\Assert\Assert;

/**
 * Parser for KeywordFeature
 */
class KeywordParser implements WarningCollector {

	/**
	 * @var int
	 */
	private $currentOffset;

	/**
	 * @var ParseWarning[]
	 */
	private $warnings = [];

	/**
	 * @param string $query
	 * @param KeywordFeature $feature
	 * @param OffsetTracker $tracker
	 * @param int $startOffset start offset of the query in $query
	 * @return ParsedNode[]
	 */
	public function parse( $query, KeywordFeature $feature, OffsetTracker $tracker, $startOffset = 0 ) {
		if ( $feature->greedy() ) {
			Assert::precondition( !$feature->allowEmptyValue(),
				"greedy keywords must not accept empty value" );
			// XXX: we ignore value delimiter for greedy keywords
			Assert::precondition( $feature->getValueDelimiters() === [ [ 'delimiter' => '"' ] ],
				"getValueDelimiters() must not be overridden with greedy keywords" );
		}
		$offset = $tracker->getMinimalUnconsumedOffset( $startOffset );
		$keyListRegex = implode(
			'|',
			array_map(
				function ( $kw ) {
					return preg_quote( $kw, '/' );
				},
				$feature->getKeywordPrefixes()
			)
		);
		// Hook to the beginning allowing optional spaces if we are a queryHeader
		// otherwise lookbehind allowing begin or space.
		// \G is similar to ^ but also works when offset is set is if we ran substr on it
		$begin = $feature->queryHeader() ? '(?:\G[\pZ\pC]*)' : '(?<=\G|[\pZ\pC])';
		$keywordRegex = '(?<key>-?(?:' . $keyListRegex . '))';
		$valueSideRegex = '';
		if ( $feature->hasValue() ) {
			$valueRegex = '(?<value>' . $this->getValueRegex( $feature ) . ')';
			// If we allow empty values we don't allow spaces between
			// the keyword and its value, a space would mean "empty value"
			$spacesAfterSep = $feature->allowEmptyValue() ? '' : '[\pZ\pC]*';
			$valueSideRegex = "${spacesAfterSep}{$valueRegex}";
		}
		$matches = [];
		preg_match_all( "/{$begin}{$keywordRegex}(?<colon>:)${valueSideRegex}/u",
			$query, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $offset );
		$output = [];
		foreach ( $matches as $match ) {
			$key = $match['key'][0];
			Assert::invariant( $feature->hasValue() === isset( $match['value'] ),
				'a value must have matched if the keyword wants a value.' );
			$quotedValue = '';
			$value = '';
			$valueDelimiter = '';
			$valueSuffix = '';
			$valueStart = $match['colon'][1] + strlen( $match['colon'][0] );
			if ( $feature->hasValue() ) {
				$quotedValue = $match['value'][0];
				if ( isset( $match['unquoted'] ) && $match['unquoted'][1] >= 0 ) {
					$value = $match['unquoted'][0];
				} else {
					$valueDelimiter = $match['delim'][0];
					$value = str_replace( "\\$valueDelimiter", $valueDelimiter, $match['quoted'][0] );
				}
				if ( isset( $match['suffixes'] ) && $match['suffixes'][1] >= 1 ) {
					$valueSuffix = $match['suffixes'][0];
					$quotedValue = rtrim( $quotedValue, $valueSuffix );
				}
			}

			$negationChar = '';
			if ( $key[0] === '-' ) {
				$negationChar = $key[0];
				$key = substr( $key, 1 );
			}
			// We take the key as start offset, the whole match can eat some spaces
			// at the beginning for query headers.
			$kwStart = $match['key'][1] + strlen( $negationChar );
			$wholeStart = $match['key'][1];
			// $end is whole match length minus chars between start and key
			$end = $wholeStart + strlen( $match[0][0] ) - ( $wholeStart - $match[0][1] );
			$parsedValue = null;
			if ( $feature->hasValue() && $quotedValue !== '' ) {
				// Set the current offset so that we can collect warnings at the keyword offset
				$this->currentOffset = $valueStart;
				$parsedValue = $feature->parseValue(
					$key, $value, $quotedValue, $valueDelimiter, $valueSuffix, $this );
				if ( $parsedValue === false ) {
					Assert::postcondition( $feature->allowEmptyValue(),
						'Only features accepting empty value can reject a value' );
					$value = '';
					$quotedValue = '';
					$end = $valueStart;
					$parsedValue = null;
				}
			}
			if ( !$tracker->overlap( $wholeStart, $end ) ) {
				$node = new KeywordFeatureNode( $kwStart, $end, $feature, $key, $value, $quotedValue,
					$valueDelimiter, $valueSuffix, $parsedValue );
				if ( $negationChar !== '' ) {
					$node = new NegatedNode( $wholeStart, $end, $node, $negationChar );
				}
				$output[] = $node;
			}
		}
		return $output;
	}

	/**
	 * @param KeywordFeature $feature
	 * @return string
	 */
	private function getValueRegex( KeywordFeature $feature ) {
		Assert::invariant( $feature->hasValue(), __METHOD__ . ' called but hasValue() is false' );
		if ( $feature->greedy() ) {
			// XXX: we send raw value to the keyword
			return '(?<unquoted>.+)';
		} else {
			$quantifier = $feature->allowEmptyValue() ? '*' : '+';
			// Collect all quoted vlaue delimiter (usually only " but can be / for regexes)
			$allDelims = '';
			$optionalSuffixes = [];
			foreach ( $feature->getValueDelimiters() as $delimConfig ) {
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
			$unquotedValue = "(?<unquoted>[^\"\pZ\pC]$quantifier)";
			return "(?:$quotedValue|$unquotedValue)";
		}
	}

	/**
	 * @return ParseWarning[]
	 */
	public function getWarnings() {
		return $this->warnings;
	}

	/**
	 * Add a warning
	 *
	 * @param string $message i18n message key
	 * @param mixed ...$params
	 */
	public function addWarning( $message, ...$params ) {
		$this->warnings[] = new ParseWarning( $message, $this->currentOffset, [], null, $params );
	}
}
