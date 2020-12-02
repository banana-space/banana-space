<?php

namespace Cite\Tests;

use Cite\ResourceLoader\CiteCSSFileModule;
use MediaWiki\MediaWikiServices;
use ResourceLoaderContext;

/**
 * @covers \Cite\ResourceLoader\CiteCSSFileModule
 *
 * @license GPL-2.0-or-later
 */
class CiteCSSFileModuleTest extends \MediaWikiIntegrationTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->setService(
			'ContentLanguage',
			MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'fa' )
		);
	}

	public function testModule() {
		$module = new CiteCSSFileModule( [], __DIR__ . '/../../modules' );
		$styles = $module->getStyleFiles( $this->createMock( ResourceLoaderContext::class ) );
		$this->assertSame( [ 'ext.cite.style.fa.css' ], $styles['all'] );
	}

}
