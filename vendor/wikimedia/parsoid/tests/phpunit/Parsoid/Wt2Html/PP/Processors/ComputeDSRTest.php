<?php

namespace Test\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Mocks\MockDataAccess;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;

/**
 * based on tests/mocha/dsr.js
 * @coversDefaultClass \Wikimedia\Parsoid\Wt2Html\PP\Processors\ComputeDSR
 */
class ComputeDSRTest extends TestCase {
	/**
	 * @param string $wt
	 * @param DOMElement $doc
	 * @param array $spec
	 */
	public function validateSpec( string $wt, DOMElement $doc, $spec ): void {
		$elts = DOMCompat::querySelectorAll( $doc, $spec['selector'] );
		$this->assertEquals( count( $elts ), 1 );

		$dp = DOMDataUtils::getDataParsoid( $elts[0] );
		$dsr = $dp->dsr;
		$this->assertEquals( !empty( $dsr ), true );
		$this->assertEquals( is_object( $dsr ), true );

		$this->assertEquals( $dsr->substr( $wt ), $spec['dsrContent'][0],
			"dsr all actual mismatch: \n\"" .
			$dsr->substr( $wt ) . "\"\ndoes not match expected:\n\"" . $spec['dsrContent'][0] . "\"" );
		$this->assertEquals( $dsr->openSubstr( $wt ), $spec['dsrContent'][1],
			"dsr open actual mismatch: \n\"" .
			$dsr->openSubstr( $wt ) . "\"\ndoes not match expected:\n\"" . $spec['dsrContent'][1] . "\"" );
		$this->assertEquals( $dsr->closeSubstr( $wt ), $spec['dsrContent'][2],
			"dsr close actual mismatch: \n\"" .
			$dsr->closeSubstr( $wt ) . "\"\ndoes not match expected:\n\"" . $spec['dsrContent'][2] . "\"" );
	}

	/**
	 * @param array $test
	 */
	public function runDSRTest( array $test ): void {
		$siteConfig = new MockSiteConfig( [] );
		$dataAccess = new MockDataAccess( [] );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$content = new MockPageContent( [ 'main' => $test['wt'] ] );
		$pageConfig = new MockPageConfig( [], $content );
		$html = $parsoid->wikitext2html( $pageConfig, [ "wrapSections" => false ] );

		$mockEnv = new MockEnv( [] );
		$doc = ContentUtils::ppToDOM( $mockEnv, $html );

		$wt = $test['wt'];
		$specs = $test['specs'];
		foreach ( $specs as $spec ) {
			$this->validateSpec( $wt, $doc, $spec );
		}
	}

	/**
	 * For every test with a 'wt' property, provide the 'spec' property that is
	 * an array of DSR specs that need to be verified on the parsed output of 'wt'.
	 * Every spec should have:
	 *   selector: a CSS selector for picking a DOM node in the parsed output.
	 *   dsrContent: a 3-element array. The first element is the wikitext corresponding
	 *               to the wikitext substring between dsr[0]..dsr[1]. The second and
	 *               third elements are the opening/closing wikitext tag for that node.
	 * @covers ::computeNodeDSR
	 * @dataProvider provideParaComputeDSR
	 * @param array $testInfo
	 */
	public function testParaComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function provideParaComputeDSR(): array {
		return [
			[
				[ 'wt' => 'a',    // disable first test so we can get to the juicy failure immediately.
					'specs' => [
						[ 'selector' => 'body > p', 'dsrContent' => [ 'a', '', '' ] ]
					]
				]
			],
			[
				[ 'wt' => "a\n\nb",
					'specs' => [
						[ 'selector' => 'body > p:nth-child(1)', 'dsrContent' => [ 'a', '', '' ] ],
						[ 'selector' => 'body > p:nth-child(2)', 'dsrContent' => [ 'b', '', '' ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider provideListComputeDSR
	 * @param array $testInfo
	 */
	public function testListComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function provideListComputeDSR(): array {
		return [
			[
				[ 'wt' => "*a\n*b",
					'specs' => [
						[ 'selector' => 'ul', 'dsrContent' => [ "*a\n*b", '', '' ] ],
						[ 'selector' => 'ul > li:nth-child(1)', 'dsrContent' => [ '*a', '*', '' ] ],
						[ 'selector' => 'ul > li:nth-child(2)', 'dsrContent' => [ '*b', '*', '' ] ]
					]
				]
			],
			[
				[ 'wt' => "*a\n**b\n***c\n*d",
					'specs' => [
						[ 'selector' => 'body > ul', 'dsrContent' => [ "*a\n**b\n***c\n*d", '', '' ] ],
						[ 'selector' => 'body > ul > li:first-child', 'dsrContent' => [ "*a\n**b\n***c", '*', '' ] ],
						[ 'selector' => 'body > ul > li:first-child > ul', 'dsrContent' => [ "**b\n***c", '', '' ] ],
						[ 'selector' => 'body > ul > li:first-child > ul > li:first-child',
							'dsrContent' => [ "**b\n***c", '**', '' ] ],
						[ 'selector' => 'body > ul > li:first-child > ul > li:first-child > ul > li',
							'dsrContent' => [ '***c', '***', '' ] ],
						[ 'selector' => 'body > ul > li:nth-child(2)', 'dsrContent' => [ '*d', '*', '' ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider provideHeadingComputeDSR
	 * @param array $testInfo
	 */
	public function testHeadingComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	public function provideHeadingComputeDSR(): array {
		return [
			[
				[ 'wt' => "=A=\n==B==\n===C===\n====D====",
					'specs' => [
						[ 'selector' => 'body > h1', 'dsrContent' => [ '=A=', '=', '=' ] ],
						[ 'selector' => 'body > h2', 'dsrContent' => [ '==B==', '==', '==' ] ],
						[ 'selector' => 'body > h3', 'dsrContent' => [ '===C===', '===', '===' ] ],
						[ 'selector' => 'body > h4', 'dsrContent' => [ '====D====', '====', '====' ] ]
					]
				]
			],
			[
				[ 'wt' => "=A New Use for the = Sign=\n==The == Operator==",
					'specs' => [
						[ 'selector' => 'body > h1',
							'dsrContent' => [ '=A New Use for the = Sign=', '=', '=' ] ],
						[ 'selector' => 'body > h2', 'dsrContent' => [ '==The == Operator==', '==', '==' ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider provideQuotesComputeDSR
	 * @param array $testInfo
	 */
	public function testQuotesComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function provideQuotesComputeDSR(): array {
		return [
			[
				[ 'wt' => "''a''\n'''b'''",
					'specs' => [
						[ 'selector' => 'p > i', 'dsrContent' => [ "''a''", "''", "''" ] ],
						[ 'selector' => 'p > b', 'dsrContent' => [ "'''b'''", "'''", "'''" ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider provideTableComputeDSR
	 * @param array $testInfo
	 */
	public function testTableComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function provideTableComputeDSR(): array {
		return [
			[
				[ 'wt' => "{|\n|-\n|A\n|}",
					'specs' => [
						[ 'selector' => 'body > table', 'dsrContent' => [ "{|\n|-\n|A\n|}", '{|', '|}' ] ],
						[ 'selector' => 'body > table > tbody > tr', 'dsrContent' => [ "|-\n|A", '|-', '' ] ],
						[ 'selector' => 'body > table > tbody > tr > td', 'dsrContent' => [ '|A', '|', '' ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider providePreComputeDSR
	 * @param array $testInfo
	 */
	public function testPreComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function providePreComputeDSR() : array {
		return [
			[
				[ 'wt' => " Preformatted text ",
					'specs' => [
						[ 'selector' => 'body > pre', 'dsrContent' => [ " Preformatted text ", ' ', '' ] ]
					]
				]
			]
		];
	}

	/**
	 * @covers ::computeNodeDSR
	 * @dataProvider provideEltComputeDSR
	 * @param array $testInfo
	 */
	public function testEltComputeDSR( array $testInfo ): void {
		$this->runDSRTest( $testInfo );
	}

	/**
	 * @return array
	 */
	public function provideEltComputeDSR(): array {
		return [
			[
				[ 'wt' => "<small>'''bold'''</small>",
					'specs' => [
						[ 'selector' => 'body > p',
							'dsrContent' => [ "<small>'''bold'''</small>", '', '' ] ],
						[ 'selector' => 'body > p > small',
							'dsrContent' => [ "<small>'''bold'''</small>", '<small>', '</small>' ] ]
					]
				]
			]
		];
	}

}
