<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Tokens;

use stdClass;

/**
 * Represents an HTML end tag token
 */
class EndTagTk extends Token {
	/** @var string Name of the end tag */
	private $name;

	/** @var array Attributes of this token
	 * This is represented an array of KV objects
	 * TODO: Expand on this.
	 */
	public $attribs = [];

	/** @var stdClass Data attributes for this token
	 * TODO: Expand on this.
	 */
	public $dataAttribs;

	/**
	 * @param string $name
	 * @param KV[] $attribs
	 * @param stdClass|null $dataAttribs
	 */
	public function __construct( string $name, array $attribs = [], stdClass $dataAttribs = null ) {
		$this->name = $name;
		$this->attribs = $attribs;
		$this->dataAttribs = $dataAttribs ?? new stdClass;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return [
			'type' => $this->getType(),
			'name' => $this->name,
			'attribs' => $this->attribs,
			'dataAttribs' => $this->dataAttribs
		];
	}
}
