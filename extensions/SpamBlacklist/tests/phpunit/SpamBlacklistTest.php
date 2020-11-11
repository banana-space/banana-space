<?php

/**
 * @group SpamBlacklist
 */
class SpamBlacklistTest extends MediaWikiTestCase {
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
	 * via Flow extension
	 *
	 * @var array
	 */
	protected $blacklist = [ '\b01bags\.com\b', 'sytes\.net' ];

	/**
	 * Spam whitelist regexes. Examples taken from:
	 *
	 * @see http://en.wikipedia.org/wiki/MediaWiki:Spam-whitelist
	 *
	 * via Flow extension
	 *
	 * @var array
	 */
	protected $whitelist = [ 'a5b\.sytes\.net' ];

	public function spamProvider() {
		return [
			'no spam' => [
				[ 'https://example.com' ],
				false,
			],
			'revision with spam, with additional non-spam' => [
				[ 'https://foo.com', 'http://01bags.com', 'http://bar.com' ],
				[ '01bags.com' ],
			],

			'revision with spam using full width stop normalization' => [
				[ 'http://01bags．com' ],
				[ '01bags.com' ],
			],

			'revision with domain blacklisted as spam, but subdomain whitelisted' => [
				[ 'http://a5b.sytes.net' ],
				false,
			],
		];
	}

	/**
	 * @dataProvider spamProvider
	 */
	public function testSpam( $links, $expected ) {
		$returnValue = $this->spamFilter->filter( $links, Title::newMainPage() );
		$this->assertEquals( $expected, $returnValue );
	}

	protected function setUp() {
		parent::setUp();

		// create spam filter
		$this->spamFilter = new SpamBlacklist;

		$this->setMwGlobals( 'wgBlacklistSettings', [
			'files' => [],
		] );

		\MessageCache::singleton()->enable();
		$this->insertPage( 'MediaWiki:Spam-blacklist', implode( "\n", $this->blacklist ) );
		$this->insertPage( 'MediaWiki:Spam-whitelist', implode( "\n", $this->whitelist ) );

		// That only works if the spam blacklist is really reset
		$instance = BaseBlacklist::getInstance( 'spam' );
		$reflProp = new \ReflectionProperty( $instance, 'regexes' );
		$reflProp->setAccessible( true );
		$reflProp->setValue( $instance, false );
	}

	protected function tearDown() {
		\MessageCache::singleton()->disable();
		parent::tearDown();
	}
}
