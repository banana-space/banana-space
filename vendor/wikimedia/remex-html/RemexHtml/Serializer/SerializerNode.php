<?php

namespace RemexHtml\Serializer;

use RemexHtml\PropGuard;

class SerializerNode {
	use PropGuard;

	public $id;
	public $parentId;
	public $namespace;
	public $name;
	public $attrs;
	public $void;
	public $children = [];

	/**
	 * Arbitrary user data can be placed here.
	 */
	public $snData;

	public function __construct( $id, $parentId, $namespace, $name, $attrs, $void ) {
		$this->id = $id;
		$this->parentId = $parentId;
		$this->namespace = $namespace;
		$this->name = $name;
		$this->attrs = $attrs;
		$this->void = $void;
	}

	/**
	 * Get a string identifying the node, for use in debugging.
	 * @return string
	 */
	public function getDebugTag() {
		return $this->name . '#' . $this->id;
	}
}
