<?php

namespace Flow\Tests;

use Flow\Container;
use Flow\Model\Anchor;
use Flow\Model\UUID;
use Title;

/**
 * @covers \Flow\UrlGenerator
 *
 * @group Flow
 */
class UrlGeneratorTest extends FlowTestCase {

	protected $urlGenerator;

	protected function setUp() : void {
		parent::setUp();
		$this->urlGenerator = Container::get( 'url_generator' );
	}

	public function provideDataBoardLink() {
		return [
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				'updated',
				true
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				'updated',
				false
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				'created',
				true
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				'created',
				false
			]
		];
	}

	/**
	 * @dataProvider provideDataBoardLink
	 */
	public function testBoardLink( Title $title, $sortBy = null, $saveSortBy = false ) {
		$anchor = $this->urlGenerator->boardLink( $title, $sortBy, $saveSortBy );
		$this->assertInstanceOf( Anchor::class, $anchor );

		$link = $anchor->getFullURL();
		$option = wfParseUrl( $link );
		$this->assertArrayHasKey( 'query', $option );
		parse_str( $option['query'], $query );

		if ( $sortBy !== null ) {
			$this->assertEquals( $sortBy, $query['topiclist_sortby'] );
			if ( $saveSortBy ) {
				$this->assertSame( '1', $query['topiclist_savesortby'] );
			}
		}
	}

	public function provideDataWatchTopicLink() {
		return [
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				UUID::create()
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				UUID::create()
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				UUID::create()
			],
			[
				Title::makeTitle( NS_MAIN, 'Test' ),
				UUID::create()
			]
		];
	}

	/**
	 * @dataProvider provideDataWatchTopicLink
	 */
	public function testWatchTopicLink( Title $title, $workflowId ) {
		$anchor = $this->urlGenerator->watchTopicLink( $title, $workflowId );
		$this->assertInstanceOf( Anchor::class, $anchor );

		$link = $anchor->getFullURL();
		$option = wfParseUrl( $link );
		$this->assertArrayHasKey( 'query', $option );
		parse_str( $option['query'], $query );
		$this->assertEquals( 'watch', $query['action'] );
	}

	/**
	 * @dataProvider provideDataWatchTopicLink
	 */
	public function testUnwatchTopicLink( Title $title, $workflowId ) {
		$anchor = $this->urlGenerator->unwatchTopicLink( $title, $workflowId );
		$this->assertInstanceOf( Anchor::class, $anchor );

		$link = $anchor->getFullURL();
		$option = wfParseUrl( $link );
		$this->assertArrayHasKey( 'query', $option );
		parse_str( $option['query'], $query );
		$this->assertEquals( 'unwatch', $query['action'] );
	}
}
