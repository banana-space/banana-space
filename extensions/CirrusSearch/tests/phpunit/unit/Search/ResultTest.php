<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use CirrusSearch\Searcher;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Search\Result
 * @covers \CirrusSearch\Search\Fetch\HighlightingTrait
 * @covers \CirrusSearch\Search\FullTextCirrusSearchResultBuilder
 * @covers \CirrusSearch\Search\CirrusSearchResult
 */
class ResultTest extends CirrusTestCase {
	private static $EXAMPLE_HIT = [
		'_index' => 'eswiki_content_123456',
		'_source' => [
			'namespace' => NS_MAIN,
			'namespace_text' => '',
			'title' => 'Main Page',
			'wiki' => 'eswiki',
			'redirect' => [
				[
					'title' => 'Main',
					'namespace' => NS_MAIN,
				],
			],
		],
		'highlight' => [
			'redirect.title' => [ 'Main' ],
			'heading' => [ '...' ],
		],
	];
	/**
	 * @var FullTextCirrusSearchResultBuilder
	 */
	private $builder;
	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	protected function setUp() : void {
		parent::setUp();

		$config = $this->newHashSearchConfig( [
			'CirrusSearchWikiToNameMap' => [ 'es' => 'eswiki', ]
		] );
		$fetchPhaseBuilder = new FetchPhaseConfigBuilder( $config, SearchQuery::SEARCH_TEXT );
		$fetchPhaseBuilder->configureDefaultFullTextFields();
		$this->titleHelper = $this->newTitleHelper( $config,
			$this->newManualInterwikiResolver( $config ) );
		$this->builder = new FullTextCirrusSearchResultBuilder( $this->titleHelper, $fetchPhaseBuilder->getHLFieldsPerTargetAndPriority() );
	}

	public function highlightedSectionSnippetProvider() {
		$originalTestCases = [
					'stuff' => [ [ '', 'stuff', '' ], [], '' ],
					// non-ASCII encoding of "fragment" is ugly, so test on easier
					// German case
					'german' => [ [ '', 'tschüß', '' ], [], '' ],
					// English combining umlaut should move from post to highlight
					'english' => [ [ 'Sp', 'ın', '̈al' ], [ 'Sp', 'ın̈', 'al' ], '' ],
					// Hindi combining vowel mark should move from post to highlight
					'hindi' => [ [ '', 'म', 'ेला' ], [ '', 'मे', 'ला' ], '' ],
					// Javanese final full character in pre should move to highlight
					// to join consonant mark; vowel mark in post should move to highlight
					'javanese' => [ [ 'ꦎꦂꦠꦺꦴꦒ', 'ꦿꦥ꦳', 'ꦶ' ], [ 'ꦎꦂꦠꦺꦴ', 'ꦒꦿꦥ꦳ꦶ', '' ], '' ],
					// Myanmar final full character in pre and two post combining marks
					// should move to highlight
					'myanmar' => [ [ 'ခင်ဦးမ', 'ြိ', 'ု့နယ်' ], [ 'ခင်ဦး', 'မြို့', 'နယ်' ], '' ],
					// Full character and combining mark should move from pre to highlight
					// to join combining mark; post combining marks should move to highlight
					'wtf' => [ [ 'Q̃̓', '̧̑', '̫̯' ], [ '', 'Q̧̫̯̃̓̑', '' ], '' ],
				];
		$testCases = [];
		foreach ( $originalTestCases as $name => $case ) {
			$testCases["$name (Using Result constructor)"] = $case;
			$testCases["$name (Using Result FullTextCirrusSearchResultBuilder)"] = array_merge( $case, [ true ] );
		}
		return $testCases;
	}

	/**
	 * @dataProvider highlightedSectionSnippetProvider
	 */
	public function testHighlightedSectionSnippet( array $input, array $output, $plain, $useFTResultBuilder = false ) {
		// If no output segementation is specified, it should break up the same as the input.
		if ( empty( $output ) ) {
			$output = $input;
		}
		// If no plain version is specified, join the input together.
		if ( $plain === '' ) {
			$plain = implode( '', $input );
		}

		// Input has PRE/POST_MARKER character; output has PRE/POST HTML.
		$elasticInput = $input[0] . Searcher::HIGHLIGHT_PRE_MARKER . $input[1] . Searcher::HIGHLIGHT_POST_MARKER . $input[2];
		$htmlOutput = $output[0] . Searcher::HIGHLIGHT_PRE . $output[1] . Searcher::HIGHLIGHT_POST . $output[2];

		$data = self::$EXAMPLE_HIT;
		$data['highlight']['heading'] = [ $elasticInput ];

		if ( $useFTResultBuilder ) {
			$result = $this->builder->build( new \Elastica\Result( $data ) );
		} else {
			$result = $this->buildResult( $data );
		}
		$this->assertEquals( $htmlOutput, $result->getSectionSnippet() );
		$this->assertEquals( $this->titleHelper->sanitizeSectionFragment( $plain ),
			$result->getSectionTitle()->getFragment() );
	}

	public function testInterwikiResults() {
		$data = self::$EXAMPLE_HIT;
		$result = $this->buildResult( $data );

		$this->assertTrue( $result->getTitle()->isExternal(), 'isExternal' );
		$this->assertTrue( $result->getRedirectTitle()->isExternal(), 'redirect isExternal' );
		$this->assertTrue( $result->getSectionTitle()->isExternal(), 'section title isExternal' );

		// Test that we can't build the redirect title if the namespaces
		// do not match
		$data['_source']['namespace'] = NS_HELP;
		$data['_source']['namespace_text'] = 'Help';

		foreach ( [ $this->buildResult( $data ), $this->buildResult( $data ) ] as $result ) {
			$msgSuffix = "using " . get_class( $result );
			$this->assertTrue( $result->getTitle()->isExternal(), "isExternal namespace mismatch $msgSuffix" );
			$this->assertEquals( $result->getTitle()->getPrefixedText(), "es:Help:Main Page",
			"prefix text must match $msgSuffix" );
			$this->assertTrue( $result->getRedirectTitle() === null,
				"redirect is not built with ns mismatch $msgSuffix" );
			$this->assertTrue( $result->getSectionTitle()->isExternal(), "section title isExternal $msgSuffix" );
		}
	}

	private function buildResult( $hit ) {
		return new Result(
			null,
			new \Elastica\Result( $hit ),
			$this->titleHelper
		);
	}
}
