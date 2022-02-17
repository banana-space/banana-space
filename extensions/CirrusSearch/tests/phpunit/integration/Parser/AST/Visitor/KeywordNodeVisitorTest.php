<?php

namespace CirrusSearch\Parser\AST\Visitor;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\AST\BooleanClause;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\QueryParserFactory;
use CirrusSearch\Query\FilterQueryFeature;
use CirrusSearch\Query\InTitleFeature;
use PHPUnit\Framework\TestCase;

class KeywordNodeVisitorTest extends CirrusIntegrationTestCase {

	public function provideQueries() {
		return [
			'nothing' => [
				'',
				[],
				[],
				[],
			],
			'word' => [
				'',
				[],
				[],
				[],
			],
			'specific filter intitle:test' => [
				'intitle:test',
				[ [ 'negated' => false, 'feature' => 'intitle' ] ],
				[ InTitleFeature::class ],
				[],
			],
			'all filters intitle:test' => [
				'intitle:test',
				[ [ 'negated' => false, 'feature' => 'intitle' ] ],
				[ FilterQueryFeature::class ],
				[],
			],
			'specific filter -intitle:test' => [
				'-intitle:test',
				[ [ 'negated' => true, 'feature' => 'intitle' ] ],
				[ InTitleFeature::class ],
				[],
			],
			'multiple filter' => [
				'-intitle:test insource:test',
				[
					[ 'negated' => true, 'feature' => 'intitle' ],
					[ 'negated' => false, 'feature' => 'insource' ]
				],
				[ FilterQueryFeature::class ],
				[],
			],
			'multiple filter with should' => [
				'-intitle:test insource:test OR insource:/test/',
				[
					[ 'negated' => true, 'feature' => 'intitle' ],
					[ 'negated' => false, 'feature' => 'insource' ],
					[ 'negated' => false, 'feature' => 'regex' ]
				],
				[ FilterQueryFeature::class ],
				[],
			],
			'multiple filter exluded NOT' => [
				'-intitle:test insource:test',
				[
					[ 'negated' => false, 'feature' => 'insource' ]
				],
				[ FilterQueryFeature::class ],
				[ BooleanClause::MUST_NOT ],
			],
			'fully featured' => [
				'test~ test* tes*te "hop" OR "hop hop*" -intitle:test boost-template:test insource:test',
				[
					[ 'negated' => true, 'feature' => 'intitle' ],
					[ 'negated' => false, 'feature' => 'insource' ]
				],
				[ FilterQueryFeature::class ],
				[],
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Parser\AST\Visitor\KeywordNodeVisitor
	 * @covers \CirrusSearch\Parser\AST\Visitor\LeafVisitor
	 * @covers \CirrusSearch\Parser\AST\KeywordFeatureNode::accept()
	 * @covers \CirrusSearch\Parser\AST\FuzzyNode::accept()
	 * @covers \CirrusSearch\Parser\AST\WordsQueryNode::accept()
	 * @covers \CirrusSearch\Parser\AST\ParsedBooleanNode::accept()
	 * @covers \CirrusSearch\Parser\AST\WildcardNode::accept()
	 * @covers \CirrusSearch\Parser\AST\PrefixNode::accept()
	 * @covers \CirrusSearch\Parser\AST\PhrasePrefixNode::accept()
	 * @covers \CirrusSearch\Parser\AST\EmptyQueryNode::accept()
	 * @covers \CirrusSearch\Parser\AST\PhraseQueryNode::accept()
	 * @covers \CirrusSearch\Parser\AST\BooleanClause::accept()
	 * @dataProvider provideQueries
	 */
	public function test( $term, array $states, $classFilter, $exlusionFilter ) {
		$parser = QueryParserFactory::newFullTextQueryParser(
			new HashSearchConfig( [] ),
			$this->namespacePrefixParser()
		);
		$visitor = new class( $exlusionFilter, $classFilter, $states ) extends KeywordNodeVisitor {
			/**
			 * @var int
			 */
			public $nbCall = 0;

			/**
			 * @var array assertions
			 */
			private $states;

			public function __construct( array $excludeOccurs, array $keywordClasses, array $states ) {
				parent::__construct( $excludeOccurs, $keywordClasses );
				$this->states = $states;
			}

			public function doVisitKeyword( KeywordFeatureNode $node ) {
				TestCase::assertThat( $this->nbCall, TestCase::lessThan( count( $this->states ) ) );
				$assertionStates = $this->states[$this->nbCall++];
				TestCase::assertEquals( $assertionStates['negated'], $this->negated() );
				TestCase::assertEquals(
					$assertionStates['feature'],
					$node->getKeyword()->getFeatureName( $node->getKey(), $node->getDelimiter() )
				);
			}
		};
		$parser->parse( $term )->getRoot()
			->accept( $visitor );

		$this->assertEquals( count( $states ), $visitor->nbCall );
	}
}
