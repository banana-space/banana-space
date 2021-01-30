<?php

namespace Flow\Collection;

class HeaderCollection extends LocalCacheAbstractCollection {
	public static function getRevisionClass() {
		return \Flow\Model\Header::class;
	}

	public function getWorkflowId() {
		return $this->getId();
	}

	public function getBoardWorkflowId() {
		return $this->getId();
	}
}
