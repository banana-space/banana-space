<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\Parsoid;

use Flow\Parsoid\ContentFixer;
use Flow\Parsoid\Fixer\BaseHrefFixer;
use Title;

/**
 * @covers \Flow\Parsoid\Fixer\BaseHrefFixer
 *
 * @group Flow
 */
class BaseHrefFixerTest extends \MediaWikiTestCase {
	protected function setUp() : void {
		parent::setUp();
		$this->setMwGlobals( 'wgServer', 'http://mywiki' );
	}

	public static function baseHrefProvider() {
		return [
			[
				'Rewrites href of link surrounding image',
				'<figure class="mw-default-size" typeof="mw:Image/Thumb" data-parsoid="...">'
					. '<a href="http://mywiki/wiki/./File:Example.jpg" data-parsoid="...">'
					. '<img resource="./File:Example.jpg" src="//upload.wikimedia.org/wikipedia/mediawiki/thumb/a/a9/Example.jpg/220px-Example.jpg" data-parsoid="..." height="147" width="220"/>'
					. '</a>'
					. '<figcaption data-parsoid="..."> caption</figcaption>'
					. '</figure>',
				'<figure class="mw-default-size" typeof="mw:Image/Thumb" data-parsoid="...">'
					. '<a href="./File:Example.jpg" data-parsoid="...">'
					. '<img resource="./File:Example.jpg" src="//upload.wikimedia.org/wikipedia/mediawiki/thumb/a/a9/Example.jpg/220px-Example.jpg" data-parsoid="..." height="147" width="220">'
					. '</a>'
					. '<figcaption data-parsoid="..."> caption</figcaption>'
					. '</figure>',
			],
		];
	}

	/**
	 * @dataProvider baseHrefProvider
	 */
	public function testBaseHrefFixer( $message, $expectedAfter, $before ) {
		$fixer = new ContentFixer( new BaseHrefFixer( '/wiki/$1' ) );
		$result = $fixer->apply( $before, Title::newMainPage() );
		$this->assertEquals( $expectedAfter, $result, $message );
	}
}
