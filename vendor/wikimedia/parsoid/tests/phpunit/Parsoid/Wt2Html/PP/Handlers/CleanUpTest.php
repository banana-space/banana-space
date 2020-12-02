<?php

namespace Test\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Mocks\MockDataAccess;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMTraverser;

/**
 * Test CleanUp, the tests used for validating CleanUp class port from JS to PHP
 * and based on tests/mocha/cleanup.js
 * @coversDefaultClass \Wikimedia\Parsoid\Wt2Html\PP\Handlers\CleanUp
 */
class CleanUpTest extends TestCase {

	/**
	 * @param Env $env
	 * @param string $wt
	 * @return DOMElement
	 */
	private function parseWT( Env $env, string $wt ): DOMElement {
		$siteConfig = new MockSiteConfig( [] );
		$dataAccess = new MockDataAccess( [] );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$content = new MockPageContent( [ 'main' => $wt ] );
		$pageConfig = new MockPageConfig( [], $content );
		$html = $parsoid->wikitext2html( $pageConfig, [ "wrapSections" => false ] );

		$doc = ContentUtils::ppToDOM( $env, $html );

		return( $doc );
	}

	/**
	 * @param DOMTraverser $domVisitor
	 * @param array $tags
	 * @param bool $value
	 */
	private function addHandlers( DOMTraverser $domVisitor, array $tags, bool $value ) {
		foreach ( $tags as $tag ) {
			$domVisitor->addHandler( $tag,
				function ( ...$args ) use ( $value ) {
					return $this->autoInsValidation( $value, ...$args );
				}
			);
		}
	}

	/**
	 * @param bool $expectedValue
	 * @param DOMElement $node
	 * @return bool
	 */
	private function autoInsValidation( bool $expectedValue, DOMElement $node ): bool {
		$dp = DOMDataUtils::getDataParsoid( $node );
		$autoInsEnd = isset( $dp->autoInsertedEnd );
		$this->assertEquals( $expectedValue,  $autoInsEnd );
		return true;
	}

	/**
	 * test searching for autoInsertedEnd flags using DOM traversal helper functions
	 * @covers ::cleanupAndSaveDataParsoid
	 * @dataProvider provideCleanUp
	 * @param string $test
	 */
	public function testCleanUp( string $test ): void {
		error_log( "Cleanup DOM pass should confirm removal of autoInsertedEnd flag\n" .
			"for wikitext table tags without closing tag syntax using DOM traversal\n" );
		$mockEnv = new MockEnv( [] );
		$doc = $this->parseWT( $mockEnv, $test );
		$fragment = $doc->firstChild;

		$domVisitor = new DOMTraverser();
		$tags = [ 'tr', 'td', ];
		$this->addHandlers( $domVisitor, $tags, false );

		$domVisitor->traverse( $mockEnv, $fragment );
	}

	/**
	 * @return array
	 */
	public function provideCleanUp(): array {
		$test = [
			"{|",
			"|a",
			"|}"
		];
		return [ [ implode( "\n", $test ) ] ];
	}

	/**
	 * test for autoInsertedEnd flags in all possible WT tags with no closing tags
	 * "PRE", "LI", "DT", "DD", "HR", "TR", "TD", "TH", "CAPTION"
	 * @covers ::cleanupAndSaveDataParsoid
	 * @dataProvider provideCleanUpWT
	 * @param string $test
	 */
	public function testCleanUpWT( string $test ): void {
		error_log( "Cleanup DOM pass should confirm removal of autoInsertedEnd flag\n" .
			"for all wikitext tags without closing tags\n" );
		$mockEnv = new MockEnv( [] );
		$doc = $this->parseWT( $mockEnv, $test );
		$table = $doc->firstChild;

		$domVisitor = new DOMTraverser();
		$tags = [ 'pre', 'li', 'dt', 'dd', 'hr', 'tr', 'td', 'th', 'caption' ];
		$this->addHandlers( $domVisitor, $tags, false );

		$domVisitor->traverse( $mockEnv, $table );
	}

	/**
	 * @return array
	 */
	public function provideCleanUpWT(): array {
		$test = [
			";Definition list",
			":First definition",
			":Second definition",
			"{|",
			"|+ caption",
			"|-",
			"! heading 1!! heading 2",
			"|-",
			"|a||b",
			"|}",
			" preformatted text using leading whitespace as a pre wikitext symbol equivalent",
			"{|",
			"|c",
			"|}",
			"# Item 1",
			"# Item 2",
			];
		return [ [ implode( "\n", $test ) ] ];
	}

	/**
	 * test for autoInsertedEnd flags in all possible HTML wikitext tags with no closing tags
	 * "PRE", "LI", "DT", "DD", "HR", "TR", "TD", "TH", "CAPTION"
	 * @covers ::cleanupAndSaveDataParsoid
	 * @dataProvider provideCleanUpHTML
	 * @param string $test
	 */
	public function testCleanUpHTML( string $test ): void {
		error_log( "Cleanup DOM pass should confirm presence of autoInsertedEnd flag\n" .
			"for all HTML wikitext tags that can appear without closing tags\n" );
		$mockEnv = new MockEnv( [] );
		$doc = $this->parseWT( $mockEnv, $test );
		$fragment = $doc->firstChild;

		$domVisitor = new DOMTraverser();
		$tags = [ 'pre', 'li', 'dt', 'dd', 'hr', 'tr', 'td', 'th', 'caption' ];
		$this->addHandlers( $domVisitor, $tags, true );

		$domVisitor->traverse( $mockEnv, $fragment );
	}

	/**
	 * @return array
	 */
	public function provideCleanUpHTML(): array {
		$test = [
			"<dl>",
			"<dt>Definition list",
			"<dd>First definition",
			"<dd>Second definition",
			"</dl>",
			"<table>",
			"<caption>caption",
			"<tr>",
			"<th>heading 1",
			"<th>heading 2",
			"<tr>",
			"<td>a",
			"<td>b",
			"</table>",
			"<pre>preformatted text using leading whitespace as a pre wikitext symbol equivalent",
			"<ol>",
			"<li>Item 1",
			"<li>Item 2",
			"</ol>",
		];
		return [ [ implode( "\n", $test ) ] ];
	}

}
