<?php

namespace Flow\Tests;

use Flow\TemplateHelper;
use LightnCandy\LightnCandy;
use MediaWikiUnitTestCase;

/**
 * @covers \Flow\TemplateHelper
 *
 * @group Flow
 */
class TemplateHelperTest extends MediaWikiUnitTestCase {

	public function provideTraversalAttackFilenames() {
		return array_map(
			function ( $x ) {
				return [ $x ];
			},
			[
				'.',
				'..',
				'./foo',
				'../foo',
				'foo/./bar',
				'foo/../bar',
				'foo/bar/.',
				'foo/bar/..',
			]
		);
	}

	/**
	 * @dataProvider provideTraversalAttackFilenames
	 */
	public function testGetTemplateFilenamesTraversalAttack( $templateName ) {
		$helper = new TemplateHelper( '/does/not/exist' );
		$this->expectException( \Flow\Exception\FlowException::class );
		$helper->getTemplateFilenames( $templateName );
	}

	public function testIfCond() {
		$code = TemplateHelper::compile( "{{#ifCond foo \"or\" bar}}Works{{/ifCond}}", '' );
		$renderer = LightnCandy::prepare( $code );

		$this->assertEquals( 'Works', $renderer( [ 'foo' => true, 'bar' => false ] ) );
		$this->assertSame( '', $renderer( [ 'foo' => false, 'bar' => false ] ) );
		/*
		FIXME: Why won't this work!?
		$code2 = TemplateHelper::compile( "{{#ifCond foo \"===\" bar}}Works{{/ifCond}}", '' );
		$renderer2 = Lightncandy::prepare( $code2 );
		$this->assertEquals( 'Works', $renderer2( array( 'foo' => 1, 'bar' => 1 ) ) );
		$this->assertSame( '', $renderer2( array( 'foo' => 2, 'bar' => 3 ) ) );*/
	}
}
