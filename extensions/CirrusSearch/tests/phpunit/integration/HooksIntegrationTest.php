<?php

namespace CirrusSearch;

use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Profile\SearchProfileServiceFactory;

/**
 * Make sure cirrus doens't break any hooks.
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
 * @covers \CirrusSearch\Hooks
 */
class HooksIntegrationTest extends CirrusIntegrationTestCase {
	public function testHooksAreArrays() {
		global $wgHooks;

		foreach ( $wgHooks as $name => $array ) {
			$this->assertThat( $array, $this->isType( 'array' ),
				"The $name hook should be an array." );
		}
	}

	public function provideOverrides() {
		return [
			'wgCirrusSearchPhraseRescoreWindowSize normal' => [
				'wgCirrusSearchPhraseRescoreWindowSize',
				1000,
				'cirrusPhraseWindow',
				"50",
				50,
			],
			'wgCirrusSearchPhraseRescoreWindowSize too high' => [
				'wgCirrusSearchPhraseRescoreWindowSize',
				1000,
				'cirrusPhraseWindow',
				"200000",
				1000,
			],
			'wgCirrusSearchPhraseRescoreWindowSize invalid' => [
				'wgCirrusSearchPhraseRescoreWindowSize',
				1000,
				'cirrusPhraseWindow',
				"blah",
				1000,
			],
			'wgCirrusSearchFunctionRescoreWindowSize normal' => [
				'wgCirrusSearchFunctionRescoreWindowSize',
				1000,
				'cirrusFunctionWindow',
				"50",
				50,
			],
			'wgCirrusSearchFunctionRescoreWindowSize too high' => [
				'wgCirrusSearchFunctionRescoreWindowSize',
				1000,
				'cirrusFunctionWindow',
				"20000",
				1000,
			],
			'wgCirrusSearchFunctionRescoreWindowSize invalid' => [
				'wgCirrusSearchFunctionRescoreWindowSize',
				1000,
				'cirrusFunctionWindow',
				"blah",
				1000,
			],
			'wgCirrusSearchFragmentSize normal' => [
				'wgCirrusSearchFragmentSize',
				10,
				'cirrusFragmentSize',
				100,
				100
			],
			'wgCirrusSearchFragmentSize too high' => [
				'wgCirrusSearchFragmentSize',
				10,
				'cirrusFragmentSize',
				100000,
				10
			],
			'wgCirrusSearchFragmentSize invalid' => [
				'wgCirrusSearchFragmentSize',
				10,
				'cirrusFragmentSize',
				'blah',
				10
			],
			'wgCirrusSearchAllFields normal' => [
				'wgCirrusSearchAllFields',
				[ 'use' => false ],
				'cirrusUseAllFields',
				'yes',
				[ 'use' => true ],
			],
			'wgCirrusSearchAllFields disable' => [
				'wgCirrusSearchAllFields',
				[ 'use' => true ],
				'cirrusUseAllFields',
				'no',
				[ 'use' => false ],
			],
			'wgCirrusSearchPhraseRescoreBoost' => [
				'wgCirrusSearchPhraseRescoreBoost',
				10,
				'cirrusPhraseBoost',
				'1',
				1
			],
			'wgCirrusSearchPhraseRescoreBoost invalid' => [
				'wgCirrusSearchPhraseRescoreBoost',
				10,
				'cirrusPhraseBoost',
				'blah',
				10,
			],
			'wgCirrusSearchPhraseSlop normal' => [
				'wgCirrusSearchPhraseSlop',
				[ 'boost' => 1 ],
				'cirrusPhraseSlop',
				'10',
				[ 'boost' => 10 ],
			],
			'wgCirrusSearchPhraseSlop too high' => [
				'wgCirrusSearchPhraseSlop',
				[ 'boost' => 1 ],
				'cirrusPhraseSlop',
				'11',
				[ 'boost' => 1 ],
			],
			'wgCirrusSearchPhraseSlop invalid' => [
				'wgCirrusSearchPhraseSlop',
				[ 'boost' => 1 ],
				'cirrusPhraseSlop',
				'blah',
				[ 'boost' => 1 ]
			],
			'wgCirrusSearchLogElasticRequests normal' => [
				'wgCirrusSearchLogElasticRequests',
				true,
				'cirrusLogElasticRequests',
				'secret',
				false,
				[ 'wgCirrusSearchLogElasticRequestsSecret' => 'secret' ]
			],
			'wgCirrusSearchLogElasticRequests bad secret' => [
				'wgCirrusSearchLogElasticRequests',
				true,
				'cirrusLogElasticRequests',
				'blah',
				true,
				[ 'wgCirrusSearchLogElasticRequestsSecret' => 'secret' ]
			],
			'wgCirrusSearchEnableAltLanguage activate' => [
				'wgCirrusSearchEnableAltLanguage',
				false,
				'cirrusAltLanguage',
				'yes',
				true,
			],
			'wgCirrusSearchEnableAltLanguage disable' => [
				'wgCirrusSearchEnableAltLanguage',
				true,
				'cirrusAltLanguage',
				'no',
				false,
			],
			'wgCirrusSearchUseCompletionSuggester disable' => [
				'wgCirrusSearchUseCompletionSuggester',
				'yes',
				'cirrusUseCompletionSuggester',
				'no',
				false
			],
			'wgCirrusSearchUseCompletionSuggester cannot be activated' => [
				'wgCirrusSearchUseCompletionSuggester',
				'no',
				'cirrusUseCompletionSuggester',
				'yes',
				'no'
			],
		];
	}

	/**
	 * @dataProvider provideOverrides
	 * @covers \CirrusSearch\Hooks::initializeForRequest
	 * @covers \CirrusSearch\Hooks::overrideYesNo
	 * @covers \CirrusSearch\Hooks::overrideSecret
	 * @covers \CirrusSearch\Hooks::overrideNumeric
	 * @covers \CirrusSearch\Hooks::overrideSecret
	 * @param string $option
	 * @param mixed $originalValue
	 * @param string $paramName
	 * @param string $paramValue
	 * @param mixed $expectedValue
	 * @throws \MWException
	 */
	public function testOverrides( $option, $originalValue, $paramName, $paramValue, $expectedValue,
		$additionalConfig = []
	) {
		$this->assertArrayHasKey( $option, $GLOBALS );
		$this->setMwGlobals( [
								 $option => $originalValue
							 ] + $additionalConfig );

		$request = new \FauxRequest( [ $paramName . "Foo" => $paramValue ] );
		Hooks::initializeForRequest( $request );
		$this->assertEquals( $originalValue, $GLOBALS[$option],
			'Unrelated param does not affect overrides' );

		$request = new \FauxRequest( [ $paramName => $paramValue ] );
		Hooks::initializeForRequest( $request );
		$this->assertEquals( $expectedValue, $GLOBALS[$option] );
	}

	public function provideMltOverrides() {
		return [
			'wgCirrusSearchMoreLikeThisConfig min_doc_freq' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'min_doc_freq' => 3 ],
				'cirrusMltMinDocFreq',
				5,
				[ 'min_doc_freq' => 5 ],
			],
			'wgCirrusSearchMoreLikeThisConfig max_doc_freq' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'max_doc_freq' => 3 ],
				'cirrusMltMaxDocFreq',
				5,
				[ 'max_doc_freq' => 5 ],
			],
			'wgCirrusSearchMoreLikeThisConfig max_query_terms' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'max_query_terms' => 3 ],
				'cirrusMltMaxQueryTerms',
				5,
				[ 'max_query_terms' => 5 ],
				[ 'wgCirrusSearchMoreLikeThisMaxQueryTermsLimit' => 6 ]
			],
			'wgCirrusSearchMoreLikeThisConfig max_query_terms too high' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'max_query_terms' => 3 ],
				'cirrusMltMaxQueryTerms',
				5,
				[ 'max_query_terms' => 3 ],
				[ 'wgCirrusSearchMoreLikeThisMaxQueryTermsLimit' => 4 ]
			],
			'wgCirrusSearchMoreLikeThisConfig min_term_freq' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'min_term_freq' => 3 ],
				'cirrusMltMinTermFreq',
				5,
				[ 'min_term_freq' => 5 ],
			],
			'wgCirrusSearchMoreLikeThisConfig minimum_should_match' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'minimum_should_match' => '30%' ],
				'cirrusMltMinimumShouldMatch',
				'50%',
				[ 'minimum_should_match' => '50%' ],
			],
			'wgCirrusSearchMoreLikeThisConfig minimum_should_match invalid' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'minimum_should_match' => '30%' ],
				'cirrusMltMinimumShouldMatch',
				'50A%',
				[ 'minimum_should_match' => '30%' ],
			],
			'wgCirrusSearchMoreLikeThisConfig min_word_length' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'min_word_length' => 3 ],
				'cirrusMltMinWordLength',
				5,
				[ 'min_word_length' => 5 ],
			],
			'wgCirrusSearchMoreLikeThisConfig max_word_length' => [
				'wgCirrusSearchMoreLikeThisConfig',
				[ 'max_word_length' => 3 ],
				'cirrusMltMaxWordLength',
				5,
				[ 'max_word_length' => 5 ],
			],
			'wgCirrusSearchMoreLikeThisFields allowed' => [
				'wgCirrusSearchMoreLikeThisFields',
				[ 'title', 'text' ],
				'cirrusMltFields',
				'text,opening_text',
				[ 'text', 'opening_text' ],
				[ 'wgCirrusSearchMoreLikeThisAllowedFields' => [ 'text', 'opening_text' ] ]
			],
			'wgCirrusSearchMoreLikeThisFields disallowed' => [
				'wgCirrusSearchMoreLikeThisFields',
				[ 'title', 'text' ],
				'cirrusMltFields',
				'text,opening_text,unknown',
				[ 'text', 'opening_text' ],
				[ 'wgCirrusSearchMoreLikeThisAllowedFields' => [ 'text', 'opening_text' ] ]
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Hooks::overrideMoreLikeThisOptions()
	 * @covers \CirrusSearch\Hooks::overrideMinimumShouldMatch()
	 * @dataProvider provideMltOverrides
	 * @param string $option
	 * @param mixed $originalValue
	 * @param string $paramName
	 * @param string $paramValue
	 * @param mixed $expectedValue
	 * @param array $additionalConfig
	 * @throws \MWException
	 */
	public function testMltOverrides( $option, $originalValue, $paramName, $paramValue,
		$expectedValue, $additionalConfig = []
	) {
		$this->assertArrayHasKey( $option, $GLOBALS );
		$nullOptions = $option === 'wgCirrusSearchMoreLikeThisConfig' ? [
			'min_doc_freq' => null,
			'max_doc_freq' => null,
			'max_query_terms' => null,
			'min_term_freq' => null,
			'min_word_length' => null,
			'max_word_length' => null,
			'minimum_should_match' => null,
		] : [];
		// Hooks use byref method on $array['value'], this creates an null entry if nothing is assigned to it.
		$originalValue += $nullOptions;
		$this->setMwGlobals( [
								 $option => $originalValue
							 ] + $additionalConfig );

		$request = new \FauxRequest( [ $paramName . "Foo" => $paramValue ] );
		Hooks::initializeForRequest( $request );
		$this->assertEquals( $originalValue, $GLOBALS[$option],
			'Unrelated param does not affect overrides' );

		$request = new \FauxRequest( [ $paramName => $paramValue ] );
		Hooks::initializeForRequest( $request );
		$this->assertEquals( $expectedValue + $nullOptions, $GLOBALS[$option] );
	}

	private function preferencesForCompletionProfiles( array $profiles ) {
		\OutputPage::setupOOUI();
		$this->setMwGlobals( [
			'wgCirrusSearchUseCompletionSuggester' => true,
		] );
		$service = new SearchProfileService();
		$service->registerDefaultProfile( SearchProfileService::COMPLETION,
			SearchProfileService::CONTEXT_DEFAULT, 'fuzzy' );
		$service->registerArrayRepository( SearchProfileService::COMPLETION, 'phpunit', $profiles );
		$factory = $this->getMockBuilder( SearchProfileServiceFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$factory->expects( $this->any() )
			->method( 'loadService' )
			->will( $this->returnValue( $service ) );
		$this->setService( SearchProfileServiceFactory::SERVICE_NAME, $factory );

		$prefs = [];
		Hooks::onGetPreferences( new \User(), $prefs );
		return $prefs;
	}

	public function testNoSearchPreferencesWhenNoChoice() {
		$prefs = $this->preferencesForCompletionProfiles( [] );
		$this->assertEquals( [], $prefs );
	}

	public function testNoSearchPreferencesWhenOnlyOneChoice() {
		$prefs = $this->preferencesForCompletionProfiles( [
			'fuzzy' => [ 'name' => 'fuzzy' ],
		] );
		$this->assertEquals( [], $prefs );
	}

	public function testSearchPreferencesAvailableWithMultipleChoices() {
		$prefs = $this->preferencesForCompletionProfiles( [
			'fuzzy' => [ 'name' => 'fuzzy' ],
			'strict' => [ 'name' => 'strict' ],
		] );
		$this->assertCount( 1, $prefs );
		$this->assertArrayHasKey( 'cirrussearch-pref-completion-profile', $prefs );
	}
}
