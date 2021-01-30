<?php

namespace Flow\Tests\Formatter;

use ExtensionRegistry;
use Flow\Container;
use Flow\Formatter\FormatterRow;
use Flow\Formatter\RevisionFormatter;
use Flow\Model\UUID;
use Flow\Tests\FlowTestCase;
use Flow\UrlGenerator;
use Title;

/**
 * @group Flow
 */
class FormatterTest extends FlowTestCase {

	public static function checkUserProvider() {
		$topicId = UUID::create();
		$revId = UUID::create();
		$postId = UUID::create();

		return [
			[
				'With only a topicId reply should not fail',
				// result must contain
				function ( $test, $message, $result ) {
					$test->assertNotNull( $result );
					$test->assertArrayHasKey( 'links', $result, $message );
				},
				// cuc_comment parameters
				'reply', $topicId, $revId, null
			],

			[
				'With topicId and postId should not fail',
				function ( $test, $message, $result ) {
					$test->assertNotNull( $result );
					$test->assertArrayHasKey( 'links', $result, $message );
				},
				'reply', $topicId, $revId, $postId,
			],
		];
	}

	/**
	 * @covers \Flow\Formatter\CheckUserFormatter
	 * @dataProvider checkUserProvider
	 * @group Broken
	 */
	public function testCheckUserFormatter( $message, $test, $action, UUID $workflowId, UUID $revId, UUID $postId = null ) {
		global $wgLang;

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			$this->markTestSkipped( 'CheckUser is not available' );
			return;
		}

		$title = Title::newFromText( 'Test', NS_USER_TALK );
		$row = new FormatterRow;
		$row->workflow = $this->mockWorkflow( $workflowId, $title );
		$row->revision = $this->mockRevision( $action, $revId, $postId );
		$row->currentRevision = $row->revision;

		$ctx = $this->createMock( \IContextSource::class );
		$ctx->expects( $this->any() )
			->method( 'getLanguage' )
			->will( $this->returnValue( $wgLang ) );
		$ctx->expects( $this->any() )
			->method( 'msg' )
			->will( $this->returnCallback( 'wfMessage' ) );

		// Code uses wfWarn as a louder wfDebugLog in error conditions.
		// but phpunit considers a warning a fail.
		\Wikimedia\suppressWarnings();
		$links = $this->createFormatter( \Flow\Formatter\CheckUserFormatter::class )->format( $row, $ctx );
		\Wikimedia\restoreWarnings();
		$test( $this, $message, $links );
	}

	protected function mockWorkflow( UUID $workflowId, Title $title ) {
		$workflow = $this->createMock( \Flow\Model\Workflow::class );
		$workflow->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( $workflowId ) );
		$workflow->expects( $this->any() )
			->method( 'getArticleTitle' )
			->will( $this->returnValue( $title ) );
		return $workflow;
	}

	protected function mockRevision( $changeType, UUID $revId, UUID $postId = null ) {
		if ( $postId ) {
			$revision = $this->createMock( \Flow\Model\PostRevision::class );
		} else {
			$revision = $this->createMock( \Flow\Model\Header::class );
		}
		$revision->expects( $this->any() )
			->method( 'getChangeType' )
			->will( $this->returnValue( $changeType ) );
		$revision->expects( $this->any() )
			->method( 'getRevisionId' )
			->will( $this->returnValue( $revId ) );
		if ( $postId ) {
			$revision->expects( $this->any() )
				->method( 'getPostId' )
				->will( $this->returnValue( $postId ) );
		}
		return $revision;
	}

	protected function createFormatter( $class ) {
		$permissions = $this->getMockBuilder( \Flow\RevisionActionPermissions::class )
			->disableOriginalConstructor()
			->getMock();
		$permissions->expects( $this->any() )
			->method( 'isAllowed' )
			->will( $this->returnValue( true ) );
		$permissions->expects( $this->any() )
			->method( 'getActions' )
			->will( $this->returnValue( Container::get( 'flow_actions' ) ) );

		$templating = $this->getMockBuilder( \Flow\Templating::class )
			->disableOriginalConstructor()
			->getMock();
		$workflowMapper = $this->getMockBuilder( \Flow\Data\Mapper\CachingObjectMapper::class )
			->disableOriginalConstructor()
			->getMock();
		$urlGenerator = new UrlGenerator( $workflowMapper );
		$templating->expects( $this->any() )
			->method( 'getUrlGenerator' )
			->will( $this->returnValue( $urlGenerator ) );

		$usernames = $this->getMockBuilder( \Flow\Repository\UserNameBatch::class )
			->disableOriginalConstructor()
			->getMock();

		global $wgFlowMaxThreadingDepth;
		$serializer = new RevisionFormatter( $permissions, $templating, $usernames, $wgFlowMaxThreadingDepth );

		return new $class( $permissions, $serializer );
	}

	protected function dataToString( $data ) {
		foreach ( $data as $key => $value ) {
			if ( $value instanceof UUID ) {
				$data[$key] = "UUID: " . $value->getAlphadecimal();
			}
		}
		return parent::dataToString( $data );
	}
}
