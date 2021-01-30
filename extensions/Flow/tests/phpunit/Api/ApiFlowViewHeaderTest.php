<?php

namespace Flow\Tests\Api;

use Sanitizer;

/**
 * @covers \Flow\Api\ApiFlowBase
 * @covers \Flow\Api\ApiFlowBaseGet
 * @covers \Flow\Api\ApiFlowViewHeader
 *
 * @group Flow
 * @group medium
 * @group Database
 */
class ApiFlowViewHeaderTest extends ApiTestCase {
	public function testViewEmptyHeader() {
		$data = $this->doApiRequest( [
			'page' => "Talk:Flow_QA",
			'action' => 'flow',
			'submodule' => 'view-header',
		] );

		$result = $data[0]['flow']['view-header']['result']['header'];
		$debug = json_encode( $result );
		$this->assertArrayHasKey( 'errors', $result, $debug );
		$this->assertEmpty( $result['errors'], $debug );

		// a revision key should exist with only an action link
		$this->assertArrayHasKey( 'revision', $result, $debug );
		$revision = $result['revision'];
		$this->assertEmpty( $revision['links'], $debug );
		$this->assertEquals( [ 'edit' ], array_keys( $revision['actions'] ), $debug );
		$this->assertArrayNotHasKey( 'content', $revision );
	}

	public function testViewHeader() {
		$data = $this->doApiRequest( [
			'page' => 'Talk:Flow_QA',
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'edit-header',
			'ehprev_revision' => '',
			'ehcontent' => 'swimmingly',
			'ehformat' => 'wikitext',
		] );

		$debug = json_encode( $data );
		$this->assertEquals( 'ok', $data[0]['flow']['edit-header']['status'], $debug );
		$this->assertCount( 1, $data[0]['flow']['edit-header']['committed'], $debug );

		$data = $this->doApiRequest( [
			'page' => "Talk:Flow_QA",
			'action' => 'flow',
			'submodule' => 'view-header',
			'vhformat' => 'html',
		] );
		$result = $data[0]['flow']['view-header']['result']['header'];
		$debug = json_encode( $result );
		$this->assertArrayHasKey( 'errors', $result, $debug );
		$this->assertEmpty( $result['errors'], $debug );
		$this->assertArrayHasKey( 'revision', $result );

		$revision = $result['revision'];
		$this->assertArrayHasKey( 'revisionId', $revision, $debug );
		$this->assertArrayHasKey( 'content', $revision, $debug );
		$this->assertArrayHasKey( 'content', $revision['content'], $debug );
		$this->assertEquals(
			'swimmingly',
			trim( Sanitizer::stripAllTags( $revision['content']['content'] ) ),
			$debug
		);
		$this->assertArrayHasKey( 'format', $revision['content'], $debug );
		$this->assertEquals( 'html', $revision['content']['format'], $debug );
	}

	/**
	 * @todo
	 *
	 * public function testViewHistorical() {
	 * }
	 */
}
