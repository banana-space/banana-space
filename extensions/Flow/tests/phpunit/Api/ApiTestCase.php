<?php

namespace Flow\Tests\Api;

use Flow\Container;
use Flow\Hooks;
use HashConfig;
use MediaWiki\MediaWikiServices;
use User;

/**
 * @group Flow
 * @group medium
 */
abstract class ApiTestCase extends \ApiTestCase {
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
	];

	protected function setUp() : void {
		parent::setUp();

		$namespaceContentModels = [
			NS_TALK => CONTENT_MODEL_FLOW_BOARD,
			NS_TOPIC => CONTENT_MODEL_FLOW_BOARD,
		];

		// TODO: remove this once core no longer accesses wgNamespaceContentModels directly.
		$this->setMwGlobals( 'wgNamespaceContentModels', $namespaceContentModels );

		$this->overrideMwServices( new HashConfig( [
			'NamespaceContentModels' => $namespaceContentModels
		] ) );

		$this->setCurrentUser( self::$users['sysop']->getUser() );
	}

	protected function getEditToken( $user = null, $token = 'edittoken' ) {
		$tokens = $this->getTokenList( $user ?: self::$users['sysop'] );
		return $tokens[$token];
	}

	/**
	 * Set $user in the Flow container
	 * WARNING: This resets your container and
	 *          gets rid of anything you may have mocked.
	 * @param User $user
	 */
	protected function setCurrentUser( User $user ) {
		Container::reset();
		$container = Container::getContainer();
		$container['user'] = $user;
	}

	protected function doApiRequest(
		array $params,
		array $session = null,
		$appendModule = false,
		User $user = null, $tokenType = null
	) {
		// reset flow state before each request
		Hooks::resetFlowExtension();
		return parent::doApiRequest( $params, $session, $appendModule, $user );
	}

	/**
	 * Create a topic on a board using the default user
	 * @param string $topicTitle
	 * @return array
	 */
	protected function createTopic( $topicTitle = 'Hi there!' ) {
		$data = $this->doApiRequest( [
			'page' => 'Talk:Flow QA',
			'token' => $this->getEditToken(),
			'action' => 'flow',
			'submodule' => 'new-topic',
			'nttopic' => $topicTitle,
			'ntcontent' => '...',
		] );

		$this->assertTrue(
			isset( $data[0]['flow']['new-topic']['committed']['topiclist']['topic-id'] ),
			'Api response must contain new topic id'
		);

		return $data[0]['flow']['new-topic']['committed']['topiclist'];
	}

	protected function expectCacheInvalidate() {
		$mock = $this->mockCache();
		$mock->expects( $this->never() )->method( 'set' );
		$mock->expects( $this->atLeastOnce() )->method( 'delete' );
		return $mock;
	}

	protected function mockCache() {
		global $wgFlowCacheTime;
		Container::reset();
		$container = Container::getContainer();
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		$mock = $this->getMockBuilder( \Flow\Data\FlowObjectCache::class )
			->setConstructorArgs( [ $wanCache, $container['db.factory'], $wgFlowCacheTime ] )
			->enableProxyingToOriginalMethods()
			->getMock();

		$container->extend( 'flowcache', function () use ( $mock ) {
			return $mock;
		} );

		return $mock;
	}
}
