<?php

namespace Flow\Tests\SpamFilter;

use Flow\Model\PostRevision;
use Flow\SpamFilter\SpamRegex;
use Flow\Tests\PostRevisionTestCase;
use Title;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\SpamFilter\SpamRegex
 *
 * @group Flow
 * @group Database
 */
class SpamRegexTest extends PostRevisionTestCase {
	/**
	 * @var SpamRegex
	 */
	protected $spamFilter;

	public function spamProvider() {
		return [
			[
				// default new topic title revision - no spam
				[],
				null,
				true
			],
			[
				// revision with spam
				[ 'rev_content' => 'http://spam', 'rev_flags' => 'html' ],
				null,
				false
			],
		];
	}

	/**
	 * @dataProvider spamProvider
	 */
	public function testSpam( $newRevisionRow, ?PostRevision $oldRevision, $expected ) {
		$newRevision = $this->generateObject( $newRevisionRow );
		$title = Title::newFromText( 'UTPage' );

		$status = $this->spamFilter->validate( $this->createMock( \IContextSource::class ), $newRevision, $oldRevision, $title, $title );
		$this->assertEquals( $expected, $status->isOK() );
	}

	protected function setUp() : void {
		parent::setUp();

		// create a dummy filter
		$this->setMwGlobals( 'wgSpamRegex', [ '/http:\/\/spam/' ] );

		// create spam filter
		$this->spamFilter = new SpamRegex;
		if ( !$this->spamFilter->enabled() ) {
			$this->markTestSkipped( 'SpamRegex not enabled' );
		}
	}
}
