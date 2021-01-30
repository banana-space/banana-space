<?php

namespace Flow\Tests\Api;

use Flow\Model\AbstractRevision;

/**
 * @covers \Flow\Api\ApiFlowBase
 * @covers \Flow\Api\ApiFlowBasePost
 * @covers \Flow\Api\ApiFlowModerateTopic
 *
 * @group Flow
 * @group medium
 * @group Database
 */
class ApiFlowModerateTopicTest extends ApiTestCase {
	protected $tablesUsed = [
		'flow_ext_ref',
		'flow_revision',
		'flow_topic_list',
		'flow_tree_node',
		'flow_tree_revision',
		'flow_wiki_ref',
		'flow_workflow',
		'page',
		'revision',
		'ip_changes',
		'text',
		'logging',
	];

	/**
	 * @group Broken
	 */
	public function testModerateTopic() {
		$topic = $this->createTopic();

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'moderate-topic',
			'mtmoderationState' => AbstractRevision::MODERATED_DELETED,
			'mtreason' => '<>&{};'
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['moderate-topic']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['moderate-topic']['committed'], $debug );

		$revisionId = $data[0]['flow']['moderate-topic']['committed']['topic']['post-revision-id'];

		// need to reset the container and set the current user here
		$this->setCurrentUser( self::$users['sysop']->getUser() );
		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'action' => 'flow',
			'submodule' => 'view-topic',
			'vtformat' => 'html',
		] );

		$debug = json_encode( $data );
		$revision = $data[0]['flow']['view-topic']['result']['topic']['revisions'][$revisionId];
		$this->assertArrayHasKey( 'changeType', $revision, $debug );
		$this->assertEquals( 'delete-topic', $revision['changeType'], $debug );
		$this->assertArrayHasKey( 'isModerated', $revision, $debug );
		$this->assertTrue( $revision['isModerated'], $debug );
		$this->assertArrayHasKey( 'actions', $revision, $debug );
		$this->assertArrayHasKey( 'undelete', $revision['actions'], $debug );
		$this->assertArrayHasKey( 'moderateState', $revision, $debug );
		$this->assertEquals( AbstractRevision::MODERATED_DELETED, $revision['moderateState'], $debug );
		$this->assertArrayHasKey( 'moderateReason', $revision, $debug );
		$this->assertArrayHasKey( 'content', $revision['moderateReason'], $debug );
		$this->assertEquals( '<>&{};', $revision['moderateReason']['content'], $debug );
		$this->assertArrayHasKey( 'format', $revision['moderateReason'], $debug );
		$this->assertEquals( 'plaintext', $revision['moderateReason']['format'], $debug );

		// make sure our moderated topic made it into Special:Log
		$data = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'logevents',
			'rawcontinue' => 1,
		] );
		$debug = json_encode( $data );
		$logEntry = $data[0]['query']['logevents'][0];
		$logParams = isset( $logEntry['params'] ) ? $logEntry['params'] : $logEntry;
		$this->assertArrayHasKey( 'topicId', $logParams, $debug );
		$this->assertEquals( $topic['topic-id'], $logParams['topicId'], $debug );
	}

	/**
	 * @throws \ApiUsageException
	 * @group Broken
	 */
	public function testModerateLockedTopic() {
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
			'submodule' => 'moderate-topic',
			'mtmoderationState' => AbstractRevision::MODERATED_DELETED,
			'mtreason' => '<>&{};'
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['moderate-topic']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['moderate-topic']['committed'], $debug );
	}
}
