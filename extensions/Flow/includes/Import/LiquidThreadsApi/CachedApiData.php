<?php

namespace Flow\Import\LiquidThreadsApi;

abstract class CachedApiData extends CachedData {
	protected $backend;

	public function __construct( ApiBackend $backend ) {
		$this->backend = $backend;
	}
}
