<?php

namespace Flow\Formatter;

use ChangesList;
use Flow\Conversion\Utils;
use Flow\Data\Listener\RecentChangesListener;
use Flow\Exception\FlowException;
use Flow\Exception\PermissionException;
use Flow\Model\Anchor;
use Flow\Model\UUID;
use IContextSource;
use Linker;
use MediaWiki\MediaWikiServices;

class ChangesListFormatter extends AbstractFormatter {
	protected function getHistoryType() {
		return 'recentchanges';
	}

	/**
	 * @param RecentChangesRow $row
	 * @param IContextSource $ctx
	 * @param bool $linkOnly
	 * @return string|false Output line, or false on failure
	 * @throws FlowException
	 */
	public function format( RecentChangesRow $row, IContextSource $ctx, $linkOnly = false ) {
		$this->serializer->setIncludeHistoryProperties( true );
		$this->serializer->setIncludeContent( false );

		$data = $this->serializer->formatApi( $row, $ctx, 'recentchanges' );
		if ( !$data ) {
			return false;
		}

		if ( $linkOnly ) {
			return $this->getTitleLink( $data, $row, $ctx );
		}

		// The ' . . ' text between elements
		$separator = $this->changeSeparator();

		$links = [];
		$links[] = $this->getDiffAnchor( $data['links'], $ctx );
		$links[] = $this->getHistAnchor( $data['links'], $ctx );

		$description = $this->formatDescription( $data, $ctx );

		$flags = $this->getFlags( $row, $ctx );

		return $this->formatAnchorsAsPipeList( $links, $ctx ) .
			$separator .
			$this->formatFlags( $flags ) .
			$this->getTitleLink( $data, $row, $ctx ) .
			$ctx->msg( 'semicolon-separator' )->escaped() .
			' ' .
			$this->formatTimestamp( $data, 'time' ) .
			$separator .
			ChangesList::showCharacterDifference(
				$data['size']['old'],
				$data['size']['new'],
				$ctx
			) .
			( Utils::htmlToPlaintext( $description ) ? $separator . $description : '' ) .
			$this->getEditSummary( $row, $ctx, $data );
	}

	/**
	 * @param RecentChangesRow $row
	 * @param IContextSource $ctx
	 * @param array $data
	 * @return string
	 */
	public function getEditSummary( RecentChangesRow $row, IContextSource $ctx, array $data ) {
		// Build description message, piggybacking on history i18n
		$changeType = $data['changeType'];
		$actions = $this->permissions->getActions();

		$key = $actions->getValue( $changeType, 'history', 'i18n-message' );
		// Find specialized message for summary
		// i18n messages: flow-rev-message-new-post-recentchanges-summary,
		// flow-rev-message-edit-post-recentchanges-summary
		$msg = $ctx->msg( $key . '-' . $this->getHistoryType() . '-summary' );
		if ( !$msg->exists() ) {
			// No summary for this action
			return '';
		}

		$msg = $msg->params( $this->getDescriptionParams( $data, $actions, $changeType ) );

		// Below code is inspired by Linker::formatAutocomments
		$prefix = $ctx->msg( 'autocomment-prefix' )->inContentLanguage()->escaped();
		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$row->workflow->getOwnerTitle(),
			$ctx->getLanguage()->getArrow( 'backwards' ),
			[],
			[]
		);
		$summary = '<span class="autocomment">' . $msg->text() . '</span>';

		// '(' + '' + '←' + summary + ')'
		$text = Linker::commentBlock( $prefix . $link . $summary );

		// Linker::commentBlock escaped everything, but what we built was safe
		// and should not be escaped so let's go back to decoded entities
		return htmlspecialchars_decode( $text );
	}

	/**
	 * This overrides the default title link to include highlights for the posts
	 * that have not yet been seen.
	 *
	 * @param array $data
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @return string
	 */
	protected function getTitleLink( array $data, FormatterRow $row, IContextSource $ctx ) {
		if ( !$row instanceof RecentChangesRow ) {
			// actually, this should be typehint, but can't because this needs
			// to match the parent's more generic typehint
			return parent::getTitleLink( $data, $row, $ctx );
		}

		if ( !isset( $data['links']['topic'] ) || !$data['links']['topic'] instanceof Anchor ) {
			// no valid title anchor (probably header entry)
			return parent::getTitleLink( $data, $row, $ctx );
		}

		$watched = $row->recentChange->getAttribute( 'wl_notificationtimestamp' );
		if ( is_bool( $watched ) ) {
			// RC & watchlist share most code; the latter is unaware of when
			// something was watched though, so we'll ignore that here
			return parent::getTitleLink( $data, $row, $ctx );
		}

		if ( $watched === null ) {
			// there is no data for unread posts - they've all been seen
			return parent::getTitleLink( $data, $row, $ctx );
		}

		// get comparison UUID corresponding to this last watched timestamp
		$uuid = UUID::getComparisonUUID( $watched );

		// add highlight details to anchor
		/** @var Anchor $anchor */
		$anchor = clone $data['links']['topic'];
		$anchor->query['fromnotif'] = '1';
		$anchor->fragment = '#flow-post-' . $uuid->getAlphadecimal();
		$data['links']['topic'] = $anchor;

		// now pass it on to parent with the new, updated, link ;)
		return parent::getTitleLink( $data, $row, $ctx );
	}

	/**
	 * @param RecentChangesRow $row
	 * @param IContextSource $ctx
	 * @return string
	 * @throws PermissionException
	 */
	public function getTimestampLink( $row, $ctx ) {
		$this->serializer->setIncludeHistoryProperties( true );
		$this->serializer->setIncludeContent( false );

		$data = $this->serializer->formatApi( $row, $ctx, 'recentchanges' );
		if ( $data === false ) {
			throw new PermissionException( 'Insufficient permissions for ' . $row->revision->getRevisionId()->getAlphadecimal() );
		}

		return $this->formatTimestamp( $data, 'time' );
	}

	/**
	 * @param RecentChangesRow $row
	 * @param IContextSource $ctx
	 * @param \RCCacheEntry[] $block
	 * @param array $links
	 * @return array|false Links array, or false on failure
	 * @throws FlowException
	 * @throws \Flow\Exception\InvalidInputException
	 */
	public function getLogTextLinks( RecentChangesRow $row, IContextSource $ctx, array $block, array $links = [] ) {
		$data = $this->serializer->formatApi( $row, $ctx, 'recentchanges' );
		if ( !$data ) {
			return false;
		}

		// Find the last (oldest) row in $block that is a Flow row. Note that there can be non-Flow
		// things in $block (T228290).
		$flowRows = array_filter( $block, function ( $blockRow ) {
			$source = $blockRow->getAttribute( 'rc_source' );
			return $source === RecentChangesListener::SRC_FLOW ||
				( $source === null && $blockRow->getAttribute( 'rc_type' ) === RC_FLOW );
		} );
		$oldestRow = end( $flowRows ) ?? $row->recentChange;

		$old = unserialize( $oldestRow->getAttribute( 'rc_params' ) );
		$oldId = $old ? UUID::create( $old['flow-workflow-change']['revision'] ) : $row->revision->getRevisionId();

		if ( isset( $data['links']['topic'] ) ) {
			// add highlight details to anchor
			// FIXME: This doesn't work well if the different rows in $block are for different topics
			/** @var Anchor $anchor */
			$anchor = clone $data['links']['topic'];
			$anchor->query['fromnotif'] = '1';
			$anchor->fragment = '#flow-post-' . $oldId->getAlphadecimal();
		} elseif ( isset( $data['links']['workflow'] ) ) {
			$anchor = $data['links']['workflow'];
		} else {
			// this will be caught and logged by the RC hook, it will not fatal the page.
			throw new FlowException( "No anchor available for revision $oldId" );
		}

		$changes = count( $block );
		// link text: "n changes"
		$text = $ctx->msg( 'nchanges' )->numParams( $changes )->escaped();

		// override total changes link
		$links['total-changes'] = $anchor->toHtml( $text );

		return $links;
	}

	/**
	 * @param RecentChangesRow $row
	 * @param IContextSource $ctx
	 * @return array
	 */
	public function getFlags( RecentChangesRow $row, IContextSource $ctx ) {
		return [
			'newpage' => $row->isFirstReply && $row->revision->isFirstRevision(),
			'minor' => false,
			'unpatrolled' => ChangesList::isUnpatrolled( $row->recentChange, $ctx->getUser() ),
			'bot' => false,
		];
	}

	/**
	 * @param array $flags
	 * @return string
	 */
	protected function formatFlags( $flags ) {
		$flagKeys = array_keys( array_filter( $flags ) );
		if ( $flagKeys ) {
			$formattedFlags = array_map( 'ChangesList::flag', $flagKeys );
			return implode( ' ', $formattedFlags ) . ' ';
		}
		return '';
	}
}
