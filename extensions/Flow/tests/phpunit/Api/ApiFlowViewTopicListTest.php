<?php

namespace Flow\Tests\Api;

use Flow\Model\UUID;
use Title;

/**
 * @covers \Flow\Api\ApiFlowBase
 * @covers \Flow\Api\ApiFlowBaseGet
 * @covers \Flow\Api\ApiFlowViewTopicList
 *
 * @group Flow
 * @group medium
 * @group Database
 */
class ApiFlowViewTopicListTest extends ApiTestCase {
	private const TITLE_PREFIX = 'VTL Test ';

	/**
	 * Flaky test causing random failures, see T209535
	 *
	 * @group Broken
	 */
	public function testTocOnly() {
		$topicData = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$title = self::TITLE_PREFIX . $i;
			$topic = $this->createTopic( $title );
			$data = $this->doApiRequest( [
				'page' => $topic['topic-page'],
				'action' => 'flow',
				'submodule' => 'view-topic',
			] );

			$topicData[$i]['response'] = $data[0]['flow']['view-topic']['result']['topic'];
			$topicData[$i]['page'] = $topic['topic-page'];
			$topicData[$i]['id'] = $topic['topic-id'];
			$topicData[$i]['revisionId'] = $topic['topic-revision-id'];
			$actualRevision = $topicData[$i]['response']['revisions'][$topicData[$i]['revisionId']];
			$topicData[$i]['expectedRevision'] = [
				'content' => [
					'content' => $title,
					'format' => 'topic-title-wikitext',
					'plaintext' => $title,
				],
				// This last_updated is used for the 'newest' test, then later changed for 'updated' test.
				'last_updated' => $actualRevision['last_updated'],
				'isModerated' => false,
			];
		}

		$flowQaTitle = Title::newFromText( 'Talk:Flow_QA' );

		$expectedCommonResponse = [
			'flow' => [
				'view-topiclist' => [
					'result' => [
						'topiclist' => [
							'submitted' => [
								'savesortby' => false,
								'offset-dir' => 'fwd',
								'offset-id' => null,
								'offset' => null,
								'limit' => 2,
								'toconly' => true,
								'include-offset' => false,
								'format' => 'fixed-html',
							],
							'errors' => [],
							'type' => 'topiclist',
						],
					],
					'status' => 'ok',
				],
			],
		];

		$expectedEmptyPageResponse = array_merge_recursive( [
			'flow' => [
				'view-topiclist' => [
					'result' => [
						'topiclist' => [
							'submitted' => [
								'sortby' => 'newest',
							],
							'sortby' => 'newest',
							'roots' => [],
							'posts' => [],
							'revisions' => [],
							'links' => [
								'pagination' => [],
							],
						],
					],
				],
			],
		], $expectedCommonResponse );

		$actualEmptyPageResponse = $this->doApiRequest(
			[
				'action' => 'flow',
				'page' => 'Talk:Intentionally blank',
				'submodule' => 'view-topiclist',
				'vtllimit' => 2,
				'vtlsortby' => 'newest',
				'vtltoconly' => true,
				'vtlformat' => 'fixed-html',
			]
		);
		$actualEmptyPageResponse = $actualEmptyPageResponse[0];

		$this->assertEquals(
			$expectedEmptyPageResponse,
			$actualEmptyPageResponse,
			'TOC-only output for an empty, but occupied, Flow board'
		);

		$expectedNewestResponse = array_merge_recursive( [
			'flow' => [
				'view-topiclist' => [
					'result' => [
						'topiclist' => [
							'submitted' => [
								'sortby' => 'newest',
							],
							'sortby' => 'newest',
							'roots' => [
								$topicData[2]['id'],
								$topicData[1]['id'],
							],
							'posts' => [
								$topicData[2]['id'] => $topicData[2]['response']['posts'][$topicData[2]['id']],
								$topicData[1]['id'] => $topicData[1]['response']['posts'][$topicData[1]['id']],
							],
							'revisions' => [
								$topicData[2]['revisionId'] => $topicData[2]['expectedRevision'],
								$topicData[1]['revisionId'] => $topicData[1]['expectedRevision'],
							],
							'links' => [
								'pagination' => [
									'fwd' => [
										'url' => $flowQaTitle->getLinkURL( [
											'topiclist_offset-dir' => 'fwd',
											'topiclist_limit' => '2',
											'topiclist_offset-id' => $topicData[1]['id'],
											'topiclist_sortby' => 'newest',
										] ),
										'title' => 'fwd',
										'text' => 'fwd',
									],
								],
							],
						],
					]
				]
			]
		], $expectedCommonResponse );

		$actualNewestResponse = $this->doApiRequest(
			[
				'action' => 'flow',
				'page' => 'Talk:Flow QA',
				'submodule' => 'view-topiclist',
				'vtllimit' => 2,
				'vtlsortby' => 'newest',
				'vtltoconly' => true,
				'vtlformat' => 'fixed-html',
			]
		);
		$actualNewestResponse = $actualNewestResponse[0];

		$this->assertEquals(
			$expectedNewestResponse,
			$actualNewestResponse,
			'TOC-only output for "newest" order'
		);

		// Make it so update order is chronologically (1, 0, 2)
		// We then expect it to be returned reverse chronologically (2, 0)

		$updateList = [ 1, 0, 2 ];

		foreach ( $updateList as $updateListInd => $topicDataInd ) {
			$replyResponse = $this->doApiRequest(
				[
					'action' => 'flow',
					'page' => $topicData[$topicDataInd]['page'],
					'submodule' => 'reply',
					'token' => $this->getEditToken(),
					'repreplyTo' => $topicData[$topicDataInd]['id'],
					'repcontent' => "Reply to topic $topicDataInd",
				]
			);

			// This is because we use timestamps with second granularity.
			// Without this, the timestamp can be exactly the same
			// for two topics, which means the ordering is undefined (and thus
			// untestable).  This was causing failures on Jenkins.
			// Possible improvement: Make a simple class for getting the current
			// time that normally calls wfTimestampNow.  Have an alternative
			// implementation for tests that can be controlled by an API like
			// http://sinonjs.org/ (which we use on the client side).
			// Pimple can be in charge of which is used.
			if ( $updateListInd !== ( count( $updateList ) - 1 ) ) {
				sleep( 1 );
			}

			$newPostId = $replyResponse[0]['flow']['reply']['committed']['topic']['post-id'];
			$topicData[$topicDataInd]['updateTimestamp'] = UUID::create( $newPostId )->getTimestamp();
			$topicData[$topicDataInd]['expectedRevision']['last_updated'] =
				wfTimestamp( TS_UNIX, $topicData[$topicDataInd]['updateTimestamp'] ) * 1000;
		}

		$expectedUpdatedResponse = array_merge_recursive( [
			'flow' => [
				'view-topiclist' => [
					'result' => [
						'topiclist' => [
							'submitted' => [
								'sortby' => 'updated',
							],
							'sortby' => 'updated',
							'roots' => [
								$topicData[2]['id'],
								$topicData[0]['id'],
							],
							'posts' => [
								$topicData[2]['id'] => $topicData[2]['response']['posts'][$topicData[2]['id']],
								$topicData[0]['id'] => $topicData[0]['response']['posts'][$topicData[0]['id']],
							],
							'revisions' => [
								$topicData[2]['revisionId'] => $topicData[2]['expectedRevision'],
								$topicData[0]['revisionId'] => $topicData[0]['expectedRevision'],
							],
							'links' => [
								'pagination' => [
									'fwd' => [
										'url' => $flowQaTitle->getLinkURL( [
											'topiclist_offset-dir' => 'fwd',
											'topiclist_limit' => '2',
											'topiclist_offset' => $topicData[0]['updateTimestamp'],
											'topiclist_sortby' => 'updated',
										] ),
										'title' => 'fwd',
										'text' => 'fwd',
									],
								],
							],
						],
					]
				]
			]
		], $expectedCommonResponse );

		$actualUpdatedResponse = $this->doApiRequest(
			[
				'action' => 'flow',
				'page' => 'Talk:Flow QA',
				'submodule' => 'view-topiclist',
				'vtllimit' => 2,
				'vtlsortby' => 'updated',
				'vtltoconly' => true,
				'vtlformat' => 'fixed-html',
			]
		);
		$actualUpdatedResponse = $actualUpdatedResponse[0];

		$this->assertEquals(
			$expectedUpdatedResponse,
			$actualUpdatedResponse,
			'TOC-only output for "updated" order'
		);
	}
}
