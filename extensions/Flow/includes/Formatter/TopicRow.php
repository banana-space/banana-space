<?php

namespace Flow\Formatter;

use Flow\Model\PostRevision;

class TopicRow extends FormatterRow {
	/**
	 * @var PostRevision[]
	 */
	public $replies;

	/**
	 * @var FormatterRow
	 */
	public $summary;

	/**
	 * @var bool
	 */
	public $isWatched;
}
