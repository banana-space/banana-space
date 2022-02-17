<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * Test More Like This keyword feature.
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
 *
 * @covers \CirrusSearch\Query\MoreLikeFeature
 * @covers \CirrusSearch\Query\MoreLikeThisFeature
 * @covers \CirrusSearch\Query\MoreLikeTrait
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 * @group CirrusSearch
 */
class MoreLikeFeatureTest extends CirrusIntegrationTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function applyProvider() {
		return [
			'morelike: doesnt eat unrelated queries' => [
				'other stuff',
				new \Elastica\Query\MatchAll(),
				false,
				MoreLikeFeature::class,
			],
			'morelike: is a queryHeader but ideally should not' => [
				'other stuff morelike:Test',
				new \Elastica\Query\MatchAll(),
				false,
				MoreLikeFeature::class,
			],
			'morelire: no query given for unknown page' => [
				'morelike:Does not exist or at least I hope not',
				null,
				true,
				MoreLikeFeature::class,
			],
			'morelike: single page' => [
				'morelike:Some page',
				( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '12345' ],
					] ),
				true,
				MoreLikeFeature::class,
			],
			'morelike: multi page' => [
				'morelike:Some page|Other page',
				( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '23456' ],
						[ '_id' => '12345' ],
					] ),
				true,
				MoreLikeFeature::class
			],
			'morelike: multi page with only one valid' => [
				'morelike:Some page|Does not exist or at least I hope not',
				( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '12345' ],
					] ),
				true,
				MoreLikeFeature::class
			],
			'morelikethis: doesnt eat unrelated queries' => [
				'other stuff',
				new \Elastica\Query\MatchAll(),
				false,
				MoreLikeThisFeature::class,
			],
			'morelikethis: can be combined' => [
				'other stuff morelikethis:"Some page" and other stuff',
				$this->wrapInMust( ( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '12345' ],
					] ) ),
				true,
				MoreLikeThisFeature::class,
				'other stuff and other stuff'
			],
			'morelikethis: no query given for unknown page' => [
				'morelikethis:"Does not exist or at least I hope not"',
				null,
				true,
				MoreLikeThisFeature::class,
			],
			'morelikethis: single page' => [
				'morelikethis:"Some page"',
				$this->wrapInMust( ( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '12345' ],
					] ) ),
				true,
				MoreLikeThisFeature::class,
			],
			'morelikethis: multi page' => [
				'morelikethis:"Some page|Other page"',
				$this->wrapInMust( ( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '23456' ],
						[ '_id' => '12345' ],
					] ) ),
				true,
				MoreLikeThisFeature::class,
			],
			'morelikethis: multi page with only one valid' => [
				'morelikethis:"Some page|Does not exist or at least I hope not"',
				$this->wrapInMust( ( new \Elastica\Query\MoreLikeThis() )
					->setParams( [
						'min_doc_freq' => 2,
						'max_doc_freq' => null,
						'max_query_terms' => 25,
						'min_term_freq' => 2,
						'min_word_length' => 0,
						'max_word_length' => 0,
						'minimum_should_match' => '30%',
					] )
					->setFields( [ 'text' ] )
					->setLike( [
						[ '_id' => '12345' ],
					] ) ),
				true,
				MoreLikeThisFeature::class,
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( $term, $expectedQuery, $mltUsed, $featureClass, $remainingText = '' ) {
		// Inject fake pages for MoreLikeTrait::collectTitles() to find
		$linkCache = MediaWikiServices::getInstance()->getLinkCache();
		$linkCache->addGoodLinkObj( 12345, Title::newFromText( 'Some page' ) );
		$linkCache->addGoodLinkObj( 23456, Title::newFromText( 'Other page' ) );

		// @todo Use a HashConfig with explicit values?
		$config = new HashSearchConfig( [ 'CirrusSearchMoreLikeThisTTL' => 600 ], [ HashSearchConfig::FLAG_INHERIT ] );

		$context = new SearchContext( $config );

		// Finally run the test
		$feature = new $featureClass( $config );

		if ( $mltUsed ) {
			$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::hostWikiOnlyStrategy() );
		}

		$result = $feature->apply( $context, $term );

		$this->assertSame( $mltUsed, $context->isSyntaxUsed( 'more_like' ) );
		if ( $mltUsed ) {
			$this->assertGreaterThan( 0, $context->getCacheTtl() );
		} else {
			$this->assertSame( 0, $context->getCacheTtl() );
		}
		if ( $expectedQuery === null ) {
			$this->assertFalse( $context->areResultsPossible() );
		} else {
			$this->assertEquals( $expectedQuery, $context->getQuery() );
			if ( $expectedQuery instanceof \Elastica\Query\MatchAll ) {
				$this->assertSame( $term, $result, 'Term must be unchanged' );
			} else {
				$this->assertSame( $remainingText, $result, 'Term must be empty string' );
			}
		}
	}

	public function testExpandedData() {
		$config = new SearchConfig();
		$title = Title::newFromText( 'Some page' );
		MediaWikiServices::getInstance()->getLinkCache()
			->addGoodLinkObj( 12345, $title );
		$feature = new MoreLikeFeature( $config );

		$this->assertExpandedData(
			$feature,
			'morelike:Some page',
			[ $title ],
			[],
			$config
		);

		$this->assertExpandedData(
			$feature,
			'morelike:Some page|Title that doesnt exist',
			[ $title ],
			[],
			$config
		);

		$this->assertExpandedData(
			$feature,
			'morelike:Title that doesnt exist',
			[],
			[ [ 'cirrussearch-mlt-feature-no-valid-titles', 'morelike' ] ],
			$config
		);
	}

	private function wrapInMust( AbstractQuery $query ): AbstractQuery {
		$boolQuery = new BoolQuery();
		$boolQuery->addMust( $query );
		return $boolQuery;
	}
}
