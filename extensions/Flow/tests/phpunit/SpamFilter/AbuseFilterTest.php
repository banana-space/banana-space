<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\SpamFilter;

use Flow\SpamFilter\AbuseFilter;
use Flow\Tests\PostRevisionTestCase;
use Title;
use User;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\SpamFilter\AbuseFilter
 *
 * @group Database
 * @group Flow
 */
class AbuseFilterTest extends PostRevisionTestCase {
	private const BAD_TOPIC_TITLE_TEXT = 'Topic:Tnprd6ksfu1v1nme';
	private const BAD_OWNER_TITLE_TEXT = 'BadBoard';

	/**
	 * @var AbuseFilter
	 */
	protected $spamFilter;

	/**
	 * @var array
	 */
	protected $tablesUsed = [ 'abuse_filter', 'abuse_filter_action', 'abuse_filter_history', 'abuse_filter_log' ];

	protected $filters = [
		// no CSS screen hijack
		'(new_wikitext rlike "position\s*:\s*(fixed|absolute)|style\s*=\s*\"[a-z0-9:;\s]*&|z-index\s*:\s*\d|\|([4-9]\d{3}|\d{5,})px")' => 'disallow',
		'(page_prefixedtitle === "Topic:Tnprd6ksfu1v1nme" & page_prefixedtitle === article_prefixedtext)' => 'disallow',
		'(board_prefixedtitle === "BadBoard" & board_prefixedtitle === board_prefixedtext)' => 'disallow',
	];

	public function spamProvider() {
		$goodTopicTitle = Title::newFromText( 'Topic:Tnpn1618hctgeguu' );
		$goodOwnerTitle = Title::newFromText( 'UTPage' );

		$badTopicTitle = Title::newFromText( self::BAD_TOPIC_TITLE_TEXT );
		$badOwnerTitle = Title::newFromText( self::BAD_OWNER_TITLE_TEXT );

		// This is a simplified test, just to cover the variables.
		// For a new topic, they are actually both the board title.
		return [
			[
				$goodTopicTitle,
				$goodOwnerTitle,
				// default new topic title revision, both good titles - no spam
				[],
				null,
				true,
			],
			[
				$goodTopicTitle,
				$goodOwnerTitle,
				// revision with spam
				// https://www.mediawiki.org/w/index.php?title=Talk:Sandbox&workflow=050bbdd07b64a1c028b2782bcb087b42#flow-post-050bbdd07b70a1c028b2782bcb087b42
				[ 'rev_content' => '<div style="background: yellow; position: fixed; top: 0; left: 0; width: 3000px; height: 3000px; z-index: 1111;">test</div>', 'rev_flags' => 'html' ],
				null,
				false,
			],
			[
				$badTopicTitle,
				$goodOwnerTitle,
				[],
				// Topic title matches
				null,
				false,
			],
			[
				$goodTopicTitle,
				$badOwnerTitle,
				[],
				// Owner title matches
				null,
				false,
			],
		];
	}

	/**
	 * @dataProvider spamProvider
	 */
	public function testSpam( $title, $ownerTitle, $newRevisionRow, $oldRevisionRow, $expected ) {
		$newRevision = $this->generateObject( $newRevisionRow );
		$oldRevision = $oldRevisionRow ? $this->generateObject( $oldRevisionRow ) : null;

		$context = $this->getMockBuilder( \ContextSource::class )
				->setMethods( [ 'getUser' ] )
				->getMock();
		$context->expects( $this->any() )
				->method( 'getUser' )
				->will( $this->returnValue( User::newFromName( 'UTSysop' ) ) );

		$status = $this->spamFilter->validate( $context, $newRevision, $oldRevision, $title, $ownerTitle );
		$this->assertEquals( $expected, $status->isOK() );
	}

	protected function setUp() : void {
		parent::setUp();

		global $wgFlowAbuseFilterGroup,
			$wgFlowAbuseFilterEmergencyDisableThreshold,
			$wgFlowAbuseFilterEmergencyDisableCount,
			$wgFlowAbuseFilterEmergencyDisableAge;

		// Needed because abuse filter tries to read the title out and then
		// set it back.  If we never provide one it tries to set a null title
		// and bails.
		\RequestContext::getMain()->setTitle( Title::newMainPage() );

		$user = User::newFromName( 'UTSysop' );
		\RequestContext::getMain()->setUser( $user );

		$this->spamFilter = new AbuseFilter( $wgFlowAbuseFilterGroup );
		if ( !$this->spamFilter->enabled() ) {
			$this->markTestSkipped( 'AbuseFilter not enabled' );
		}

		$this->spamFilter->setup( [
			'threshold' => $wgFlowAbuseFilterEmergencyDisableThreshold,
			'count' => $wgFlowAbuseFilterEmergencyDisableCount,
			'age' => $wgFlowAbuseFilterEmergencyDisableAge,
		] );

		foreach ( $this->filters as $pattern => $action ) {
			$this->createFilter( $pattern, $action );
		}
	}

	protected function tearDown() : void {
		parent::tearDown();
		foreach ( $this->tablesUsed as $table ) {
			$this->db->delete( $table, '*', __METHOD__ );
		}
	}

	/**
	 * Inserts a filter into stub database.
	 *
	 * @param string $pattern
	 * @param string $action
	 */
	protected function createFilter( $pattern, $action = 'disallow' ) {
		global $wgFlowAbuseFilterGroup;
		$user = User::newFromName( 'UTSysop' );

		$this->db->replace(
			'abuse_filter',
			[ 'af_id' ],
			[
				// 'af_id',
				'af_pattern' => $pattern,
				'af_user' => $user->getId(),
				'af_user_text' => $user->getName(),
				'af_timestamp' => wfTimestampNow(),
				'af_enabled' => 1,
				'af_comments' => null,
				'af_public_comments' => 'Test filter',
				'af_hidden' => 0,
				'af_hit_count' => 0,
				'af_throttled' => 0,
				'af_deleted' => 0,
				'af_actions' => $action,
				'af_group' => $wgFlowAbuseFilterGroup,
			],
			__METHOD__
		);

		$this->db->replace(
			'abuse_filter_action',
			[ 'afa_filter' ],
			[
				'afa_filter' => $this->db->insertId(),
				'afa_consequence' => $action,
				'afa_parameters' => '',
			],
			__METHOD__
		);
	}
}
