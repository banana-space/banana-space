<?php

namespace Flow\Tests\SpamFilter;

use BaseBlacklist;
use Flow\Model\PostRevision;
use Flow\SpamFilter\SpamBlacklist;
use Flow\Tests\PostRevisionTestCase;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\SpamFilter\SpamBlacklist
 *
 * @group Flow
 * @group Database
 */
class SpamBlacklistTest extends PostRevisionTestCase {
	/**
	 * @var SpamBlacklist
	 */
	protected $spamFilter;

	/**
	 * Spam blacklist regexes. Examples taken from:
	 *
	 * @see http://meta.wikimedia.org/wiki/Spam_blacklist
	 * @see http://en.wikipedia.org/wiki/MediaWiki:Spam-blacklist
	 *
	 * @var array
	 */
	protected $blacklist = [ '\b01bags\.com\b', 'sytes\.net' ];

	/**
	 * Spam whitelist regexes. Examples taken from:
	 *
	 * @see http://en.wikipedia.org/wiki/MediaWiki:Spam-whitelist
	 *
	 * @var array
	 */
	protected $whitelist = [ 'a5b\.sytes\.net' ];

	public function spamProvider() {
		return [
			'default new topic title revision - no spam' => [
				[],
				null,
				true
			],
			'revision with spam' => [
				[ 'rev_content' => 'http://01bags.com', 'rev_flags' => 'html' ],
				null,
				false
			],
			'revision with domain blacklisted as spam, but subdomain whitelisted' => [
				[ 'rev_content' => 'http://a5b.sytes.net', 'rev_flags' => 'html' ],
				null,
				true
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

		// create spam filter
		$this->spamFilter = new SpamBlacklist;
		if ( !$this->spamFilter->enabled() ) {
			$this->markTestSkipped( 'SpamBlacklist not enabled' );
		}

		$this->setMwGlobals( 'wgBlacklistSettings', [
			'files' => [],
		] );

		MediaWikiServices::getInstance()->getMessageCache()->enable();
		$this->insertPage( 'MediaWiki:Spam-blacklist', implode( "\n", $this->blacklist ) );
		$this->insertPage( 'MediaWiki:Spam-whitelist', implode( "\n", $this->whitelist ) );

		// That only works if the spam blacklist is really reset
		$instance = BaseBlacklist::getInstance( 'spam' );
		$reflProp = new \ReflectionProperty( $instance, 'regexes' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $instance, false );
	}

	protected function tearDown() : void {
		MediaWikiServices::getInstance()->getMessageCache()->disable();
		parent::tearDown();
	}
}
