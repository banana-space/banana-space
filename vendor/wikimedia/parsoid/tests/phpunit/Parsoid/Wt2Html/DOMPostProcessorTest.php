<?php
// phpcs:disable Generic.Files.LineLength.TooLong

namespace Test\Parsoid\Wt2Html;

use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Wt2Html\DOMPostProcessor;

use Wikimedia\Parsoid\Wt2Html\PP\Handlers\CleanUp;

use Wikimedia\Parsoid\Wt2Html\PP\Processors\Normalize;

class DOMPostProcessorTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\DOMPostProcessor
	 * @dataProvider provideDOMPostProcessor
	 */
	public function testDOMPostProcessor( $atTopLevel, $processors, $html, $expected ) {
		// Use 'Test Page' to verify that dc:isVersioOf link in header uses underscores
		// but the user rendered version in <title> in header uses spaces.
		$mockEnv = new MockEnv( [ 'title' => 'Test Page' ] );
		$dpp = new DOMPostProcessor( $mockEnv );
		$dpp->registerProcessors( $processors );
		$opts = [
			'toplevel' => $atTopLevel
		];
		$dpp->resetState( $opts );
		$document = ( ContentUtils::ppToDOM( $mockEnv, $html ) )->ownerDocument;
		$dpp->doPostProcess( $document );
		$this->assertEquals( $expected, DOMCompat::getOuterHTML( $document->documentElement ) );
	}

	public function provideDOMPostProcessor() {
		return [
			[
				false,
				[],
				"<div>123</div>",
				'<html><head></head><body data-object-id="0"><div data-object-id="1">123</div></body></html>'
			],
			[
				true,
				[],
				"<div>123</div>",
				'<html prefix="dc: http://purl.org/dc/terms/ mw: http://mediawiki.org/rdf/" about="https://my.wiki.example/wikix/Special:Redirect/revision/1"><head prefix="mwr: https://my.wiki.example/wikix/Special:Redirect/"><meta charset="utf-8"/><meta property="mw:pageId" content="-1"/><meta property="mw:pageNamespace" content="0"/><meta property="mw:html:version" content="2.1.0"/><link rel="dc:isVersionOf" href="//my.wiki.example/wikix/Test_Page"/><title>Test Page</title><base href="//my.wiki.example/wikix/"/><link rel="stylesheet" href="//my.wiki.example/wx/load.php?modules=mediawiki.skinning.content.parsoid%7Cmediawiki.skinning.interface%7Csite.styles%7Cmediawiki.page.gallery.styles%7Cext.cite.style%7Cext.cite.styles&amp;only=styles&amp;skin=vector"/><!--[if lt IE 9]><script src="//my.wiki.example/wx/load.php?modules=html5shiv&amp;only=scripts&amp;skin=vector&amp;sync=1"></script><script>html5.addElements(\'figure-inline\');</script><![endif]--><meta http-equiv="content-language" content="en"/><meta http-equiv="vary" content="Accept"/></head><body data-parsoid=\'{"dsr":[0,39,0,0]}\' lang="en" class="mw-content-rtl sitedir-rtl rtl mw-body-content parsoid-body mediawiki mw-parser-output" dir="rtl"><section data-mw-section-id="0" data-parsoid="{}"><div data-parsoid=\'{"autoInsertedEnd":true,"autoInsertedStart":true,"dsr":[36,39,0,0]}\'>123</div></section></body></html>'
			],
			[
				false,
				[
					[
						'Processor' => Normalize::class
					]
				],
				"<p>hi</p><p></p><p>ho</p>",
				'<html><head></head><body data-object-id="0"><p data-object-id="1">hi</p><p data-object-id="2"></p><p data-object-id="3">ho</p></body></html>'
			],
			[
				false,
				[
					[
						'name' => 'CleanUp-handleEmptyElts',
						'shortcut' => 'cleanup',
						'isTraverser' => true,
						'handlers' => [
							[
								'nodeName' => null,
								'action' => [ CleanUp::class, 'handleEmptyElements' ]
							]
						]
					]
				],
				"<p>hi</p><p></p><p>ho</p>",
				'<html><head></head><body data-object-id="0"><p data-object-id="1">hi</p><p data-object-id="2" class="mw-empty-elt"></p><p data-object-id="3">ho</p></body></html>'
			]
		];
	}

}
