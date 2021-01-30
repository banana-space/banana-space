<?php

namespace Flow\Data\Listener;

use Flow\Data\LifecycleHandler;

/**
 * Inserts mw recentchange rows for flow AbstractRevision instances.
 */
class AbstractListener implements LifecycleHandler {

	/**
	 * @inheritDoc
	 */
	public function onAfterUpdate( $object, array $old, array $new, array $metadata ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterRemove( $object, array $old, array $metadata ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterLoad( $object, array $row ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterInsert( $revision, array $row, array $metadata ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onAfterClear() {
	}
}
