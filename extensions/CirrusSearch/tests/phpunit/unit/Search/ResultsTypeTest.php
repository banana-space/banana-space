<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use CirrusSearch\Searcher;
use Elastica\Query;
use Elastica\Response;

/**
 * Test escaping search strings.
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
 * @covers \CirrusSearch\Search\FullTextResultsType
 * @covers \CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder
 * @covers \CirrusSearch\Search\Fetch\HighlightedField
 * @covers \CirrusSearch\Search\Fetch\BaseHighlightedField
 * @covers \CirrusSearch\Search\Fetch\ExperimentalHighlightedFieldBuilder
 * @group CirrusSearch
 */
class ResultsTypeTest extends CirrusTestCase {
	public function fancyRedirectHandlingProvider() {
		return [
			'typical title only match' => [
				NS_MAIN,
				'Trebuchet',
				[
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
					],
				],
			],
			'partial title match' => [
				NS_MAIN,
				'Trebuchet',
				[
					'highlight' => [
						'title.prefix' => [
							Searcher::HIGHLIGHT_PRE . 'Trebu' . Searcher::HIGHLIGHT_POST . 'chet',
						],
					],
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
					],
				],
			],
			'full redirect match same namespace' => [
				NS_MAIN,
				'Pierriere',
				[
					'highlight' => [
						'redirect.title.prefix' => [
							Searcher::HIGHLIGHT_PRE . 'Pierriere' . Searcher::HIGHLIGHT_POST,
						],
					],
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
						'redirect' => [
							[ 'namespace' => 0, 'title' => 'Pierriere' ]
						],
					],
				],
			],
			'full redirect match other namespace' => [
				NS_CATEGORY,
				'Pierriere',
				[
					'highlight' => [
						'redirect.title.prefix' => [
							Searcher::HIGHLIGHT_PRE . 'Pierriere' . Searcher::HIGHLIGHT_POST,
						],
					],
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
						'redirect' => [
							[ 'namespace' => 14, 'title' => 'Pierriere' ]
						],
					],
				],
			],
			'partial redirect match other namespace' => [
				NS_CATEGORY,
				'Pierriere',
				[
					'highlight' => [
						'redirect.title.prefix' => [
							Searcher::HIGHLIGHT_PRE . 'Pi' . Searcher::HIGHLIGHT_POST . 'erriere',
						],
					],
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
						'redirect' => [
							[ 'namespace' => 14, 'title' => 'Pierriere' ]
						],
					],
				],
			],
			'multiple redirect namespace matches' => [
				NS_USER,
				'Pierriere',
				[
					'highlight' => [
						'redirect.title.prefix' => [
							Searcher::HIGHLIGHT_PRE . 'Pierriere' . Searcher::HIGHLIGHT_POST,
						],
					],
					'_source' => [
						'namespace_text' => '',
						'namespace' => 0,
						'title' => 'Trebuchet',
						'redirect' => [
							[ 'namespace' => 14, 'title' => 'Pierriere' ],
							[ 'namespace' => 2, 'title' => 'Pierriere' ],
						],
					],
				],
				[ 0, 2 ]
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Search\FancyTitleResultsType
	 * @dataProvider fancyRedirectHandlingProvider
	 */
	public function testFancyRedirectHandling( $expectedNs, $expected, $hit, array $namespaces = [] ) {
		$type = new FancyTitleResultsType( 'prefix', $this->newTitleHelper() );
		$result = new \Elastica\Result( $hit );
		$matches = $type->transformOneElasticResult( $result, $namespaces );
		$title = FancyTitleResultsType::chooseBestTitleOrRedirect( $matches );
		$this->assertEquals( \Title::makeTitle( $expectedNs, $expected ), $title );
	}

	/**
	 * @covers \CirrusSearch\Search\FullTextResultsType
	 */
	public function testFullTextSyntax() {
		$res = new \Elastica\ResultSet( new Response( [] ), new Query( [] ), [] );
		$fullTextRes = new FullTextResultsType( new FetchPhaseConfigBuilder( new HashSearchConfig( [] ) ), true, $this->newTitleHelper() );
		$this->assertTrue( $fullTextRes->transformElasticsearchResult( $res )->searchContainedSyntax() );

		$fullTextRes = new FullTextResultsType( new FetchPhaseConfigBuilder( new HashSearchConfig( [] ) ), false, $this->newTitleHelper() );
		$this->assertFalse( $fullTextRes->transformElasticsearchResult( $res )->searchContainedSyntax() );
		$fullTextRes = new FullTextResultsType( new FetchPhaseConfigBuilder( new HashSearchConfig( [] ) ), false, $this->newTitleHelper() );
		$this->assertFalse( $fullTextRes->transformElasticsearchResult( $res )->searchContainedSyntax() );
	}
}
