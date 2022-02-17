<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\FuzzyNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\NamespaceHeaderNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\ParsedBooleanNode;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\PrefixNode;
use CirrusSearch\Parser\AST\Visitor\Visitor;
use CirrusSearch\Parser\AST\WildcardNode;
use CirrusSearch\Parser\AST\WordsQueryNode;

/**
 * Basic classifier to identify queries like:
 * - simple words: foo bar
 * - simple phrase: "foo bar"
 * - simple words + simple phrase; foo "bar baz"
 * - complex: any queries that use a keyword, or any non trivial features
 * - bogus queries: queries where a bogus pattern have been identified at
 * 	 parse time
 */
class BasicQueryClassifier implements ParsedQueryClassifier, Visitor {

	/**
	 * The simplest query ever: only words
	 */
	const SIMPLE_BAG_OF_WORDS = 'simple_bag_of_words';

	/**
	 * Only quoted words
	 */
	const SIMPLE_PHRASE = 'simple_phrase_query';

	/**
	 * A simple bag of words query with some quoted words
	 */
	const BAG_OF_WORDS_WITH_PHRASE = 'bag_of_words_with_phrase_query';

	/**
	 * Expert: a query that uses some special syntax such as:
	 * - wildcards/fuzzy/word prefix
	 * - explicit boolean expression
	 * - complex phrase (phrase prefix, non default slop)
	 */
	const COMPLEX_QUERY = 'complex_query';

	/**
	 * Query that was fixed/corrected
	 */
	const BOGUS_QUERY = 'bogus_query';

	/**
	 * @var bool
	 */
	private $hasWords;

	/**
	 * @var bool
	 */
	private $hasSimplePhrase;

	/**
	 * @var bool
	 */
	private $hasComplex;

	/**
	 * @var int
	 */
	private $depth;

	/**
	 * @var int
	 */
	private $maxDepth;

	/**
	 * @param ParsedQuery $query
	 * @return string[]
	 */
	public function classify( ParsedQuery $query ) {
		$this->hasWords = false;
		$this->hasSimplePhrase = false;
		$this->hasComplex = false;
		$this->depth = 0;
		$this->maxDepth = 0;

		$classes = [];
		if ( $query->getParseWarnings() !== [] ) {
			$classes[] = self::BOGUS_QUERY;
		}

		$query->getRoot()->accept( $this );

		if ( $this->hasComplex ) {
			$classes[] = self::COMPLEX_QUERY;
			// @phan-suppress-next-line PhanSuspiciousValueComparison
		} elseif ( $this->maxDepth === 0 && $this->hasWords && !$this->hasSimplePhrase ) {
			$classes[] = self::SIMPLE_BAG_OF_WORDS;
		} elseif ( $this->maxDepth === 0 && !$this->hasWords && $this->hasSimplePhrase ) {
			$classes[] = self::SIMPLE_PHRASE;
		} elseif ( $this->maxDepth === 1 && $this->hasWords && $this->hasSimplePhrase ) {
			$classes[] = self::BAG_OF_WORDS_WITH_PHRASE;
		}

		return $classes;
	}

	/**
	 * @param WordsQueryNode $node
	 */
	public function visitWordsQueryNode( WordsQueryNode $node ) {
		$this->hasWords = true;
	}

	/**
	 * @param PhraseQueryNode $node
	 */
	public function visitPhraseQueryNode( PhraseQueryNode $node ) {
		if ( $node->isStem() || $node->getSlop() !== -1 ) {
			$this->hasComplex = true;
		} elseif ( !$node->isUnbalanced() ) {
			$this->hasSimplePhrase = true;
		}
	}

	/**
	 * @param PhrasePrefixNode $node
	 */
	public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @param FuzzyNode $node
	 */
	public function visitFuzzyNode( FuzzyNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @param PrefixNode $node
	 */
	public function visitPrefixNode( PrefixNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @param WildcardNode $node
	 */
	public function visitWildcardNode( WildcardNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @param EmptyQueryNode $node
	 */
	public function visitEmptyQueryNode( EmptyQueryNode $node ) {
	}

	/**
	 * @param KeywordFeatureNode $node
	 */
	public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @param ParsedBooleanNode $node
	 */
	public function visitParsedBooleanNode( ParsedBooleanNode $node ) {
		if ( $this->hasComplex ) {
			// we can quickly skip, this query cannot belong to this class
			return;
		}
		foreach ( $node->getClauses() as $clause ) {
			$clause->accept( $this );
		}
	}

	/**
	 * @param BooleanClause $clause
	 */
	public function visitBooleanClause( BooleanClause $clause ) {
		$this->depth++;
		$this->maxDepth = max( $this->depth, $this->maxDepth );
		$this->hasComplex = $this->hasComplex || $clause->isExplicit() || $clause->getOccur() === BooleanClause::MUST_NOT;
		$clause->getNode()->accept( $this );
		$this->depth--;
	}

	/**
	 * @param NegatedNode $node
	 */
	public function visitNegatedNode( NegatedNode $node ) {
		$this->hasComplex = true;
	}

	/**
	 * @return string[]
	 */
	public function classes() {
		return [
			self::SIMPLE_BAG_OF_WORDS,
			self::SIMPLE_PHRASE,
			self::BAG_OF_WORDS_WITH_PHRASE,
			self::COMPLEX_QUERY,
			self::BOGUS_QUERY,
		];
	}

	/**
	 * @param NamespaceHeaderNode $node
	 */
	public function visitNamespaceHeader( NamespaceHeaderNode $node ) {
	}
}
