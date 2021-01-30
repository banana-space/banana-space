<?php

namespace Flow\Tests\Model;

use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Tests\PostRevisionTestCase;
use Title;
use User;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 *
 * @group Flow
 * @group Database
 */
class PostRevisionTest extends PostRevisionTestCase {
	/**
	 * Tests that a PostRevision::fromStorageRow & ::toStorageRow roundtrip
	 * returns the same DB data.
	 */
	public function testRoundtrip() {
		$row = $this->generateRow();
		$object = PostRevision::fromStorageRow( $row );

		// toStorageRow will add a bogus column 'rev_content_url' - that's ok.
		// It'll be caught in code to distinguish between external content and
		// content to be saved in rev_content, and, before inserting into DB,
		// it'll be unset. We'll ignore this column here.
		$roundtripRow = PostRevision::toStorageRow( $object );
		unset( $roundtripRow['rev_content_url'] );

		// Due to our desire to store alphadecimal values in cache and binary values on
		// disk we need to perform uuid conversion before comparing
		$roundtripRow = UUID::convertUUIDs( $roundtripRow, 'binary' );
		$this->assertEquals( $row, $roundtripRow );
	}

	public function testContentLength() {
		$content = 'This is a topic title';
		$nextContent = 'Changed my mind';

		$title = Title::newMainPage();
		$user = User::newFromName( '127.0.0.1', false );
		$workflow = Workflow::create( 'topic', $title );

		$topic = PostRevision::createTopicPost( $workflow, $user, $content );
		$this->assertSame( 0, $topic->getPreviousContentLength() );
		$this->assertEquals( mb_strlen( $content ), $topic->getContentLength() );

		$next = $topic->newNextRevision( $user, $nextContent, 'topic-title-wikitext', 'edit-title', $title );
		$this->assertEquals( mb_strlen( $content ), $next->getPreviousContentLength() );
		$this->assertEquals( mb_strlen( $nextContent ), $next->getContentLength() );
	}
}
