<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use ExtensionRegistry;

/**
 * @covers \CirrusSearch\Maintenance\MappingConfigBuilder
 */
class MappingConfigBuilderTest extends CirrusIntegrationTestCase {

	public function buildProvider() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'mapping/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$buildClass = preg_match( '/\Q-archive.config\E$/', $testFile )
				? ArchiveMappingConfigBuilder::class
				: MappingConfigBuilder::class;
			$extraConfig = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [ $expectedFile, $extraConfig, $buildClass ];
		}
		return $tests;
	}

	/**
	 * @dataProvider buildProvider
	 */
	public function testBuild( $expectedFile, $extraConfig, $buildClass ) {
		// The set of installed extensions might have extra content models
		// which provide extra fields, but we only want the default values
		// from core.
		$this->setMwGlobals( [
			'wgContentHandlers' => $this->defaultContentHandlers(),
		] );
		$this->mergeMwGlobalArrayValue( 'wgHooks', [
			'CirrusSearchMappingConfig' => [],
			'GetContentModels' => [],
			'SearchIndexFields' => [],
		] );
		$scopedCallback = ExtensionRegistry::getInstance()->setAttributeForTest( 'Hooks', [] );

		$defaultConfig = [
			'CirrusSearchSimilarityProfile' => 'bm25_with_defaults',
			'CirrusSearchWikimediaExtraPlugin' => [
				'regex' => [ 'build' ],
			],
			'CirrusSearchPhraseSuggestReverseField' => [
				'build' => true,
			],
		];
		$config = new HashSearchConfig( $defaultConfig + $extraConfig, [ HashSearchConfig::FLAG_INHERIT ] );
		$builder = new $buildClass( true, 0, $config );
		$flags = 0;
		$mapping = $builder->buildConfig( $flags );
		$mappingJson = \FormatJson::encode( $mapping, true );

		$this->assertFileContains(
			CirrusIntegrationTestCase::fixturePath( $expectedFile ),
			$mappingJson,
			CirrusIntegrationTestCase::canRebuildFixture()
		);
	}

	private function defaultContentHandlers() {
		global $IP;
		require "$IP/includes/DefaultSettings.php";
		return $wgContentHandlers;
	}
}
