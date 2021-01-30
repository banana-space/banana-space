<?php

namespace Flow\Tests\Api;

use Sanitizer;

/**
 * @covers \Flow\Api\ApiFlowBase
 * @covers \Flow\Api\ApiFlowBasePost
 * @covers \Flow\Api\ApiFlowEditTopicSummary
 *
 * @group Flow
 * @group medium
 * @group Database
 */
class ApiFlowEditTopicSummaryTest extends ApiTestCase {
	public function testEditTopicSummary() {
		$summaryText = '( ●_●)-((⌼===((() ≍≍≍≍≍ ♒ ✺ ♒ ZAP!';

		$topic = $this->createTopic();

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'edit-topic-summary',
			'etsprev_revision' => '',
			'etssummary' => $summaryText,
			'etsformat' => 'wikitext',
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['edit-topic-summary']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['edit-topic-summary']['committed'], $debug );

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'action' => 'flow',
			'submodule' => 'view-topic-summary',
			'vtsformat' => 'html',
		] );

		$debug = json_encode( $data );
		$revision = $data[0]['flow']['view-topic-summary']['result']['topicsummary']['revision'];
		$this->assertArrayHasKey( 'changeType', $revision, $debug );
		$this->assertEquals( 'create-topic-summary', $revision['changeType'], $debug );
		$this->assertEquals(
			$summaryText,
			trim( Sanitizer::stripAllTags( $revision['content']['content'] ) ),
			$debug
		);
		$this->assertEquals( 'html', $revision['content']['format'], $debug );

		$data = $this->doApiRequest( [
			'page' => $topic['topic-page'],
			'action' => 'flow',
			'submodule' => 'view-topic',
		] );

		$topicData = $data[0]['flow']['view-topic']['result']['topic'];
		$rootPostId = $topicData['roots'][0];
		$topicRevisionId = $topicData['posts'][$rootPostId][0];
		$topicRevision = $topicData['revisions'][$topicRevisionId];

		$this->assertEquals(
			$summaryText,
			trim( Sanitizer::stripAllTags( $topicRevision['summary']['revision']['content']['content'] ) ),
			'Summary content present with correct structure in view-topic response'
		);
	}
}
