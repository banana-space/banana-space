<?php

namespace Cite\Tests;

use ApiQuerySiteinfo;
use Cite\Hooks\CiteHooks;

/**
 * @coversDefaultClass \Cite\Hooks\CiteHooks
 *
 * @license GPL-2.0-or-later
 */
class CiteHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers ::onResourceLoaderGetConfigVars
	 */
	public function testOnResourceLoaderGetConfigVars() {
		$vars = [];

		CiteHooks::onResourceLoaderGetConfigVars( $vars );

		$this->assertArrayHasKey( 'wgCiteVisualEditorOtherGroup', $vars );
		$this->assertArrayHasKey( 'wgCiteResponsiveReferences', $vars );
	}

	/**
	 * @covers ::onAPIQuerySiteInfoGeneralInfo
	 */
	public function testOnAPIQuerySiteInfoGeneralInfo() {
		$api = $this->createMock( ApiQuerySiteinfo::class );
		$data = [];

		CiteHooks::onAPIQuerySiteInfoGeneralInfo( $api, $data );

		$this->assertArrayHasKey( 'citeresponsivereferences', $data );
	}

}
