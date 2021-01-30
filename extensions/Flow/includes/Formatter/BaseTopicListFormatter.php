<?php

namespace Flow\Formatter;

use Flow\Model\Anchor;
use Flow\Model\Workflow;

class BaseTopicListFormatter {
	/**
	 * Builds the results for an empty topic.
	 *
	 * @param Workflow $workflow Workflow for topic list
	 * @return array Associative array with the result
	 */
	public function buildEmptyResult( Workflow $workflow ) {
		return [
			'type' => 'topiclist',
			'roots' => [],
			'posts' => [],
			'revisions' => [],
			'links' => [ 'pagination' => [] ],
		];
	}

	/**
	 * @param Workflow $workflow Topic list workflow
	 * @param array[] $links pagination link data
	 *
	 * @return Anchor[] link structure
	 */
	protected function buildPaginationLinks( Workflow $workflow, array $links ) {
		$res = [];
		$title = $workflow->getArticleTitle();
		foreach ( $links as $key => $options ) {
			// prefix all options with topiclist_
			$realOptions = [];
			foreach ( $options as $k => $v ) {
				$realOptions["topiclist_$k"] = $v;
			}
			$res[$key] = new Anchor(
				$key, // @todo i18n
				$title,
				$realOptions
			);
		}

		return $res;
	}
}
