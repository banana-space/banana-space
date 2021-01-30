<?php

namespace Flow\Formatter;

use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\Workflow;

/**
 * Helper class represents a row of data from AbstractQuery
 */
class FormatterRow {
	/** @var AbstractRevision */
	public $revision;
	/** @var AbstractRevision|null */
	public $previousRevision;
	/** @var AbstractRevision */
	public $currentRevision;
	/** @var Workflow */
	public $workflow;
	/** @var string */
	public $indexFieldName;
	/** @var string */
	public $indexFieldValue;
	/** @var PostRevision|null */
	public $rootPost;
	/** @var bool */
	public $isLastReply = false;
	/** @var bool */
	public $isFirstReply = false;

	// protect against typos
	public function __get( $attribute ) {
		throw new \MWException( "Accessing non-existent parameter: $attribute" );
	}

	// protect against typos
	public function __set( $attribute, $value ) {
		throw new \MWException( "Accessing non-existent parameter: $attribute" );
	}
}
