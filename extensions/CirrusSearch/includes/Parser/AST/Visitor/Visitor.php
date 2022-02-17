<?php

namespace CirrusSearch\Parser\AST\Visitor;

use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\EmptyQueryNode;
use CirrusSearch\Parser\AST\FuzzyNode;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\NamespaceHeaderNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\ParsedBooleanNode;
use CirrusSearch\Parser\AST\PhrasePrefixNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Parser\AST\PrefixNode;
use CirrusSearch\Parser\AST\WildcardNode;
use CirrusSearch\Parser\AST\WordsQueryNode;

/**
 * AST Visitor
 */
interface Visitor {

	/**
	 * @param ParsedBooleanNode $node
	 */
	public function visitParsedBooleanNode( ParsedBooleanNode $node );

	/**
	 * @param BooleanClause $clause
	 */
	public function visitBooleanClause( BooleanClause $clause );

	/**
	 * @param WordsQueryNode $node
	 */
	public function visitWordsQueryNode( WordsQueryNode $node );

	/**
	 * @param PhraseQueryNode $node
	 */
	public function visitPhraseQueryNode( PhraseQueryNode $node );

	/**
	 * @param PhrasePrefixNode $node
	 */
	public function visitPhrasePrefixNode( PhrasePrefixNode $node );

	/**
	 * @param NegatedNode $node
	 */
	public function visitNegatedNode( NegatedNode $node );

	/**
	 * @param FuzzyNode $node
	 */
	public function visitFuzzyNode( FuzzyNode $node );

	/**
	 * @param PrefixNode $node
	 */
	public function visitPrefixNode( PrefixNode $node );

	/**
	 * @param WildcardNode $node
	 */
	public function visitWildcardNode( WildcardNode $node );

	/**
	 * @param EmptyQueryNode $node
	 */
	public function visitEmptyQueryNode( EmptyQueryNode $node );

	/**
	 * @param KeywordFeatureNode $node
	 */
	public function visitKeywordFeatureNode( KeywordFeatureNode $node );

	/**
	 * @param NamespaceHeaderNode $node
	 */
	public function visitNamespaceHeader( NamespaceHeaderNode $node );
}
