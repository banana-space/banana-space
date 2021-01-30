<?php

namespace Flow\Data;

/**
 * Listeners that receive notifications about the lifecycle of
 * a domain model.
 */
interface LifecycleHandler {
	public function onAfterLoad( $object, array $old );

	public function onAfterInsert( $object, array $new, array $metadata );

	public function onAfterUpdate( $object, array $old, array $new, array $metadata );

	public function onAfterRemove( $object, array $old, array $metadata );

	public function onAfterClear();
}
