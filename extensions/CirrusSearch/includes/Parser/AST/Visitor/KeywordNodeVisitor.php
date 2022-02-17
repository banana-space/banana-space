<?php

namespace CirrusSearch\Parser\AST\Visitor;

use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\FuzzyNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\PrefixNode;
use CirrusSearch\Parser\AST\WildcardNode;
use CirrusSearch\Parser\AST\WordsQueryNode;

/**
 * Simple KeywordFeatureNode visitor
 */
abstract class KeywordNodeVisitor extends LeafVisitor {

	/**
	 * @var string[] class names to accept (empty to accept all)
	 */
	private $keywordClasses;

	/**
	 * @param array $excludeOccurs list of boolean accurence type to ignore
	 * @param array $keywordClasses list of KeywordFeature classes to accept (empty to accept them all)
	 */
	public function __construct( array $excludeOccurs = [], array $keywordClasses = [] ) {
		parent::__construct( $excludeOccurs );
		$this->keywordClasses = $keywordClasses;
	}

	/**
	 * @param WordsQueryNode $node
	 */
	final public function visitWordsQueryNode( WordsQueryNode $node ) {
	}

	/**
	 * @param PhraseQueryNode $node
	 */
	final public function visitPhraseQueryNode( PhraseQueryNode $node ) {
	}

	/**
	 * @param PhrasePrefixNode $node
	 */
	final public function visitPhrasePrefixNode( PhrasePrefixNode $node ) {
	}

	/**
	 * @param FuzzyNode $node
	 */
	final public function visitFuzzyNode( FuzzyNode $node ) {
	}

	/**
	 * @param PrefixNode $node
	 */
	final public function visitPrefixNode( PrefixNode $node ) {
	}

	/**
	 * @param WildcardNode $node
	 */
	final public function visitWildcardNode( WildcardNode $node ) {
	}

	/**
	 * @param EmptyQueryNode $node
	 */
	final public function visitEmptyQueryNode( EmptyQueryNode $node ) {
	}

	/**
	 * @param KeywordFeatureNode $node
	 */
	final public function visitKeywordFeatureNode( KeywordFeatureNode $node ) {
		if ( $this->filterKeyword( $node ) ) {
			$this->doVisitKeyword( $node );
		}
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return bool
	 */
	private function filterKeyword( KeywordFeatureNode $node ) {
		if ( $this->keywordClasses === [] ) {
			return true;
		}
		foreach ( $this->keywordClasses as $class ) {
			if ( $node->getKeyword() instanceof $class ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param KeywordFeatureNode $node
	 */
	abstract public function doVisitKeyword( KeywordFeatureNode $node );
}
