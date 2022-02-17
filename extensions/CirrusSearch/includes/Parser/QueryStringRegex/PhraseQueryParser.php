<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Search\Escaper;
use Wikimedia\Assert\Assert;

/**
 * Detects phrase queries:
 * "simple phrase" : use the plain fields
 * "simple phrase"~ : use the stem fields
 * "simple phrase"~2 : force the slop to be 2
 * "simple phrase"~2~ : force the slop to be 2 and use the stem fields
 *
 * The phrase can be negated using a ! or -
 * Quotes can be escaped using \
 *
 * Supports phrase prefix as well:
 * "simple phras*"
 * iff slop and stem are not provided otherwise we send a simple phrase node
 */
class PhraseQueryParser {

	/**
	 * Start of a phrase
	 */
	const PHRASE_START = '/\G(?<negate>-|!)?"/';

	/**
	 * Normal phrase detection
	 */
	const PHRASE_REGEX = '/\G(?<negate>-|!)?"(?<value>(?:\\\\.|[^"])*)"(?<slop>~(?<slopvalue>\d+))?(?<fuzzy>~)?/';

	/**
	 * @var Escaper
	 */
	private $escaper;

	public function __construct( Escaper $escaper ) {
		$this->escaper = $escaper;
	}

	/**
	 * @param string $query
	 * @param int $start
	 * @param int $end
	 * @return PhraseQueryNode|PhrasePrefixNode|null
	 */
	public function parse( $query, $start, $end ) {
		$match = [];
		Assert::precondition( $start < $end, '$start < $end' );
		Assert::precondition( $end <= strlen( $query ), '$end <= strlen( $query )' );
		if ( preg_match( self::PHRASE_REGEX, $query, $match, 0, $start ) === 1 ) {
			if ( strlen( $match[0] ) + $start <= $end ) {
				$slop = -1;
				$phrasePrefix = false;
				$quotedvalue = $match['value'];
				// Detects phrase prefix (still unclear why we do not allow *)
				if ( preg_match( '/^(?:\\\\.|[^*])+[*]$/', $quotedvalue ) === 1 ) {
					$phrasePrefix = true;
				}
				if ( isset( $match['slopvalue'] ) && strlen( $match['slopvalue'] ) > 0 ) {
					$slop = intval( $match['slopvalue'] );
					$phrasePrefix = false;
				}
				$stem = false;
				if ( isset( $match['fuzzy'] ) ) {
					$stem = true;
					$phrasePrefix = false;
				}
				$negated = $match['negate'];
				$phraseStart = $start + strlen( $match['negate'] );
				$value = $this->escaper->unescape( $quotedvalue );
				if ( $phrasePrefix ) {
					$node = new PhrasePrefixNode( $phraseStart, strlen( $match[0] ) + $start, rtrim( $value, '*' ) );
				} else {
					$node = new PhraseQueryNode( $phraseStart, strlen( $match[0] ) + $start, $value, $slop,
						$stem );
				}
				if ( $negated !== '' ) {
					$node = new NegatedNode( $start, $node->getEndOffset(), $node, $negated );
				}
				return $node;
			}
		}
		return null;
	}
}
