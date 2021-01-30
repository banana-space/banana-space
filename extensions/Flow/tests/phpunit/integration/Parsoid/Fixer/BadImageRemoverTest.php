<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\Parsoid;

use Flow\Parsoid\ContentFixer;
use Flow\Parsoid\Fixer\BadImageRemover;
use Title;

/**
 * @covers \Flow\Parsoid\Fixer\BadImageRemover
 *
 * @group Flow
 */
class BadImageRemoverTest extends \MediaWikiTestCase {

	/**
	 * Note that this must return html rather than roundtripping wikitext
	 * through parsoid because that is not current available from the jenkins
	 * test runner/
	 */
	public static function imageRemovalProvider() {
		return [
			[
				'Passes through allowed good inline images',
				// expected html after filtering
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"/></a></figure-inline> and other stuff</p>',
				// input html
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a></figure-inline> and other stuff</p>',
				// accept/decline callback
				function () {
					return false;
				}
			],

			[
				'Passes through allowed good inline images with percent in name',
				// expected html after filtering
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:100%25.jpg"><img resource="./File:100%25.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/100%25.jpg" height="500" width="500"/></a></figure-inline> and other stuff</p>',
				// input html
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:100%25.jpg"><img resource="./File:100%25.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/100%25.jpg" height="500" width="500"></a></figure-inline> and other stuff</p>',
				// accept/decline callback
				function () {
					return false;
				}
			],

			[
				'Passes through allowed good inline images (with legacy span markup)',
				// expected html after filtering
				'<p><span class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"/></a></span> and other stuff</p>',
				// input html
				'<p><span class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a></span> and other stuff</p>',
				// accept/decline callback
				function () {
					return false;
				}
			],

			[
				'Passes through allowed good block images',
				// expected html after filtering
				'<figure class="mw-default-size" typeof="mw:Image/Thumb"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"/></a><figcaption>Blah blah</figcaption></figure>',
				// input html
				'<figure class="mw-default-size" typeof="mw:Image/Thumb"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a><figcaption>Blah blah</figcaption></figure>',
				// accept/decline callback
				function () {
					return false;
				}
			],

			[
				'Keeps unknown images',
				// expected html after filtering
				'<meta typeof="mw:Placeholder" data-parsoid="..."/>',
				// input html
				'<meta typeof="mw:Placeholder" data-parsoid="...">',
				// accept/decline callback
				function () {
					return true;
				}
			],

			[
				'Strips declined inline images',
				// expected html after filtering
				'<p> and other stuff</p>',
				// input html
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a></figure-inline> and other stuff</p>',
				// accept/decline callback
				function () {
					return true;
				}
			],

			[
				'Strips declined inline images with percent in name',
				// expected html after filtering
				'<p> and other stuff</p>',
				// input html
				'<p><figure-inline class="mw-default-size" typeof="mw:Image"><a href="./File:100%25.jpg"><img resource="./File:100%25.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/100%25.jpg" height="500" width="500"></a></figure-inline> and other stuff</p>',
				// accept/decline callback
				function () {
					return true;
				}
			],

			[
				'Strips declined inline images (with legacy span markup)',
				// expected html after filtering
				'<p> and other stuff</p>',
				// input html
				'<p><span class="mw-default-size" typeof="mw:Image"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a></span> and other stuff</p>',
				// accept/decline callback
				function () {
					return true;
				}
			],

			[
				'Strips declined block images',
				// expected html after filtering
				'',
				// input html
				'<figure class="mw-default-size" typeof="mw:Image/Thumb"><a href="./File:Image.jpg"><img resource="./File:Image.jpg" src="//upload.wikimedia.org/wikipedia/commons/7/78/Image.jpg" height="500" width="500"></a><figcaption>Blah blah</figcaption></figure>',
				// accept/decline callback
				function () {
					return true;
				}
			],
		];
	}

	/**
	 * @dataProvider imageRemovalProvider
	 */
	public function testImageRemoval( $message, $expect, $content, $badImageFilter ) {
		$fixer = new ContentFixer( new BadImageRemover( $badImageFilter ) );
		$result = $fixer->apply( $content, Title::newMainPage() );
		$this->assertEquals( $expect, $result, $message );
	}
}
