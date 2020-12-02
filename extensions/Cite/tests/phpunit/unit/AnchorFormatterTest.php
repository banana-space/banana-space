<?php

namespace Cite\Tests\Unit;

use Cite\AnchorFormatter;
use Cite\ReferenceMessageLocalizer;
use Message;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\AnchorFormatter
 *
 * @license GPL-2.0-or-later
 */
class AnchorFormatterTest extends \MediaWikiUnitTestCase {

	public function setUp() : void {
		parent::setUp();

		global $wgFragmentMode;
		$wgFragmentMode = [ 'html5' ];
	}

	/**
	 * @covers ::refKey
	 */
	public function testRefKey() {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'plain' )->willReturn( '(plain:' . implode( '|', $args ) . ')' );
				$msg->method( 'text' )->willReturn( '(text:' . implode( '|', $args ) . ')' );
				return $msg;
			}
		);
		$formatter = new AnchorFormatter( $mockMessageLocalizer );

		$this->assertSame(
			'(text:cite_reference_link_prefix)key(text:cite_reference_link_suffix)',
			$formatter->refKey( 'key', null ) );
		$this->assertSame(
			'(text:cite_reference_link_prefix)' .
				'(plain:cite_reference_link_key_with_num&#124;key&#124;2)(text:cite_reference_link_suffix)',
			$formatter->refKey( 'key', '2' ) );
	}

	/**
	 * @covers ::getReferencesKey
	 */
	public function testGetReferencesKey() {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'text' )->willReturn( '(' . implode( '|', $args ) . ')' );
				return $msg;
			}
		);
		$formatter = new AnchorFormatter( $mockMessageLocalizer );

		$this->assertSame(
			'(cite_references_link_prefix)key(cite_references_link_suffix)',
			$formatter->getReferencesKey( 'key' ) );
	}

	/**
	 * @covers ::normalizeKey
	 * @covers ::__construct
	 * @dataProvider provideKeyNormalizations
	 */
	public function testNormalizeKey( $key, $expected ) {
		/** @var AnchorFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new AnchorFormatter(
			$this->createMock( ReferenceMessageLocalizer::class ) ) );
		$this->assertSame( $expected, $formatter->normalizeKey( $key ) );
	}

	public function provideKeyNormalizations() {
		return [
			[ 'a b', 'a_b' ],
			[ 'a  __  b', 'a_b' ],
			[ ':', ':' ],
			[ "\t\n", '_' ],
			[ "'", '&#039;' ],
			[ "''", '&#039;&#039;' ],
			[ '"%&/<>?[]{|}', '&quot;%&amp;/&lt;&gt;?&#91;&#93;&#123;&#124;&#125;' ],
			[ 'ISBN', '&#73;SBN' ],
		];
	}

}
