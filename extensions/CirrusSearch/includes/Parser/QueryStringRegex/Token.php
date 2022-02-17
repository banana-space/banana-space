<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\Parser\AST\ParsedNode;
use Wikimedia\Assert\Assert;

/**
 * A token used in parsing the cirrus fulltext syntax
 */
class Token {

	const EOF = 0;

	/** explicit boolean AND */
	const BOOL_AND = 1;

	/** explicit boolean OR */
	const BOOL_OR = 2;

	/** explicit negation */
	const NOT = 3;

	/**
	 * Parsed node, due to its "mixed" nature
	 * the parser is able is able to spawn complex
	 * nodes directly from the query string without using
	 * tokens. The PARSED_NODE token type represent
	 * this type of "complex" token
	 */
	const PARSED_NODE = 4;

	const WHITESPACE = 5;

	/**
	 * @var string[] token type labels
	 */
	private static $TYPE_LABEL = [
		self::EOF => 'EOF',
		self::BOOL_AND => 'AND',
		self::BOOL_OR => 'OR',
		self::NOT => 'NOT',
		self::PARSED_NODE => 'QUERY',
		self::WHITESPACE => 'WHITESPACE',
	];

	/**
	 * @var int start offset
	 */
	private $start;

	/**
	 * @var int end offset (excl)
	 */
	private $end;

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var int|null token type
	 */
	private $type;

	/**
	 * @var string|null token image cache
	 */
	private $image;

	/**
	 * @var ParsedNode|null
	 */
	private $node;

	/**
	 * @param string $query
	 */
	public function __construct( $query ) {
		Assert::parameter( $query !== null, '$query', 'cannot be null' );
		$this->query = $query;
		$this->reset();
	}

	/**
	 * Reset the token state so that it can be reused
	 */
	public function reset() {
		$this->start = -1;
		$this->end = -1;
		$this->type = null;
		$this->image = null;
	}

	/**
	 * Get the image of the token in the query
	 * @return bool|null|string
	 */
	public function getImage() {
		Assert::precondition( $this->start >= 0 && $this->end >= 0, 'Trying to get token image at offset -1' );
		if ( $this->image === null ) {
			$this->image = substr( $this->query, $this->start, $this->end - $this->start );
		}
		return $this->image;
	}

	/**
	 * the token type
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param int $type token type
	 * @param int $start offset
	 * @param int $end offset (exc)
	 */
	public function setType( $type, $start, $end ) {
		$this->type = $type;
		$this->setOffsets( $start, $end );
	}

	/**
	 * @param int $start offset
	 * @param int $end offset (exc)
	 */
	public function setOffsets( $start, $end ) {
		$len = strlen( $this->query );
		Assert::precondition( $start < $end && $start < $len && $end <= $len,
			'invalid $start and $end param' );
		$this->start = $start;
		$this->end = $end;
	}

	public function eof() {
		$this->type = self::EOF;
		$this->start = -1;
		$this->end = -1;
	}

	/**
	 * Initialize the token from a parsed node
	 *
	 * @param ParsedNode $node
	 */
	public function node( ParsedNode $node ) {
		$this->setType( self::PARSED_NODE, $node->getStartOffset(), $node->getEndOffset() );
		$this->node = $node;
	}

	/**
	 * @return int start offset
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @return int end offset (excl)
	 */
	public function getEnd() {
		return $this->end;
	}

	/**
	 * @return bool true if this token can be ignored
	 */
	public function ignorable() {
		return $this->type === self::WHITESPACE;
	}

	/**
	 * Get the node if the token was initialized from a pre-parsed
	 * node.
	 * @return ParsedNode|null
	 */
	public function getNode() {
		return $this->node;
	}

	/**
	 * Copy state from this token to the token
	 * argument
	 * @param Token $lookBehind
	 */
	public function copyTo( Token $lookBehind ) {
		$lookBehind->query = $this->query;
		$lookBehind->start = $this->start;
		$lookBehind->end = $this->end;
		$lookBehind->image = $this->image;
		$lookBehind->node = $this->node;
		$lookBehind->type = $this->type;
	}

	/**
	 * @param int[]|int $types
	 * @return string[] type labels
	 */
	public static function getTypeLabels( $types ) {
		if ( is_int( $types ) ) {
			return [ self::getTypeLabel( $types ) ];
		}
		return array_map( function ( $type ) {
			return self::$TYPE_LABEL[$type];
		}, $types );
	}

	/**
	 * @param int $type
	 * @return string type labels
	 */
	public static function getTypeLabel( $type ) {
		return self::$TYPE_LABEL[$type];
	}
}
