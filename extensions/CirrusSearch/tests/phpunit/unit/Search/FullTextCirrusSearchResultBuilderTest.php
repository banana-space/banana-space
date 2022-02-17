<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use CirrusSearch\Search\Fetch\HighlightedField;
use CirrusSearch\Searcher;
use Title;

/**
 * @covers \CirrusSearch\Search\FullTextCirrusSearchResultBuilder
 * @covers \CirrusSearch\Search\Fetch\HighlightingTrait
 * @covers \CirrusSearch\Search\TitleHelper
 * @covers \CirrusSearch\Search\Result
 */
class FullTextCirrusSearchResultBuilderTest extends CirrusTestCase {
	private static $MINIMAL_HIT = [
		'_index' => 'some_index',
		'_source' => [
			'namespace' => NS_TEMPLATE,
			'namespace_text' => 'Template',
			'title' => 'Main Page',
			'wiki' => 'mywiki',
		],
	];
	/**
	 * @var FullTextCirrusSearchResultBuilder
	 */
	private $fulltextResultBuilder;
	/**
	 * @var TitleHelper
	 */
	private $titleHelper;

	public function setUp() : void {
		parent::setUp();
		$config = $this->newHashSearchConfig( [
			'CirrusSearchWikiToNameMap' => [
				'cs' => 'cswiki',
			],
			// We test regex behavior
			'CirrusSearchUseExperimentalHighlighter' => true,
			'_wikiID' => 'mywiki'
		] );
		$resolver = $this->newManualInterwikiResolver( $config );
		$this->titleHelper = $this->newTitleHelper( $config, $resolver );
		$fetchPhaseConfigBuilder = new FetchPhaseConfigBuilder( $config, SearchQuery::SEARCH_TEXT );
		$fetchPhaseConfigBuilder->addNewRegexHLField( "title.plain", HighlightedField::TARGET_TITLE_SNIPPET,
			'/unused/', true, HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY );
		$fetchPhaseConfigBuilder->addNewRegexHLField( "redirect.title.plain", HighlightedField::TARGET_REDIRECT_SNIPPET,
			'/unused/', true, HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY );
		$fetchPhaseConfigBuilder->addNewRegexHLField( "source_text.plain", HighlightedField::TARGET_MAIN_SNIPPET,
			'/unused/', true, HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY );
		$fetchPhaseConfigBuilder->configureDefaultFullTextFields();
		$this->fulltextResultBuilder = new FullTextCirrusSearchResultBuilder( $this->titleHelper,
			$fetchPhaseConfigBuilder->getHLFieldsPerTargetAndPriority() );
	}

	public function provideTest() {
		$cases = [
			'word_count' => [
				array_merge_recursive( [ 'fields' => [ 'text.word_count' => [ 432 ] ] ], self::$MINIMAL_HIT ),
				[ 'wordCount' => 432 ]
			],
			'byte_size' => [
				array_merge_recursive( [ '_source' => [ 'text_bytes' => 298000 ] ], self::$MINIMAL_HIT ),
				[ 'byteSize' => 298000 ]
			],
			'timestamp' => [
				array_merge_recursive( [ '_source' => [ 'timestamp' => '2019-08-25T14:28:11Z' ] ], self::$MINIMAL_HIT ),
				[ 'timestamp' => '20190825142811' ]
			],
			'score' => [
				array_merge_recursive( [ '_score' => 3.424 ], self::$MINIMAL_HIT ),
				[ 'score' => 3.424 ]
			],
			'explanation' => [
				array_merge_recursive( [ '_explanation' => [ 'some concise explanation' ] ], self::$MINIMAL_HIT ),
				[ 'explanation'  => [ 'some concise explanation' ] ]
			],
			'interwikiNamespaceText' => [
				array_replace_recursive( self::$MINIMAL_HIT, [ '_source' => [ 'namespace_text' => 'Šablona', 'wiki' => 'cswiki' ] ] ),
				[ 'interwikiNamespaceText' => 'Šablona' ]
			],
			'titleSnippet' => [
				array_merge_recursive( [ 'highlight' => [
						'title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'title' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					] ], self::$MINIMAL_HIT ),
				[ 'titleSnippet' => 'Template:' . Searcher::HIGHLIGHT_PRE . 'title' . Searcher::HIGHLIGHT_POST . ' &lt;match' ]
			],
			'titleSnippet (intitle:// prefers title.plain)' => [
				array_merge_recursive( [ 'highlight' => [
					'title' => [
						Searcher::HIGHLIGHT_PRE_MARKER . 'title' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
					],
					'title.plain' => [
						Searcher::HIGHLIGHT_PRE_MARKER . 'regex match' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
					],
				] ], self::$MINIMAL_HIT ),
				[ 'titleSnippet' => 'Template:' . Searcher::HIGHLIGHT_PRE . 'regex match' . Searcher::HIGHLIGHT_POST . ' &lt;match' ],
			],
			'redirectSnippet' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'redirect.title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'redirect' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
					'_source' => [
						'redirect' => [
							[ 'title' => 'redirect <match', 'namespace' => NS_TEMPLATE ],
							[ 'title' => 'redirect <match', 'namespace' => 0 ],
							[ 'title' => 'redirect', 'namespace' => 0 ],
						]
					]
				] ),
				[ 'redirectSnippet' => Searcher::HIGHLIGHT_PRE . 'redirect' . Searcher::HIGHLIGHT_POST . ' &lt;match' ]
			],
			'redirectSnippet (intitle:// prefers redirect.title.plain)' => [
				array_merge_recursive( [
					'highlight' => [
						'redirect.title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'redirect' .
							Searcher::HIGHLIGHT_POST_MARKER . ' <match',
						],
						'redirect.title.plain' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'regex redir' .
							Searcher::HIGHLIGHT_POST_MARKER . ' <match',
						],
					],
					'_source' => [
						'redirect' => [
							[ 'title' => 'redirect <match', 'namespace' => 2 ],
							[ 'title' => 'redirect <match', 'namespace' => 0 ],
							[ 'title' => 'redirect', 'namespace' => 0 ],
							[ 'title' => 'regex redir <match', 'namespace' => 2 ],
							[ 'title' => 'regex redir <match', 'namespace' => 0 ],
						],
					],
				], self::$MINIMAL_HIT ),
				[ 'redirectSnippet' => Searcher::HIGHLIGHT_PRE . 'regex redir' . Searcher::HIGHLIGHT_POST . ' &lt;match' ]
			],
			'redirectSnippet no redirect when no redirect.title is found' => [
				array_merge_recursive( [
					'highlight' => [
						'redirect.title' => [
							searcher::HIGHLIGHT_PRE_MARKER . 'redirect' . searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
					'_source' => [
						'redirect' => [
							[ 'title' => 'redirect', 'namespace' => 0 ],
						]
					]
				], self::$MINIMAL_HIT ),
				[ 'redirectSnippet' => null ]
			],
			'redirectTitle' => [
				array_merge_recursive( [
					'highlight' => [
						'redirect.title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'redirect' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
					'_source' => [
						'redirect' => [
							[ 'title' => 'redirect <match', 'namespace' => 2 ],
							[ 'title' => 'redirect <match', 'namespace' => 0 ],
							[ 'title' => 'redirect', 'namespace' => 0 ],
						]
					]
				], self::$MINIMAL_HIT ),
				[ 'redirectTitle' => \Title::makeTitle( NS_MAIN, 'redirect <match' ) ]
			],
			'redirectTitle (interwiki support when namespace matches)' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'redirect.title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'redirect' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
					'_source' => [
						'namespace' => NS_TEMPLATE,
						'namespace_text' => 'Šablona',
						'wiki' => 'cswiki',
						'redirect' => [
							[ 'title' => 'redirect <match', 'namespace' => NS_TEMPLATE ],
							[ 'title' => 'redirect', 'namespace' => 0 ],
						]
					]
				] ),
				[ 'redirectTitle' => \Title::makeTitle( NS_MAIN, 'Šablona:redirect <match', '', 'cs' ) ]
			],
			'redirectTitle (interwiki support when namespace does not match)' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'redirect.title' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'redirect' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
					'_source' => [
						'namespace' => NS_TEMPLATE,
						'namespace_text' => 'Šablona',
						'wiki' => 'cswiki',
						'redirect' => [
							[ 'title' => 'redirect <match', 'namespace' => NS_CATEGORY ],
							[ 'title' => 'redirect', 'namespace' => 0 ],
						]
					]
				] ),
				[ 'redirectTitle' => null ]
			],
			'source_text matches preferred' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'source_text.plain' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'source_text.plain matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'auxiliary_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'auxiliary_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'file_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'file_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
				] ),
				[
					'textSnippet' => Searcher::HIGHLIGHT_PRE . 'source_text.plain matches' . Searcher::HIGHLIGHT_POST . ' &lt;match',
					'fileMatch' => false
				]
			],
			'text matches preferred after source_text' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'source_text.plain' => [
							'source_text.plain no matches <match'
						],
						'text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'auxiliary_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'auxiliary_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'file_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'file_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
				] ),
				[
					'textSnippet' => Searcher::HIGHLIGHT_PRE . 'text matches' . Searcher::HIGHLIGHT_POST . ' &lt;match',
					'fileMatch' => false
				]
			],
			'auxiliary_text matches preferred after text' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'source_text.plain' => [
							'source_text.plain no matches <match'
						],
						'text' => [
							'text no matches <match'
						],
						'auxiliary_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'auxiliary_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						],
						'file_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'file_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
				] ),
				[
					'textSnippet' => Searcher::HIGHLIGHT_PRE . 'auxiliary_text matches' . Searcher::HIGHLIGHT_POST . ' &lt;match',
					'fileMatch' => false
				]
			],
			'file_text matches preferred after auxiliary_text' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'source_text.plain' => [
							'source_text.plain no matches <match'
						],
						'text' => [
							'text no matches <match'
						],
						'auxiliary_text' => [
							'auxiliary_text no matches <match'
						],
						'file_text' => [
							Searcher::HIGHLIGHT_PRE_MARKER . 'file_text matches' . Searcher::HIGHLIGHT_POST_MARKER . ' <match'
						]
					],
				] ),
				[
					'textSnippet' => Searcher::HIGHLIGHT_PRE . 'file_text matches' . Searcher::HIGHLIGHT_POST . ' &lt;match',
					'fileMatch' => true
				]
			],
			'text preferred when nothing matches' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'source_text.plain' => [
							'source_text.plain no matches <match'
						],
						'text' => [
							'text no matches <match'
						],
						'auxiliary_text' => [
							'auxiliary_text no matches <match'
						],
						'file_text' => [
							'file_text no matches <match'
						]
					],
				] ),
				[
					'textSnippet' => 'text no matches &lt;match',
					'fileMatch' => false
				]
			],
			'heading snippet and title' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'heading' => [
							'The ' .
							Searcher::HIGHLIGHT_PRE_MARKER . 'matched' . Searcher::HIGHLIGHT_POST_MARKER .
							' <section'
						],
					],
				] ),
				[
					'sectionSnippet' => 'The ' . Searcher::HIGHLIGHT_PRE . 'matched' . Searcher::HIGHLIGHT_POST . ' &lt;section',
					'sectionTitle' => Title::makeTitle( NS_TEMPLATE, 'Main Page' )
						->createFragmentTarget( $this->sanitizeLinkFragment( 'The matched <section' ) )
				]
			],
			'categorySnippet' => [
				array_replace_recursive( self::$MINIMAL_HIT, [
					'highlight' => [
						'category' => [
							'The ' .
							Searcher::HIGHLIGHT_PRE_MARKER . 'matched' . Searcher::HIGHLIGHT_POST_MARKER .
							' <category'
						],
					],
				] ),
				[
					'categorySnippet' => 'The ' . Searcher::HIGHLIGHT_PRE . 'matched' . Searcher::HIGHLIGHT_POST . ' &lt;category',
				]
			],
		];
		return array_map( function ( array $v ) {
			$v[0] = new \Elastica\Result( $v[0] );
			return $v;
		}, $cases );
	}

	/**
	 * @dataProvider provideTest
	 * @param \Elastica\Result $hit
	 * @param array $expectedFieldValues
	 */
	public function test( \Elastica\Result $hit, array $expectedFieldValues ) {
		$result = $this->fulltextResultBuilder->build( $hit );
		foreach ( $expectedFieldValues as $field => $value ) {
			$getter = $this->getter( $field, gettype( $value ) );
			$this->assertEquals( $value, $getter( $result ),
				"value for for field $field should match with a hit: " . print_r( $hit, true ) );
		}
	}

	private function getter( $field, $type ): callable {
		return function ( CirrusSearchResult $result ) use ( $field, $type ) {
			return call_user_func( [ $result, ( $type === 'boolean' ? 'is' : 'get' ) . ucfirst( $field ) ] );
		};
	}
}
