<?php

namespace CirrusSearch;

use CirrusSearch\BuildDocument\Completion\DefaultSortSuggestionsBuilder;
use CirrusSearch\BuildDocument\Completion\NaiveSubphrasesSuggestionsBuilder;
use CirrusSearch\BuildDocument\Completion\SuggestBuilder;
use CirrusSearch\BuildDocument\Completion\SuggestScoringMethodFactory;

/**
 * test suggest builder.
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
 * @group CirrusSearch
 * @covers \CirrusSearch\BuildDocument\Completion\SuggestBuilder
 */
class SuggestBuilderTest extends CirrusTestCase {
	public function testEinstein() {
		$builder = $this->buildBuilder();
		$score = 10;
		$redirScore = (int)( $score * SuggestBuilder::REDIRECT_DISCOUNT );
		$doc = [
			'id' => 123,
			'title' => 'Albert Einstein',
			'namespace' => 0,
			'redirect' => [
				[ 'title' => "Albert Enstein", 'namespace' => 0 ],
				[ 'title' => "Albert Einsten", 'namespace' => 0 ],
				[ 'title' => 'Albert Einstine', 'namespace' => 0 ],
				[ 'title' => "Enstein", 'namespace' => 0 ],
				[ 'title' => "Einstein", 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];
		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'Albert Einstein', 'Albert Enstein',
						'Albert Einsten', 'Albert Einstine' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'Albert Einstein', 'Albert Enstein',
						'Albert Einsten', 'Albert Einstine' ],
					'weight' => $score
				]
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'Enstein', 'Einstein' ],
					'weight' => $redirScore
				],
				'suggest-stop' => [
					'input' => [ 'Enstein', 'Einstein' ],
					'weight' => $redirScore
				]
			]
		];

		$suggestions = $this->buildSuggestions( $builder, $doc );
		$this->assertSame( $expected, $suggestions );
	}

	public function testExplain() {
		$builder = $this->buildBuilder( 'incomingLinks' );
		$score = 10;
		$redirScore = (int)( $score * SuggestBuilder::REDIRECT_DISCOUNT );
		$doc = [
			'id' => 123,
			'title' => 'Albert Einstein',
			'namespace' => 0,
			'redirect' => [
				[ 'title' => "Albert Enstein", 'namespace' => 0 ],
				[ 'title' => "Albert Einsten", 'namespace' => 0 ],
				[ 'title' => 'Albert Einstine', 'namespace' => 0 ],
				[ 'title' => "Enstein", 'namespace' => 0 ],
				[ 'title' => "Einstein", 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];
		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [
						0 => 'Albert Einstein',
						1 => 'Albert Enstein',
						2 => 'Albert Einsten',
						3 => 'Albert Einstine',
					],
					'weight' => $score,
				],
				'suggest-stop' => [
					'input' => [
						0 => 'Albert Einstein',
						1 => 'Albert Enstein',
						2 => 'Albert Einsten',
						3 => 'Albert Einstine',
					],
					'weight' => $score,
				],
				'score_explanation' => [
					'value' => $score,
					'description' => 'Number of incoming links',
				],
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [
						0 => 'Enstein',
						1 => 'Einstein',
					],
					'weight' => $redirScore,
				],
				'suggest-stop' => [
					'input' => [
						0 => 'Enstein',
						1 => 'Einstein',
					],
					'weight' => $redirScore,
				],
				'score_explanation' => [
					'value' => $score, // TODO: add explanation of weighting done in SuggestBuilder
					'description' => 'Number of incoming links',
				],
			],
		];
		$suggestions = $this->buildSuggestions( $builder, $doc, true );
		$this->assertSame( $expected, $suggestions );
	}

	public function testDefaultSort() {
		$builder = $this->buildBuilder();
		$this->assertContains( 'defaultsort', $builder->getRequiredFields() );
		$score = 10;
		$redirScore = (int)( $score * SuggestBuilder::REDIRECT_DISCOUNT );
		$doc = [
			'id' => 123,
			'title' => 'Albert Einstein',
			'namespace' => 0,
			'defaultsort' => 'Einstein, Albert',
			'redirect' => [
				[ 'title' => "Albert Enstein", 'namespace' => 0 ],
				[ 'title' => "Einstein", 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];
		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'Albert Einstein', 'Albert Enstein', 'Einstein, Albert' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'Albert Einstein', 'Albert Enstein', 'Einstein, Albert' ],
					'weight' => $score
				]
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Albert Einstein',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'Einstein' ],
					'weight' => $redirScore
				],
				'suggest-stop' => [
					'input' => [ 'Einstein' ],
					'weight' => $redirScore
				]
			]
		];

		$suggestions = $this->buildSuggestions( $builder, $doc );
		$this->assertSame( $expected, $suggestions );
	}

	public function testEraq() {
		$builder = $this->buildBuilder();
		$score = 10;
		$redirScore = (int)( $score * SuggestBuilder::REDIRECT_DISCOUNT );
		$doc = [
			'id' => 123,
			'title' => 'Iraq',
			'namespace' => 0,
			'redirect' => [
				[ 'title' => "Eraq", 'namespace' => 0 ],
				[ 'title' => "Irak", 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];

		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Iraq',
					'namespace' => 0
				],
				'suggest' => [
					'input' => [ 'Iraq', 'Irak' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'Iraq', 'Irak' ],
					'weight' => $score
				]
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Iraq',
					'namespace' => 0
				],
				'suggest' => [
					'input' => [ 'Eraq' ],
					'weight' => $redirScore
				],
				'suggest-stop' => [
					'input' => [ 'Eraq' ],
					'weight' => $redirScore
				]
			]
		];
		$suggestions = $this->buildSuggestions( $builder, $doc );
		$this->assertSame( $expected, $suggestions );
	}

	public function testUlm() {
		$builder = $this->buildBuilder();
		$score = 10;
		$redirScore = (int)( $score * SuggestBuilder::REDIRECT_DISCOUNT );
		$doc = [
			'id' => 123,
			'title' => 'Ulm',
			'namespace' => 0,
			'redirect' => [
				[ 'title' => 'UN/LOCODE:DEULM', 'namespace' => 0 ],
				[ 'title' => 'Ulm, Germany', 'namespace' => 0 ],
				[ 'title' => "Ulm displaced persons camp", 'namespace' => 0 ],
				[ 'title' => "Söflingen", 'namespace' => 0 ],
				[ 'title' => "Should be ignored", 'namespace' => 1 ],
			],
			'incoming_links' => $score
		];

		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Ulm',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'Ulm' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'Ulm' ],
					'weight' => $score
				],
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Ulm',
					'namespace' => 0,
				],
				'suggest' => [
					'input' => [ 'UN/LOCODE:DEULM', 'Ulm, Germany',
						'Ulm displaced persons camp', 'Söflingen' ],
					'weight' => $redirScore
				],
				'suggest-stop' => [
					'input' => [ 'UN/LOCODE:DEULM', 'Ulm, Germany',
						'Ulm displaced persons camp', 'Söflingen' ],
					'weight' => $redirScore
				],
			]
		];
		$suggestions = $this->buildSuggestions( $builder, $doc );
		$this->assertSame( $expected, $suggestions );
	}

	private function buildSuggestions( $builder, $doc, $explain = false ) {
		$id = $doc['id'];
		unset( $doc['id'] );
		return array_map(
			function ( $x ) {
				$dat = $x->getData();
				unset( $dat['batch_id'] );
				return $dat;
			},
			$builder->build( [ [ 'id' => $id, 'source' => $doc ] ], $explain )
		);
	}

	/**
	 * @dataProvider providePagesForSubphrases
	 */
	public function testSubphrasesSuggestionsBuilder( $input, $langSubPage, $type, $max, array $output ) {
		$config = [ 'limit' => $max, 'type' => $type ];
		$builder = NaiveSubphrasesSuggestionsBuilder::create( $config );
		$subPageSuggestions = $builder->tokenize( $input, $langSubPage );
		$this->assertEquals( $output, $subPageSuggestions );
	}

	public function providePagesForSubphrases() {
		return [
			'none subpage' => [
				'Hello World',
				'',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[]
			],
			'none any words' => [
				'Hello World',
				'',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[ 'World' ]
			],
			'none subpage translated' => [
				'Hello World/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[],
			],
			'none any words translated' => [
				'Hello World/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[ 'World/ru' ],
			],
			'simple subphrase' => [
				'Hyperion Cantos/Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[ 'Hyperion' ],
			],
			'simple any words' => [
				'Hyperion Cantos/Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[ 'Cantos/Hyperion', 'Hyperion' ],
			],
			'simple subpage translated' => [
				'Hyperion Cantos/Hyperion/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[ 'Hyperion/ru' ],
			],
			'simple any words translated' => [
				'Hyperion Cantos/Hyperion/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[ 'Cantos/Hyperion/ru', 'Hyperion/ru' ],
			],
			'multiple subpage' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[
					'Hyperion/The Priest\'s Tale',
					'The Priest\'s Tale'
				],
			],
			'multiple any words' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				10,
				[
					'Cantos/Hyperion/The Priest\'s Tale',
					'Hyperion/The Priest\'s Tale',
					'The Priest\'s Tale',
					'Priest\'s Tale',
					'Tale'
				],
			],
			'multiple subpage translated' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[
					'Hyperion/The Priest\'s Tale/ru',
					'The Priest\'s Tale/ru'
				],
			],
			'multiple any words translated' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				10,
				[
					'Cantos/Hyperion/The Priest\'s Tale/ru',
					'Hyperion/The Priest\'s Tale/ru',
					'The Priest\'s Tale/ru',
					'Priest\'s Tale/ru',
					'Tale/ru',
				],
			],
			'multiple subpage limited' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/Part One',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				2,
				[
					'Hyperion/The Priest\'s Tale/Part One',
					'The Priest\'s Tale/Part One'
				],
			],
			'multiple any words limited' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/Part One',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				2,
				[
					'Cantos/Hyperion/The Priest\'s Tale/Part One',
					'Hyperion/The Priest\'s Tale/Part One',
				],
			],
			'multiple translated subpage limited' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/Part One/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				2,
				[
					'Hyperion/The Priest\'s Tale/Part One/ru',
					'The Priest\'s Tale/Part One/ru'
				],
			],
			'multiple translated any words limited' => [
				'Hyperion Cantos/Hyperion/The Priest\'s Tale/Part One/ru',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				2,
				[
					'Cantos/Hyperion/The Priest\'s Tale/Part One/ru',
					'Hyperion/The Priest\'s Tale/Part One/ru',
				],
			],
			'empty subpage' => [
				'Hyperion Cantos//Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[ 'Hyperion' ],
			],
			'empty subpage anywords' => [
				'Hyperion Cantos//Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[ 'Cantos//Hyperion', 'Hyperion' ],
			],
			'misplace lang subpage' => [
				'Hyperion Cantos/ru/Hyperion',
				'ru',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[ 'ru/Hyperion', 'Hyperion' ],
			],
			'missing subpage' => [
				'Hyperion Cantos/',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[]
			],
			'orphan subpage' => [
				'/Hyperion Cantos/Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[ 'Hyperion' ]
			],
			'starts with space' => [
				' Hyperion',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[]
			],
			'edge case with empty title' => [
				'',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[]
			],
			'edge case with only split chars' => [
				'//',
				'en',
				NaiveSubphrasesSuggestionsBuilder::SUBPAGE_TYPE,
				3,
				[]
			],
			'edge case with only split chars #2' => [
				' / / /en',
				'en',
				NaiveSubphrasesSuggestionsBuilder::STARTS_WITH_ANY_WORDS_TYPE,
				3,
				[]
			]
		];
	}

	private function buildBuilder() {
		$extra = [
			new DefaultSortSuggestionsBuilder(),
		];
		return new SuggestBuilder( SuggestScoringMethodFactory::getScoringMethod( 'incomingLinks' ), $extra );
	}
}
