<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\Parsoid\Fixer;

use Flow\Parsoid\ContentFixer;
use Flow\Parsoid\Fixer\WikiLinkFixer;
use Flow\Tests\PostRevisionTestCase;
use Title;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\Parsoid\Fixer\WikiLinkFixer
 *
 * @group Flow
 * @group Database
 */
class WikiLinkFixerTest extends PostRevisionTestCase {

	public static function redLinkProvider() {
		return [
			[
				'Basic redlink application',
				// html from parsoid for: [[Talk:Flow/Bugs]]
				'<a rel="mw:WikiLink" href="./Talk:Flow/Bugs" data-parsoid=\'{"stx":"simple","a":{"href":"./Talk:Flow/Bugs"},"sa":{"href":"Talk:Flow/Bugs"},"dsr":[0,18,2,2]}\'>Talk:Flow/Bugs</a>',
				// expect string
				// @fixme easily breakable, depends on url order
				htmlentities( 'Talk:Flow/Bugs&action=edit&redlink=1' ),
			],

			[
				'Subpage redlink application',
				// html from parsoid for: [[/SubPage]]
				'<a rel="mw:WikiLink" href=".//SubPage" data-parsoid=\'{"stx":"simple","a":{"href":".//SubPage"},"sa":{"href":"/SubPage"},"dsr":[0,12,2,2]}\'>/SubPage</a>',
				// expect string
				htmlentities( 'Main_Page/SubPage&action=edit&redlink=1' ),
			],

			[
				'Link containing html entities should be properly handled',
				// html from parsoid for: [[Foo&Bar]]
				'<a rel="mw:WikiLink" href="./Foo&amp;Bar" data-parsoid=\'{"stx":"simple","a":{"href":"./Foo&amp;Bar"},"sa":{"href":"Foo&amp;Bar"},"dsr":[0,11,2,2]}\'>Foo&amp;Bar</a>',
				// expect string
				'>Foo&amp;Bar</a>',
			],

			[
				'Link containing UTF-8 anchor content passes through as UTF-8',
				// html from parsoid for: [[Foo|test – test]]
				'<a rel="mw:WikiLink" href="./Foo" data-parsoid=\'{"stx":"piped","a":{"href":"./Foo"},"sa":{"href":"Foo"},"dsr":[0,19,6,2]}\'>test – test</a>',
				// title text from parsoid
				// expect string
				'test – test',
			],

			[
				'Link containing urlencoded UTF-8 href works',
				// html from parsoid for: [[Viquipèdia:La taverna/Tecnicismes/Arxius_2]]
				'<a rel="mw:WikiLink" href="./Viquip%C3%A8dia:La_taverna/Tecnicismes/Arxius_2" title="Viquipdia:La taverna/Tecnicismes/Arxius 2" data-parsoid=\'{"stx":"simple","a":{"href":"./Viquipdia:La_taverna/Tecnicismes/Arxius_2"},"sa":{"href":"Viquipdia:La taverna/Tecnicismes/Arxius 2"},"dsr":[59,105,2,2]}\'>Viquipdia:La taverna/Tecnicismes/Arxius 2</a>',
				// anchor should be transformed to /wiki/Viquip...
				// annoyingly we don't control Title::exists() so just assume redlink
				// with index.php
				'index.php?title=Viquip%C3%A8dia:La_taverna/Tecnicismes/Arxius_2'
			],
		];
	}

	/**
	 * @dataProvider redLinkProvider
	 */
	public function testAppliesRedLinks( $message, $anchor, $expect ) {
		$fixer = new ContentFixer( new WikiLinkFixer( $this->createMock( \LinkBatch::class ) ) );
		$result = $fixer->apply( $anchor, Title::newMainPage() );
		$this->assertStringContainsString( $expect, $result, $message );
	}
}
