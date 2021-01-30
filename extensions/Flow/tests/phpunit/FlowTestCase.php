<?php

namespace Flow\Tests;

use ExtensionRegistry;
use Flow\Container;
use Flow\Data\FlowObjectCache;
use Flow\Model\UUID;
use HashBagOStuff;
use MediaWikiTestCase;
use WANObjectCache;

class FlowTestCase extends MediaWikiTestCase {
	protected function setUp() : void {
		Container::reset();
		parent::setUp();
	}

	/**
	 * @param mixed $data
	 * @return string
	 */
	protected function dataToString( $data ) {
		foreach ( $data as $key => $value ) {
			if ( $value instanceof UUID ) {
				$data[$key] = 'UUID: ' . $value->getAlphadecimal();
			}
		}

		return parent::dataToString( $data );
	}

	protected function getCache() {
		global $wgFlowCacheTime;
		$wanCache = new WANObjectCache( [
			'cache' => new HashBagOStuff(),
			'pool' => 'testcache-hash',
		] );

		return new FlowObjectCache( $wanCache, Container::get( 'db.factory' ), $wgFlowCacheTime );
	}

	protected function resetPermissions() {
		$registry = new ExtensionRegistry();
		$data = $registry->readFromQueue( [ __DIR__ . '/../../extension.json' => 1 ] );
		$perms = $data['globals']['wgGroupPermissions'];
		unset( $perms[$registry::MERGE_STRATEGY] );
		global $wgGroupPermissions;
		$this->setMwGlobals( 'wgGroupPermissions', wfArrayPlus2d( $perms, $wgGroupPermissions ) );
	}
}
