<?php

namespace Flow\Formatter;

use ChangesList;
use Flow\Conversion\Utils;
use Flow\Exception\FlowException;
use Flow\Model\Anchor;
use Flow\Model\PostRevision;
use Html;
use IContextSource;

class ContributionsFormatter extends AbstractFormatter {
	protected function getHistoryType() {
		return 'contributions';
	}

	/**
	 * @param FormatterRow $row With properties workflow, revision, previous_revision
	 * @param IContextSource $ctx
	 * @return string|false HTML for contributions entry, or false on failure
	 * @throws FlowException
	 */
	public function format( FormatterRow $row, IContextSource $ctx ) {
		$this->serializer->setIncludeHistoryProperties( true );
		$data = $this->serializer->formatApi( $row, $ctx, 'contributions' );
		if ( !$data ) {
			return false;
		}

		$isNewPage = isset( $data['isNewPage'] ) && $data['isNewPage'];

		$charDiff = ChangesList::showCharacterDifference(
			$data['size']['old'],
			$data['size']['new']
		);

		$separator = $this->changeSeparator();

		$links = [];
		$links[] = $this->getDiffAnchor( $data['links'], $ctx );
		$links[] = $this->getHistAnchor( $data['links'], $ctx );

		$description = $this->formatDescription( $data, $ctx );

		$flags = '';
		if ( $isNewPage ) {
			$flags .= ChangesList::flag( 'newpage' ) . ' ';
		}

		// Put it all together
		return $this->formatTimestamp( $data ) . ' ' .
			$this->formatAnchorsAsPipeList( $links, $ctx ) .
			$separator .
			$charDiff .
			$separator .
			$flags .
			$this->getTitleLink( $data, $row, $ctx ) .
			( Utils::htmlToPlaintext( $description ) ? $separator . $description : '' ) .
			$this->getHideUnhide( $data, $row, $ctx );
	}

	/**
	 * @todo can be generic?
	 * @param array $data
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @return string
	 */
	protected function getHideUnhide( array $data, FormatterRow $row, IContextSource $ctx ) {
		if ( !$row->revision instanceof PostRevision ) {
			return '';
		}

		// @phan-suppress-next-line PhanUndeclaredMethod Phan doesn't infer $row->revision is PostRevision
		$type = $row->revision->isTopicTitle() ? 'topic' : 'post';

		if ( isset( $data['actions']['hide'] ) ) {
			$key = 'hide';
			// flow-post-action-hide-post, flow-post-action-hide-topic
			$msg = "flow-$type-action-hide-$type";
		} elseif ( isset( $data['actions']['unhide'] ) ) {
			$key = 'unhide';
			// flow-topic-action-restore-topic, flow-post-action-restore-post
			$msg = "flow-$type-action-restore-$type";
		} else {
			return '';
		}

		/** @var Anchor $anchor */
		$anchor = $data['actions'][$key];
		$message = ' ' . $ctx->msg( 'parentheses' )->rawParams( Html::rawElement(
			'a',
			[
				'href' => $anchor->getFullURL(),
				'data-flow-interactive-handler' => 'moderationDialog',
				'data-flow-template' => "flow_moderate_$type.partial",
				'data-role' => $key,
				'class' => 'flow-history-moderation-action flow-click-interactive',
			],
			$ctx->msg( $msg )->escaped()
		) )->escaped();

		return $message;
	}
}
