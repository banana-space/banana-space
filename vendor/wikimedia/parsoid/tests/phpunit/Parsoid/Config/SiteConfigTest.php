<?php

namespace Test\Parsoid\Config;

use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Wikimedia\Parsoid\Config\SiteConfig
 */
class SiteConfigTest extends \PHPUnit\Framework\TestCase {

	/** @return MockObject|SiteConfig */
	private function getSiteConfig( array $methods = [] ) {
		return $this->getMockBuilder( SiteConfig::class )
			->setMethods( $methods )
			->getMockForAbstractClass();
	}

	public function testNamespaceIsTalk() {
		$siteConfig = $this->getSiteConfig();
		$this->assertFalse( $siteConfig->namespaceIsTalk( -2 ) );
		$this->assertFalse( $siteConfig->namespaceIsTalk( -1 ) );
		$this->assertFalse( $siteConfig->namespaceIsTalk( 0 ) );
		$this->assertTrue( $siteConfig->namespaceIsTalk( 1 ) );
		$this->assertFalse( $siteConfig->namespaceIsTalk( 2 ) );
		$this->assertTrue( $siteConfig->namespaceIsTalk( 3 ) );
	}

	public function testUcfirst() {
		$siteConfig = $this->getSiteConfig( [ 'lang' ] );
		$siteConfig->method( 'lang' )->willReturn( 'en' );

		$this->assertSame( 'Foo', $siteConfig->ucfirst( 'Foo' ) );
		$this->assertSame( 'Foo', $siteConfig->ucfirst( 'foo' ) );
		$this->assertSame( 'Ááá', $siteConfig->ucfirst( 'ááá' ) );
		$this->assertSame( 'Iii', $siteConfig->ucfirst( 'iii' ) );

		foreach ( [ 'tr', 'kaa', 'kk', 'az' ] as $lang ) {
			$siteConfig = $this->getSiteConfig( [ 'lang' ] );
			$siteConfig->method( 'lang' )->willReturn( $lang );
			$this->assertSame( 'İii', $siteConfig->ucfirst( 'iii' ), "Special logic for $lang" );
		}
	}

	/**
	 * @dataProvider provideMakeExtResourceURL
	 */
	public function testMakeExtResourceURL( $match, $href, $content, $expect ) {
		$siteConfig = $this->getSiteConfig();
		$this->assertSame( $expect, $siteConfig->makeExtResourceURL( $match, $href, $content ) );
	}

	public function provideMakeExtResourceURL() {
		return [
			[
				[ 'ISBN', '9780615720302' ], './Special:Booksources/9780615720302', 'ISBN 978-0615720302',
				'ISBN 978-0615720302'
			],
			[
				[ 'ISBN', '0596519796' ], './Special:Booksources/0596519796', "ISBN\u{00A0}0-596-51979-6",
				"ISBN\u{00A0}0-596-51979-6"
			],
			[
				[ 'ISBN', '221212466X' ], './Special:Booksources/221212466X', 'ISBN 2-212-12466-x',
				'ISBN 2-212-12466-x'
			],
			[
				[ 'ISBN', '9780615720302' ], './Special:Booksources/9780615720302', 'Working With MediaWiki',
				'[[Special:Booksources/9780615720302|Working With MediaWiki]]'
			],
			[
				[ 'RFC', '2324' ], 'https://tools.ietf.org/html/rfc2324', 'RFC 2324', 'RFC 2324'
			],
			[
				[ 'RFC', '2324' ], 'https://tools.ietf.org/html/rfc2324', 'HTCPCP',
				'[https://tools.ietf.org/html/rfc2324 HTCPCP]'
			],
		];
	}

	public function testMakeExtResourceURL_invalid() {
		$siteConfig = $this->getSiteConfig();
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "Invalid match type 'Bogus'" );
		$siteConfig->makeExtResourceURL( [ 'Bogus', '0' ], './Bogus', 'Bogus 0' );
	}

	/**
	 * @dataProvider provideInterwikiMatcher
	 */
	public function testInterwikiMatcher( $href, $expect, $batchSize = null ) {
		$siteConfig = $this->getSiteConfig( [ 'interwikiMap' ] );
		if ( $batchSize !== null ) {
			TestingAccessWrapper::newFromObject( $siteConfig )->iwMatcherBatchSize = $batchSize;
		}
		$siteConfig->expects( $this->once() )->method( 'interwikiMap' )->willReturn( [
			'w' => [
				'prefix' => 'w',
				'local' => true,
				'localinterwiki' => true,
				'url' => 'https://en.wikipedia.org/wiki/$1',
			],
			'en' => [
				'prefix' => 'en',
				'local' => true,
				'language' => true,
				'localinterwiki' => true,
				'url' => 'https://en.wikipedia.org/wiki/$1',
			],
			'de' => [
				'prefix' => 'de',
				'local' => true,
				'language' => true,
				'url' => 'https://de.wikipedia.org/wiki/$1',
			],
			'iarchive' => [
				'prefix' => 'iarchive',
				'url' => 'https://archive.org/details/$1',
				'protorel' => true,
			],
			'example' => [
				'prefix' => 'example',
				'url' => '//example.org/$1',
			],
		] );

		$this->assertSame( $expect, $siteConfig->interwikiMatcher( $href ) );
		// Again, to test caching
		$this->assertSame( $expect, $siteConfig->interwikiMatcher( $href ) );
	}

	/**
	 * @dataProvider provideInterwikiMatcher
	 */
	public function testInterwikiMatcher_smallBatches( $href, $expect ) {
		$this->testInterwikiMatcher( $href, $expect, 1 );
	}

	public function provideInterwikiMatcher() {
		return [
			[ 'https://de.wikipedia.org/wiki/Foobar', [ ':de', 'Foobar' ] ],
			[ 'https://en.wikipedia.org/wiki/Foobar', [ ':en', 'Foobar' ] ], // not 'w'
			[ './w:Foobar', [ 'w', 'Foobar' ] ],
			[ 'de%3AFoobar', [ ':de', 'Foobar' ] ],

			// Protocol-relative handling
			[ 'https://archive.org/details/301works', [ 'iarchive', '301works' ] ],
			[ 'http://archive.org/details/301works', [ 'iarchive', '301works' ] ],
			[ '//archive.org/details/301works', [ 'iarchive', '301works' ] ],
			[ 'https://example.org/foobar', [ 'example', 'foobar' ] ],
			[ 'http://example.org/foobar', [ 'example', 'foobar' ] ],
			[ '//example.org/foobar', [ 'example', 'foobar' ] ],
			[ 'http://en.wikipedia.org/wiki/Foobar', null ],
			[ '//en.wikipedia.org/wiki/Foobar', null ],
		];
	}

	public function testInterwikiMatcher_tooManyPatterns() {
		$map = [];
		for ( $i = 0; $i < 100000; $i++ ) {
			$map["x$i"] = [
				'prefix' => "x$i",
				'url' => "https://example.org/$i/$1",
			];
		}
		$map["l"] = [
			'prefix' => 'l',
			'language' => true,
			'url' => 'https://example.org/42/$1'
		];

		$siteConfig = $this->getSiteConfig( [ 'interwikiMap' ] );
		$siteConfig->method( 'interwikiMap' )->willReturn( $map );

		$this->assertSame(
			[ 'x9876', 'Foobar' ],
			$siteConfig->interwikiMatcher( 'https://example.org/9876/Foobar' )
		);
		$this->assertSame(
			[ ':l', 'Foobar' ],
			$siteConfig->interwikiMatcher( 'https://example.org/42/Foobar' )
		);
		$this->assertSame(
			null,
			$siteConfig->interwikiMatcher( 'https://example.org/9999999/Foobar' )
		);
	}

}
