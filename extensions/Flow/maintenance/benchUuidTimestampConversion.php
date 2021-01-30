<?php

use Flow\Model\UUID;

require_once __DIR__ . '/../../../maintenance/benchmarks/Benchmarker.php';

/**
 * @ingroup Benchmark
 */
class BenchUuidConversions extends \Benchmarker {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Benchmark uuid timstamp extraction implementations' );
	}

	public function execute() {
		// sample values slightly different to avoid
		// UUID internal caching
		$alpha = UUID::hex2alnum( '0523f95ac547ab45266762' );
		$binary = UUID::hex2bin( '0523f95af547ab45266762' );
		$hex = '0423f95af547ab45266762';

		// benchmarker requires we pass an object
		$id = UUID::create();

		$this->bench( [
			[
				'function' => [ $id, 'bin2hex' ],
				'args' => [ $binary ],
			],
			[
				'function' => [ $id, 'alnum2hex' ],
				'args' => [ $alpha ],
			],
			[
				'function' => [ $id, 'hex2bin' ],
				'args' => [ $hex ],
			],
			[
				'function' => [ $id, 'hex2alnum' ],
				'args' => [ $hex ],
			],
			[
				'function' => [ $id, 'hex2timestamp' ],
				'args' => [ $hex ],
			],
			[
				'function' => [ $this, 'oldhex2timestamp' ],
				'args' => [ $hex ],
			],
			[
				'function' => [ $this, 'oldalphadecimal2timestamp' ],
				'args' => [ $alpha ],
			],
			[
				'function' => [ $this, 'case1' ],
				'args' => [ $binary ],
			],
			[
				'function' => [ $this, 'case2' ],
				'args' => [ $binary ],
			],
			[
				'function' => [ $this, 'case3' ],
				'args' => [ $alpha ],
			],
			[
				'function' => [ $this, 'case4' ],
				'args' => [ $alpha ],
			],
		] );

		// @fixme Find a replacement for this (removed from core in 1.29)
		// $this->output( $this->getFormattedResults() );
	}

	public function oldhex2timestamp( $hex ) {
		$bits = \Wikimedia\base_convert( $hex, 16, 2, 88 );
		$msTimestamp = (int)\Wikimedia\base_convert( substr( $bits, 0, 46 ), 2, 10 );
		return intval( $msTimestamp / 1000 );
	}

	public function oldalphadecimal2timestamp( $alpha ) {
		$bits = \Wikimedia\base_convert( $alpha, 36, 2, 88 );
		$msTimestamp = (int)\Wikimedia\base_convert( substr( $bits, 0, 46 ), 2, 10 );
		return intval( $msTimestamp / 1000 );
	}

	/**
	 * Common case 1: binary from database to alpha and timestamp.
	 * @param string $binary
	 */
	public function case1( $binary ) {
		// clone to avoid internal object caching
		$id = clone UUID::create( $binary );
		$id->getAlphadecimal();
		$id->getTimestampObj();
	}

	/**
	 * Common case 2: binary from database to timestamp and alpha.
	 * Probably same as case 1, but who knows.
	 * @param string $binary
	 */
	public function case2( $binary ) {
		// clone to avoid internal object caching
		$id = clone UUID::create( $binary );
		$id->getTimestampObj();
		$id->getAlphadecimal();
	}

	/**
	 * Common case 3: alphadecimal from cache to timestamp and binary.
	 * @param string $alpha
	 */
	public function case3( $alpha ) {
		// clone to avoid internal object caching
		$id = clone UUID::create( $alpha );
		$id->getBinary();
		$id->getTimestampObj();
	}

	/**
	 * Common case 4: alphadecimal from cache to bianry and timestamp.
	 * @param string $alpha
	 */
	public function case4( $alpha ) {
		// clone to avoid internal object caching
		$id = clone UUID::create( $alpha );
		$id->getTimestampObj();
		$id->getBinary();
	}
}

$maintClass = BenchUuidConversions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
