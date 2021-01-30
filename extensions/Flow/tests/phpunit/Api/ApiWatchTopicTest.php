<?php

namespace Flow\Tests\Api;

use MediaWiki\MediaWikiServices;
use Title;
use User;

/**
 * @covers Flow\Hooks
 *
 * @group Flow
 * @group medium
 * @group Database
 */
class ApiWatchTopicTest extends ApiTestCase {

	public function watchTopicProvider() {
		return [
			[
				'Watch a topic',
				// expected key in api result
				'watched',
				// initialization
				function ( User $user, Title $title ) {
					$store = MediaWikiServices::getInstance()->getWatchedItemStore();
					$store->removeWatch( $user, $title->getSubjectPage() );
					$store->removeWatch( $user, $title->getTalkPage() );
					$title->invalidateCache();
				},
				// extra request parameters
				[],
			],
			[
				'Unwatch a topic',
				// expected key in api result
				'unwatched',
				// initialization
				function ( User $user, Title $title ) {
					MediaWikiServices::getInstance()->getWatchedItemStore()->addWatchBatchForUser(
						$user,
						[ $title->getSubjectPage(), $title->getTalkPage() ]
					);
					$user->invalidateCache();
				},
				// extra request parameters
				[ 'unwatch' => 1 ],
			],
		];
	}

	/**
	 * @dataProvider watchTopicProvider
	 */
	public function testWatchTopic( $message, $expect, $init, array $request ) {
		$topic = $this->createTopic();

		$title = Title::newFromText( $topic['topic-page'] );
		$init( self::$users['sysop']->getUser(), $title );

		// issue a watch api request
		$data = $this->doApiRequest( $request + [
				'action' => 'watch',
				'format' => 'json',
				'titles' => $topic['topic-page'],
				'token' => $this->getEditToken( null, 'watchtoken' ),
		] );
		$this->assertArrayHasKey( $expect, $data[0]['watch'][0], $message );
	}
}
