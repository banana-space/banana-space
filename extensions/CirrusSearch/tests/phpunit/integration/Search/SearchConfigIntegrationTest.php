<?php

namespace CirrusSearch;

use MediaWiki\MediaWikiServices;

/**
 * @covers \CirrusSearch\SearchConfig
 */
class SearchConfigIntegrationTest extends CirrusIntegrationTestCase {
	public function testLoadContLang() {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$config = new HashSearchConfig(
			[ 'LanguageCode' => 'fr' ],
			[ HashSearchConfig::FLAG_LOAD_CONT_LANG, HashSearchConfig::FLAG_INHERIT ] );
		$frContLang = $config->get( 'ContLang' );
		$this->assertNotSame( $contLang, $frContLang );
		$this->assertSame( \Language::factory( 'fr' ), $frContLang );
	}

	public function testMWServiceIntegration() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		$this->assertInstanceOf( SearchConfig::class, $config );
	}

}
