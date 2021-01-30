<?php

namespace Flow\Formatter;

use Flow\Container;
use Flow\RevisionActionPermissions;
use FormatJson;
use IContextSource;
use MediaWiki\Logger\LoggerFactory;
use RCFeedFormatter;
use RecentChange;
use SplObjectStorage;

/**
 * Generates URL's to be inserted into the IRC
 * recent changes feed.
 */
class IRCLineUrlFormatter extends AbstractFormatter implements RCFeedFormatter {
	/**
	 * @var SplObjectStorage
	 */
	protected $data;

	public function __construct( RevisionActionPermissions $permissions, RevisionFormatter $serializer ) {
		parent::__construct( $permissions, $serializer );
		$this->data = new SplObjectStorage;
	}

	protected function getHistoryType() {
		return 'irc';
	}

	public function associate( RecentChange $rc, array $metadata ) {
		$this->data[$rc] = $metadata;
	}

	/**
	 * Allows us to set the rc_comment field
	 */

	/**
	 * @param array $feed
	 * @param RecentChange $rc
	 * @param null|string $actionComment
	 * @return string|null Text for IRC line, or null on failure
	 */
	public function getLine( array $feed, RecentChange $rc, $actionComment ) {
		$ctx = \RequestContext::getMain();

		$serialized = $this->serializeRcRevision( $rc, $ctx );
		if ( !$serialized ) {
			LoggerFactory::getInstance( 'Flow' )->debug(
				__METHOD__ . ': Failed to obtain serialized RC revision.',
				[
					'rc_attributes' => FormatJson::encode( $rc->getAttributes(), true ),
					'user_id' => $ctx->getUser()->getId(),
				]
			);
			return null;
		}

		$rcAttribs = $rc->getAttributes();
		$rcAttribs['rc_comment'] = $this->formatDescription( $serialized, $ctx );
		$rcAttribs['rc_comment_text'] = $rcAttribs['rc_comment'];
		$rcAttribs['rc_comment_data'] = null;
		$rc->setAttribs( $rcAttribs );

		/** @var RCFeedFormatter $formatter */
		$formatter = new $feed['original_formatter']();
		return $formatter->getLine( $feed, $rc, $actionComment );
	}

	/**
	 * Gets the formatted RC revision, or returns null if this revision is not to be
	 *  shown in RC, or on failure.
	 *
	 * @param RecentChange $rc
	 * @param IContextSource $ctx
	 * @return array|false Array of data, or false on failure
	 *
	 * @fixme this looks slow, likely a better way
	 */
	protected function serializeRcRevision( RecentChange $rc, IContextSource $ctx ) {
		/** @var RecentChangesQuery $query */
		$query = Container::get( 'query.changeslist' );
		$query->loadMetadataBatch( [ (object)$rc->getAttributes() ] );
		$rcRow = $query->getResult( null, $rc );
		if ( !$rcRow ) {
			LoggerFactory::getInstance( 'Flow' )->debug(
				__METHOD__ . ': Failed to load result.',
				[
					'rc_attributes' => FormatJson::encode( $rc->getAttributes(), true ),
					'user_id' => $ctx->getUser()->getId()
				]
			);
			return false;
		}

		$this->serializer->setIncludeHistoryProperties( true );
		return $this->serializer->formatApi( $rcRow, $ctx, 'recentchanges' );
	}

	/**
	 * Generate a plaintext revision description suitable for IRC consumption
	 *
	 * @param array $data
	 * @param \IContextSource $ctx not used
	 * @return string
	 */
	protected function formatDescription( array $data, \IContextSource $ctx ) {
		$msg = $this->getDescription( $data, $ctx );
		return $msg->inLanguage( 'en' )->text();
	}

	/**
	 * @param RecentChange $rc
	 * @return string|null
	 */
	public function format( RecentChange $rc ) {
		// commit metadata provided via self::associate
		if ( !isset( $this->data[$rc] ) ) {
			wfDebugLog( 'Flow', __METHOD__ . ': Nothing pre-loaded about rc ' . $rc->getAttribute( 'rc_id' ) );
			return null;
		}
		$metadata = $this->data[$rc];

		$row = new FormatterRow;
		$row->revision = $metadata['revision'];
		$row->currentRevision = $row->revision;
		$row->workflow = $metadata['workflow'];
		$links = $this->serializer->buildLinks( $row );

		// Listed in order of preference
		$accept = [
			'diff',
			'post-history', 'topic-history', 'board-history',
			'post', 'topic',
			'workflow'
		];

		foreach ( $accept as $key ) {
			if ( isset( $links[$key] ) ) {
				return $links[$key]->getCanonicalURL();
			}
		}

		wfDebugLog( 'Flow', __METHOD__
				. ': No url generated for action ' . $row->workflow->getType()
				. ' on revision ' . $row->revision->getRevisionId()
		);
		return null;
	}
}
