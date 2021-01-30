<?php

// The primary purpose of this is to check whether ExternalStore is used, to verify that it
// doesn't insert rows that are not later used.
// This is not in Flow\Tests\Mock since ExternalStore expects it in the global namespace.
class ExternalStoreFlowMock extends ExternalStoreMemory {
	public static $isUsed = false;

	public function __construct( $params ) {
		parent::__construct( $params );
		self::$isUsed = true;
	}

	public function fetchFromURL( $url ) {
		throw new MWException( 'The mock does not support ' . __FUNCTION__ . '.' );
	}

	public function store( $location, $data ) {
		return "FlowMock://$location/1";
	}
}
