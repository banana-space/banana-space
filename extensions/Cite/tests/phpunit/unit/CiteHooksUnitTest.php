<?php

namespace Cite\Tests\Unit;

use Cite\Hooks\CiteHooks;
use ResourceLoader;
use Title;

/**
 * @coversDefaultClass \Cite\Hooks\CiteHooks
 *
 * @license GPL-2.0-or-later
 */
class CiteHooksUnitTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::onContentHandlerDefaultModelFor
	 */
	public function testOnContentHandlerDefaultModelFor() {
		$title = $this->createMock( Title::class );
		$title->method( 'inNamespace' )
			->willReturn( true );
		$title->method( 'getText' )
			->willReturn( 'Cite-tool-definition.json' );

		CiteHooks::onContentHandlerDefaultModelFor( $title, $model );

		$this->assertSame( CONTENT_MODEL_JSON, $model );
	}

	/**
	 * @covers ::onResourceLoaderRegisterModules
	 */
	public function testOnResourceLoaderRegisterModules() {
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->expects( $this->atLeastOnce() )
			->method( 'register' );

		CiteHooks::onResourceLoaderRegisterModules( $resourceLoader );
	}

}
