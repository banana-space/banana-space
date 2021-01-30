<?php

namespace Flow\Formatter;

use FeedItem;
use Flow\Exception\FlowException;
use IContextSource;

class FeedItemFormatter extends AbstractFormatter {
	protected function getHistoryType() {
		return 'feeditem';
	}

	/**
	 * @param FormatterRow $row With properties workflow, revision, previous_revision
	 * @param IContextSource $ctx
	 * @return FeedItem|false The requested format, or false on failure
	 * @throws FlowException
	 */
	public function format( FormatterRow $row, IContextSource $ctx ) {
		$this->serializer->setIncludeHistoryProperties( true );
		$data = $this->serializer->formatApi( $row, $ctx, 'contributions' );
		if ( !$data ) {
			return false;
		}

		$preferredLinks = [
			'header-revision',
			'post-revision', 'post',
			'topic-revision', 'topic',
			'board'
		];
		$url = '';
		foreach ( $preferredLinks as $link ) {
			if ( isset( $data['links'][$link] ) ) {
				$url = $data['links'][$link]->getFullURL();
				break;
			}
		}
		// If we didn't choose anything just take the first.
		// @todo perhaps just a convention that the first link
		// is always the most specific, we use a similar pattern
		// to above in TemplateHelper::historyTimestamp too.
		if ( $url === '' && $data['links'] ) {
			$keys = array_keys( $data['links'] );
			$link = reset( $keys );
			$url = $data['links'][$link]->getFullURL();
		}

		return new FeedItem(
			$row->workflow->getArticleTitle()->getPrefixedText(),
			$this->formatDescription( $data, $ctx ),
			$url,
			$data['timestamp'],
			$data['author']['name'],
			$row->workflow->getOwnerTitle()->getFullURL()
		);
	}
}
