<?php

namespace Test\Parsoid\Html2Wt\DOMHandlers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Html2Wt\DOMHandlers\MetaHandler;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WikitextSerializer;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;

class MetaHandlerTest extends TestCase {
	/**
	 * FIXME: Copied from SerializerState php unit test.
	 * We should perhaps create a library of shared mocks.
	 *
	 * A WikitextSerializer mock, with some basic methods mocked.
	 * @param array $extraMethodsToMock
	 * @return WikitextSerializer|MockObject
	 */
	private function getBaseSerializerMock( $extraMethodsToMock = [] ) {
		$serializer = $this->getMockBuilder( WikitextSerializer::class )
			->disableOriginalConstructor()
			->setMethods( array_merge( [ 'buildSep', 'trace' ], $extraMethodsToMock ) )
			->getMock();
		$serializer->expects( $this->any() )
			->method( 'buildSep' )
			->willReturn( '' );
		$serializer->expects( $this->any() )
			->method( 'trace' )
			->willReturn( null );
		/** @var WikitextSerializer $serializer */
		return $serializer;
	}

	protected function processMeta( $env, $state, $html, $res ) {
		$doc = $env->createDocument( $html );
		$metaNode = DOMCompat::getBody( $doc )->firstChild;
		DOMDataUtils::visitAndLoadDataAttribs( $metaNode );

		$state->currLine->text = '';
		( new MetaHandler() )->handle( $metaNode, $state );
		$this->assertSame( $res, $state->currLine->text );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\DOMHandlers\MetaHandler::handle
	 */
	public function testHandle() {
		$env = new MockEnv( [] );
		$serializer = $this->getBaseSerializerMock();
		$serializer->env = $env;
		$state = new SerializerState( $serializer, [] );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$html = '<meta property="mw:PageProp/categorydefaultsort" content="1972-73 New York Knicks Season" data-parsoid=\'{"src":"{{DEFAULTSORT:1972-73 New York Knicks Season}}"}\'/>';
		$this->processMeta( $env, $state, $html, '{{DEFAULTSORT:1972-73 New York Knicks Season}}' );

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$html = '<meta property="mw:PageProp/categorydefaultsort" content="$1" data-parsoid=\'{"src":"{{DEFAULTSORT:$1}}"}\'/>';
		$this->processMeta( $env, $state, $html, '{{DEFAULTSORT:$1}}' );
	}
}
