<?php

namespace Flow\Tests;

use Flow\Model\PostRevision;
use Flow\Model\Workflow;
use Flow\Repository\UserNameBatch;
use Flow\Templating;
use MediaWikiTestCase;
use Title;
use User;

/**
 * @covers \Flow\Templating
 *
 * @group Flow
 */
class TemplatingTest extends MediaWikiTestCase {

	protected function mockTemplating() {
		$query = $this->createMock( \Flow\Repository\UserName\UserNameQuery::class );
		$usernames = new UserNameBatch( $query );
		$urlGenerator = $this->getMockBuilder( \Flow\UrlGenerator::class )
			->disableOriginalConstructor()
			->getMock();
		$output = $this->getMockBuilder( \OutputPage::class )
			->disableOriginalConstructor()
			->getMock();
		$fixer = $this->getMockBuilder( \Flow\Parsoid\ContentFixer::class )
			->disableOriginalConstructor()
			->getMock();
		$permissions = $this->getMockBuilder( \Flow\RevisionActionPermissions::class )
			->disableOriginalConstructor()
			->getMock();

		return new Templating( $usernames, $urlGenerator, $output, $fixer, $permissions );
	}

	/**
	 * There was a bug where all anonymous users got the same
	 * user links output, this checks that they are distinct.
	 */
	public function testNonRepeatingUserLinksForAnonymousUsers() {
		$templating = $this->mockTemplating();

		$user = User::newFromName( '127.0.0.1', false );
		$title = Title::newMainPage();
		$workflow = Workflow::create( 'topic', $title );
		$topicTitle = PostRevision::createTopicPost( $workflow, $user, 'some content' );

		$hidden = $topicTitle->moderate(
			$user,
			$topicTitle::MODERATED_HIDDEN,
			'hide-topic',
			'hide and go seek'
		);

		$this->assertStringContainsString(
			'Special:Contributions/127.0.0.1',
			$templating->getUserLinks( $hidden ),
			'User links should include anonymous contributions'
		);

		$hidden = $topicTitle->moderate(
			User::newFromName( '10.0.0.2', false ),
			$topicTitle::MODERATED_HIDDEN,
			'hide-topic',
			'hide and go seek'
		);
		$this->assertStringContainsString(
			'Special:Contributions/10.0.0.2',
			$templating->getUserLinks( $hidden ),
			'An alternate user should have the correct anonymous contributions'
		);
	}
}
