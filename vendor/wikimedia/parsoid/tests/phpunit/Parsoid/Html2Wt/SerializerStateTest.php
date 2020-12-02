<?php

namespace Test\Parsoid\Html2Wt;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WikitextSerializer;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Zest\Zest;

class SerializerStateTest extends TestCase {

	/**
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
		$serializer->env = new MockEnv( [] );
		return $serializer;
	}

	private function getState(
		array $options = [], MockEnv $env = null, WikitextSerializer $serializer = null
	) {
		if ( !$env ) {
			$env = new MockEnv( [] );
		}
		if ( !$serializer ) {
			$serializer = new WikitextSerializer( [ 'env' => $env ] );
		}
		return new SerializerState( $serializer, $options );
	}

	/**
	 * Create a DOM document with the given HTML body and return the given node within it.
	 * @param string $html
	 * @param string $selector
	 * @return DOMElement
	 */
	private function getNode( $html = '<div id="main"></div>', $selector = '#main' ) {
		$document = new DOMDocument();
		$document->loadHTML( "<html><body>$html</body></html>" );
		return Zest::find( $selector, $document )[0];
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::__construct
	 */
	public function testConstruct() {
		$state = $this->getState();
		$this->assertTrue( $state->rtTestMode );
		$this->assertSame( [], $state->currLine->chunks );

		$state = $this->getState( [ 'rtTestMode' => false ] );
		$this->assertFalse( $state->rtTestMode );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::initMode
	 */
	public function testInitMode() {
		$state = $this->getState();
		$state->initMode( true );
		$this->assertTrue( $state->selserMode );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::appendSep
	 */
	public function testAppendSep() {
		$state = $this->getState();
		$state->appendSep( 'foo' );
		$state->appendSep( 'bar' );
		$this->assertSame( 'foobar', $state->sep->src );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::updateSep
	 */
	public function testUpdateSep() {
		$state = $this->getState();
		$node = $this->getNode();
		$state->updateSep( $node );
		$this->assertSame( $node, $state->sep->lastSourceNode );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::getOrigSrc
	 */
	public function testGetOrigSrc() {
		$env = new MockEnv( [] );
		$selserData = new SelserData( '0123456789' );
		$state = $this->getState( [
			'selserData' => $selserData,
		], $env );
		$state->initMode( true );
		$this->assertSame( '23', $state->getOrigSrc( 2, 4 ) );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::updateModificationFlags
	 */
	public function testUpdateModificationFlags() {
		$state = $this->getState();
		$node = $this->getNode();
		$state->currNodeUnmodified = true;
		$state->updateModificationFlags( $node );
		$this->assertFalse( $state->currNodeUnmodified );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::emitChunk
	 */
	public function testEmitChunk() {
		$serializer = $this->getBaseSerializerMock();
		$serializer->env = new MockEnv( [] );
		$state = $this->getState( [], null, $serializer );
		$node = $this->getNode();
		$this->assertSame( '', $state->currLine->text );
		$state->emitChunk( 'foo', $node );
		$this->assertSame( 'foo', $state->currLine->text );
		$state->singleLineContext->enforce();
		$state->emitChunk( "\nfoo", $node );
		$this->assertSame( 'foo foo', $state->currLine->text );
		$state->singleLineContext->pop();
		$state->emitChunk( "\nfoo", $node );
		$this->assertSame( "foo foo\nfoo", $state->currLine->text );
		// FIXME this could be expanded a lot
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::serializeChildren
	 */
	public function testSerializeChildren() {
		$node = $this->getNode();
		$serializer = $this->getBaseSerializerMock( [ 'serializeNode' ] );
		$serializer->expects( $this->never() )
			->method( 'serializeNode' );
		$state = $this->getState( [], null, $serializer );
		$state->serializeChildren( $node );

		$node = $this->getNode( '<div id="main"><span></span><span></span></div>' );
		$serializer = $this->getBaseSerializerMock( [ 'serializeNode' ] );
		$serializer->expects( $this->exactly( 2 ) )
			->method( 'serializeNode' )
			->withConsecutive(
				[ $node->firstChild ],
				[ $node->firstChild->nextSibling ]
			)
			->willReturnCallback( function ( DOMElement $node ) {
				return $node->nextSibling;
			} );
		$state = $this->getState( [], null, $serializer );
		$state->serializeChildren( $node );

		$callback = function () {
		};
		$node = $this->getNode( '<div id="main"><span></span></div>' );
		$serializer = $this->getBaseSerializerMock( [ 'serializeNode' ] );
		$serializer->expects( $this->once() )
			->method( 'serializeNode' )
			->with( $node->firstChild )
			->willReturnCallback( function ( DOMElement $node ) use ( &$state, $callback ) {
				$this->assertSame( $callback, end( $state->wteHandlerStack ) );
				return $node->nextSibling;
			} );
		$state = $this->getState( [], null, $serializer );
		$this->assertEmpty( $state->wteHandlerStack );
		$state->serializeChildren( $node, $callback );
		$this->assertEmpty( $state->wteHandlerStack );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::kickOffSerialize
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::serializeLinkChildrenToString
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::serializeCaptionChildrenToString
	 * @covers \Wikimedia\Parsoid\Html2Wt\SerializerState::serializeIndentPreChildrenToString
	 * @dataProvider provideSerializeChildrenToString
	 */
	public function testSerializeChildrenToString( $method ) {
		$serializer = $this->getBaseSerializerMock( [ 'serializeNode' ] );
		$serializer->expects( $this->never() )
			->method( 'serializeNode' );
		$state = $this->getState( [], null, $serializer );
		$node = $this->getNode();
		$callback = function () {
		};
		$state->$method( $node, $callback );
	}

	public function provideSerializeChildrenToString() {
		return [
			[ 'kickOffSerialize' ],
			[ 'serializeLinkChildrenToString' ],
			[ 'serializeCaptionChildrenToString' ],
			[ 'serializeIndentPreChildrenToString' ],
		];
	}

}
