<?php

/**
 * Note: this is not a unit test, as it touches the file system and reads an actual file.
 * If unit tests are added for MediaWikiVersionFetcher, this should be done in a distinct test case.
 *
 * @covers MediaWikiVersionFetcher
 *
 * @group ComposerHooks
 *
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class MediaWikiVersionFetcherTest extends \MediaWikiIntegrationTestCase {

	public function testReturnsResult() {
		$versionFetcher = new MediaWikiVersionFetcher();
		$this->assertSame( MW_VERSION, $versionFetcher->fetchVersion() );
	}

}
