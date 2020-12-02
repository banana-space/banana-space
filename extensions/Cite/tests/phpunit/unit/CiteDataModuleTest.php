<?php

namespace Cite\Tests\Unit;

use Cite\ResourceLoader\CiteDataModule;
use Message;
use ResourceLoaderContext;
use WebRequest;

/**
 * @covers \Cite\ResourceLoader\CiteDataModule
 *
 * @license GPL-2.0-or-later
 */
class CiteDataModuleTest extends \MediaWikiUnitTestCase {

	protected function setUp() : void {
		global $wgRequest;

		parent::setUp();
		$wgRequest = $this->createMock( WebRequest::class );
	}

	public function testGetScript() {
		$module = new CiteDataModule();
		$context = $this->createResourceLoaderContext();

		$this->assertSame(
			've.init.platform.addMessages({"cite-tool-definition.json":' .
				'"[{\"name\":\"n\",\"title\":\"t\"}]"});',
			$module->getScript( $context )
		);
	}

	public function testGetDependencies() {
		$module = new CiteDataModule();

		$this->assertContainsOnly( 'string', $module->getDependencies() );
	}

	public function testGetDefinitionSummary() {
		$module = new CiteDataModule();
		$context = $this->createResourceLoaderContext();

		$this->assertSame(
			$module->getScript( $context ),
			$module->getDefinitionSummary( $context )[0]['script']
		);
	}

	private function createResourceLoaderContext() : ResourceLoaderContext {
		$msg = $this->createMock( Message::class );
		$msg->method( 'inContentLanguage' )
			->willReturnSelf();
		$msg->method( 'plain' )
			->willReturnOnConsecutiveCalls( '', '[{"name":"n"}]', '[{"name":"n","title":"t"}]' );
		$msg->method( 'text' )
			->willReturn( 't' );

		$context = $this->createMock( ResourceLoaderContext::class );
		$context->method( 'msg' )
			->withConsecutive(
				[ 'cite-tool-definition.json' ],
				[ 'visualeditor-cite-tool-definition.json' ],
				[ 'visualeditor-cite-tool-name-n' ],
				[ 'cite-tool-definition.json' ]
			)
			->willReturn( $msg );
		return $context;
	}

}
