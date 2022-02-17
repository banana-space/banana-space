<?php

namespace CirrusSearch\Tests\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Maintenance\SuggesterAnalysisConfigBuilder;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Maintenance\SuggesterAnalysisConfigBuilder
 */
class SuggesterAnalysisConfigBuilderTest extends CirrusIntegrationTestCase {

	public function provideLanguageAnalysis() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'languageAnalysisCompSuggest/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$extraConfig = CirrusIntegrationTestCase::loadFixture( $testFile );
			if ( isset( $extraConfig[ 'LangCode' ] ) ) {
				$langCode = $extraConfig[ 'LangCode' ];
			} else {
				$langCode = $testName;
			}
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[ $testName ] = [ $expectedFile, $langCode, $extraConfig ];
		}
		return $tests;
	}

	/**
	 * Test various language specific analysers against fixtures, to make
	 *  the results of generation obvious and tracked in git
	 *
	 * @dataProvider provideLanguageAnalysis
	 * @param mixed $expected
	 * @param string $langCode
	 * @param array $extraConfig
	 */
	public function testLanguageAnalysis( $expected, $langCode, array $extraConfig ) {
		$this->setTemporaryHook( 'CirrusSearchAnalysisConfig',
			function () {
			}
		);
		$config = new HashSearchConfig( $extraConfig + [ 'CirrusSearchSimilarityProfile' => 'default' ] );
		$plugins = [
			'analysis-stempel', 'analysis-kuromoji',
			'analysis-smartcn', 'analysis-hebrew',
			'analysis-ukrainian', 'analysis-stconvert',
			'extra-analysis-serbian', 'extra-analysis-slovak',
			'extra-analysis-esperanto', 'analysis-nori',
		];
		$builder = new SuggesterAnalysisConfigBuilder( $langCode, $plugins, $config );
		if ( !CirrusIntegrationTestCase::hasFixture( $expected ) ) {
			if ( self::canRebuildFixture() ) {
				CirrusIntegrationTestCase::saveFixture( $expected, $builder->buildConfig() );
				$this->markTestSkipped();
				return;
			} else {
				$this->fail( 'Missing fixture file ' . $expected );
			}
		} else {
			$expectedConfig = CirrusIntegrationTestCase::loadFixture( $expected );
			$this->assertEquals( $expectedConfig, $builder->buildConfig() );
		}
	}
}
