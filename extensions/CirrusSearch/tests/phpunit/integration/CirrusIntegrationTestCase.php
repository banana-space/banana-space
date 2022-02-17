<?php

namespace CirrusSearch;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class CirrusIntegrationTestCase extends \MediaWikiIntegrationTestCase {
	use CirrusTestCaseTrait;

	public static function setUpBeforeClass() : void {
		parent::setUpBeforeClass();
		LoggerFactory::getInstance( 'CirrusSearchIntegTest' )->debug( 'Using seed ' . self::getSeed() );
	}

	protected function setUp() : void {
		parent::setUp();
		$services = MediaWikiServices::getInstance();
		$services->resetServiceForTesting( InterwikiResolver::SERVICE );
		$services->getConfigFactory()->makeConfig( 'CirrusSearch' )->clearCachesForTesting();
	}
}
