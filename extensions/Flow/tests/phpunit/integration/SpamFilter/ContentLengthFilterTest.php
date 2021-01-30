<?php

namespace Flow\Tests\SpamFilter;

use Flow\Model\PostRevision;
use Flow\Model\Workflow;
use Flow\SpamFilter\ContentLengthFilter;
use Title;
use User;

/**
 * @covers \Flow\SpamFilter\ContentLengthFilter
 *
 * @group Flow
 */
class ContentLengthFilterTest extends \MediaWikiTestCase {

	public function filterValidationProvider() {
		yield 'With content shorter than max length allow through filter' => [
			'expected' => true,
			'content' => 'blah',
			'maxLength' => 100
		];
		yield 'With content longer than max length disallow through filter' => [
			'expected' => false,
			'content' => 'blah',
			'maxLength' => 2,
		];
	}

	/**
	 * @dataProvider filterValidationProvider
	 */
	public function testFilterValidation( $expected, $content, $maxLength ) {
		$ownerTitle = Title::newFromText( 'UTPage' );
		$title = Title::newFromText( 'Topic:Tnprd6ksfu1v1nme' );
		$user = User::newFromName( '127.0.0.1', false );
		$workflow = Workflow::create( 'topic', $title );
		$topic = PostRevision::createTopicPost( $workflow, $user, 'title content' );
		$reply = $topic->reply( $workflow, $user, $content, 'wikitext' );

		$filter = new ContentLengthFilter( $maxLength );
		$status = $filter->validate( $this->createMock( \IContextSource::class ), $reply, null, $title, $ownerTitle );
		$this->assertSame( $expected, $status->isOK() );
	}
}
