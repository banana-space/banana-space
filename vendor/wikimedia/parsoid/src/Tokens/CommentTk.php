<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Tokens;

use stdClass;

/**
 * Represents a comment
 */
class CommentTk extends Token {
	/** @var string Comment text */
	public $value;

	/** @var stdClass Data attributes for this token
	 * TODO: Expand on this.
	 */
	public $dataAttribs;

	/**
	 * @param string $value
	 * @param stdClass|null $dataAttribs
	 */
	public function __construct( string $value, stdClass $dataAttribs = null ) {
		$this->value = $value;

		// Won't survive in the DOM, but still useful for token serialization
		// FIXME: verify if this is still required given that html->wt doesn't
		// use tokens anymore. That was circa 2012 serializer code.
		$this->dataAttribs = $dataAttribs ?? new stdClass;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'value' => $this->value,
			'dataAttribs' => $this->dataAttribs
		];
	}
}
