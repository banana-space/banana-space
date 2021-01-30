<?php

namespace Flow\Tests\Block;

use Flow\Block\TopicListBlock;
use Flow\Container;
use Flow\Hooks;
use Flow\Model\Workflow;
use Title;
use User;

/**
 * @covers \Flow\Block\TopicListBlock
 */
class TopicListBlockTest extends \MediaWikiTestCase {

	public function testSortByOption() {
		$user = User::newFromId( 1 );
		$user->setOption( 'flow-topiclist-sortby', '' );

		// reset flow state, so everything ($container['permissions'])
		// uses this particular $user
		Hooks::resetFlowExtension();
		Container::reset();
		$container = Container::getContainer();
		$container['user'] = $user;

		$ctx = $this->createMock( \IContextSource::class );
		$ctx->expects( $this->any() )
			->method( 'getUser' )
			->will( $this->returnValue( $user ) );

		$workflow = Workflow::create( 'discussion', Title::newFromText( 'Talk:Flow_QA' ) );
		$block = new TopicListBlock( $workflow, Container::get( 'storage' ) );
		$block->init( $ctx, 'view' );

		$res = $block->renderApi( [
		] );
		$this->assertEquals( 'newest', $res['sortby'], 'With no sortby defaults to newest' );

		$res = $block->renderApi( [
			'sortby' => 'foo',
		] );
		$this->assertEquals( 'newest', $res['sortby'], 'With invalid sortby defaults to newest' );

		$res = $block->renderApi( [
			'sortby' => 'updated',
		] );
		$this->assertEquals( 'updated', $res['sortby'], 'With sortby updated output changes to updated' );
		$res = $block->renderApi( [
		] );
		$this->assertEquals( 'newest', $res['sortby'], 'Sort still defaults to newest' );

		$res = $block->renderApi( [
			'sortby' => 'updated',
			'savesortby' => '1',
		] );
		$this->assertEquals( 'updated', $res['sortby'], 'Request saving sortby option' );

		$res = $block->renderApi( [
		] );
		$this->assertEquals( 'updated', $res['sortby'], 'Default sortby now changed to updated' );

		$res = $block->renderApi( [
			'sortby' => '',
		] );
		$this->assertEquals( 'updated', $res['sortby'], 'Default sortby with blank sortby still uses user default' );
	}
}
