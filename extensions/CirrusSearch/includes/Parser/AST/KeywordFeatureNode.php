<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;
use CirrusSearch\Query\KeywordFeature;

/**
 * Represents a keyword in the query
 */
class KeywordFeatureNode extends ParsedNode {

	/**
	 * @var KeywordFeature
	 */
	private $keyword;

	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var string
	 */
	private $value;

	/**
	 * @var string
	 */
	private $quotedValue;

	/**
	 * @var string
	 */
	private $delimiter;

	/**
	 * @var string
	 */
	private $suffix;

	/**
	 * Parsed value
	 * @see KeywordFeature::parseValue()
	 * @var array|null
	 */
	private $parsedValue;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param KeywordFeature $keyword
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $delimiter
	 * @param string $suffix
	 * @param array|null $parsedValue value as parsed by KeywordFeature::parseValue()
	 * @see KeywordFeature::parseValue()
	 */
	public function __construct(
		$startOffset,
		$endOffset,
		KeywordFeature $keyword,
		$key,
		$value,
		$quotedValue,
		$delimiter,
		$suffix,
		array $parsedValue = null
	) {
		parent::__construct( $startOffset, $endOffset );
		$this->keyword = $keyword;
		$this->key = $key;
		$this->value = $value;
		$this->quotedValue = $quotedValue;
		$this->delimiter = $delimiter;
		$this->suffix = $suffix;
		$this->parsedValue = $parsedValue;
	}

	/**
	 * The feature
	 * @return KeywordFeature
	 */
	public function getKeyword() {
		return $this->keyword;
	}

	/**
	 * The keyword prefix used
	 * @return string
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * The value (unescaped)
	 * @return string
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * The quoted as-is
	 * @return string
	 */
	public function getQuotedValue() {
		return $this->quotedValue;
	}

	/**
	 * The delimiter used to wrap the value
	 * @return string
	 */
	public function getDelimiter() {
		return $this->delimiter;
	}

	/**
	 * The optional value suffix used in the query
	 * @return string
	 */
	public function getSuffix() {
		return $this->suffix;
	}

	/**
	 * Return the value parsed by the KeywordFeature implementation
	 *
	 * NOTE: Only the keyword implementation knows what to do with this data
	 * @return array|null
	 * @see KeywordFeature::parseValue()
	 */
	public function getParsedValue() {
		return $this->parsedValue;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			"keyword" => array_merge(
				$this->baseParams(),
				array_filter( [
						"keyword" => get_class( $this->keyword ),
						"key" => $this->key,
						"value" => $this->value,
						"quotedValue" => $this->quotedValue,
						"delimiter" => $this->delimiter,
						"suffix" => $this->suffix,
						"parsedValue" => $this->parsedValue,
					], function ( $x ) {
						return $x !== null;
					}
				)
			)
		];
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitKeywordFeatureNode( $this );
	}
}
