<?php

namespace Flow\Tests\Api;

use Flow\Model\AbstractRevision;

/**
 * @covers \Flow\Api\ApiFlowBase
 * @covers \Flow\Api\ApiFlowBasePost
 * @covers \Flow\Api\ApiFlowModeratePost
 *
 * @group Flow
 * @group medium
 * @group Database
 * @group Broken
 */
class ApiFlowModeratePostTest extends ApiTestCase {
	public function testModeratePost() {
		$topic = $this->createTopic();

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'moderate-post',
			'mpmoderationState' => AbstractRevision::MODERATED_HIDDEN,
			'mppostId' => $topic['post-id'],
			'mpreason' => '<>&{};'
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['moderate-post']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['moderate-post']['committed'], $debug );

		$postId = $data[0]['flow']['moderate-post']['committed']['topic']['post-id'];
		$revisionId = $data[0]['flow']['moderate-post']['committed']['topic']['post-revision-id'];

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'action' => 'flow',
			'submodule' => 'view-post',
			'vppostId' => $postId,
			'vpformat' => 'html',
		] );

		$debug = json_encode( $data );
		$revision = $data[0]['flow']['view-post']['result']['topic']['revisions'][$revisionId];
		$this->assertArrayHasKey( 'changeType', $revision, $debug );
		$this->assertEquals( 'hide-post', $revision['changeType'], $debug );
		$this->assertArrayHasKey( 'isModerated', $revision, $debug );
		$this->assertTrue( $revision['isModerated'], $debug );
		$this->assertArrayHasKey( 'actions', $revision, $debug );
		$this->assertArrayHasKey( 'unhide', $revision['actions'], $debug );
		$this->assertArrayHasKey( 'moderateState', $revision, $debug );
		$this->assertEquals( AbstractRevision::MODERATED_HIDDEN, $revision['moderateState'], $debug );
		$this->assertArrayHasKey( 'moderateReason', $revision, $debug );
		$this->assertArrayHasKey( 'content', $revision['moderateReason'], $debug );
		$this->assertEquals( '<>&{};', $revision['moderateReason']['content'], $debug );
		$this->assertArrayHasKey( 'format', $revision['moderateReason'], $debug );
		$this->assertEquals( 'plaintext', $revision['moderateReason']['format'], $debug );
	}

	public function testModeratePostInLockedTopic() {
		$topic = $this->createTopic();

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'lock-topic',
			'cotmoderationState' => AbstractRevision::MODERATED_LOCKED,
			'cotreason' => '<>&{};'
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['lock-topic']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['lock-topic']['committed'], $debug );

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'moderate-post',
			'mpmoderationState' => AbstractRevision::MODERATED_HIDDEN,
			'mppostId' => $topic['post-id'],
			'mpreason' => '<>&{};'
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['moderate-post']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['moderate-post']['committed'], $debug );
	}
}
