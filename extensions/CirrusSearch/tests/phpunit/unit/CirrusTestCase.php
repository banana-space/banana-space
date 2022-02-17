<?php

namespace CirrusSearch;

use MediaWiki\Logger\LoggerFactory;

/**
 * Base class for Cirrus test cases
 * @group CirrusSearch
 */
abstract class CirrusTestCase extends \MediaWikiUnitTestCase {
	use CirrusTestCaseTrait;

	public static function setUpBeforeClass() : void {
		parent::setUpBeforeClass();
		LoggerFactory::getInstance( 'CirrusSearchUnitTest' )->debug( 'Using seed ' . self::getSeed() );
	}

}
