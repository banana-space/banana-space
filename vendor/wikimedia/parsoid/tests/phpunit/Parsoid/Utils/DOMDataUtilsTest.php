<?php

namespace Test\Parsoid\Utils;

use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Utils\DataBag;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * @coversDefaultClass  \Wikimedia\Parsoid\Utils\DOMDataUtils
 */
class DOMDataUtilsTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @covers ::injectPageBundle
	 */
	public function testInjectPageBundle() {
		$doc = DOMUtils::parseHTML( "Hello, world" );
		DOMDataUtils::injectPageBundle( $doc, PHPUtils::arrayToObject( [
			'node-id' => [ 'parsoid' => [ 'rah' => 'rah' ] ],
		] ) );
		// Note that we use the 'native' getElementById, not
		// DOMCompat::getElementById, in order to test T232390
		$el = $doc->getElementById( 'mw-pagebundle' );
		$this->assertNotEquals( $el, null );
		$this->assertEquals( $el->tagName, 'script' );
	}

	/**
	 * @covers ::storeInPageBundle
	 */
	public function testStoreInPageBundle() {
		$env = new MockEnv( [] );
		$doc = DOMUtils::parseHTML( "<p>Hello, world</p>" );
		$doc->bag = new DataBag(); // see Env::createDocument
		$p = DOMCompat::querySelector( $doc, 'p' );
		DOMDataUtils::storeInPageBundle( $p, $env, PHPUtils::arrayToObject( [
			'parsoid' => [ 'go' => 'team' ],
			'mw' => [ 'test' => 'me' ],
		] ), DOMDataUtils::usedIdIndex( $p ) );
		$id = $p->getAttribute( 'id' );
		$this->assertNotEquals( $id, null );
		// Use the 'native' getElementById, not DOMCompat::getElementById,
		// in order to test T232390.
		$el = $doc->getElementById( $id );
		$this->assertEquals( $el, $p );
	}
}
