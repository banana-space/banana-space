<?php

namespace CirrusSearch;

/**
 * Handles decisions around if the current request is a member of any
 * test currently being run. This initial implementation is per-request
 * with a consistent bucketing scheme that keeps the user in the same
 * test over multiple requests when no trigger is provided.
 *
 * $wgCirrusSearchUserTesting = array(
 *     'someTest' => array(
 *         'sampleRate' => 100, // sample 1 in 100 occurrences
 *         'buckets' => array(
 *             'a' => array(
 *                 // control bucket, retain defaults
 *             ),
 *             'b' => array(
 *                 'globals' => array(
 *                     'wgCirrusSearchRescoreProfile' => 'classic',
 *                 ),
 *             ),
 *             ...
 *         ),
 *     ,
 *     ...
 * );
 *
 * Per test configuration options:
 *
 * * sampleRate - A number >= 1 that specifies the sampling rate of the tests.
 *                1 in sampleRate requests will participate in the test. If a trigger
 *                is provided by the request this will not be checked. A sampleRate of
 *                0 can be provided to allow only triggered inclusion.
 * * globals    - A map from global variable name to value to set for all requests
 *                participating in the test.
 * * buckets    - A map from bucket name to bucket configuration.
 *
 * Per bucket configuration options:
 *
 * * globals    - A map from global variable name to value to set for all requests
 *                in this bucket. Per-bucket globals override per-test globals.
 * * trigger    - Provide this value to allow inclusion in the bucket based on the
 *                cirrusUserTesting query parameter.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class UserTesting {
	/**
	 * @var UserTesting|null Shared instance of this class configured with
	 *  $wgCirrusSearchUserTesting
	 */
	private static $instance;

	/**
	 * @var string[] Map from test name to the bucket the request is in.
	 */
	protected $tests = [];

	/**
	 * Returns a stable instance based on $wgCirrusSearchUserTesting
	 * global configuration and the trigger from the main request context.
	 *
	 * @param callable|null $callback
	 * @param string|null $trigger
	 * @return self
	 */
	public static function getInstance( $callback = null, $trigger = null ) {
		// TODO: stop relying on globals
		global $wgCirrusSearchUserTesting;
		if ( self::$instance === null ) {
			$userTestingConfig = $wgCirrusSearchUserTesting ?? [];
			if ( $trigger === null ) {
				$trigger = \RequestContext::getMain()->getRequest()
					->getVal( 'cirrusUserTesting' );
			}
			self::$instance = new self( $userTestingConfig, $callback, $trigger );
		}
		return self::$instance;
	}

	/**
	 * Unit test only
	 */
	public static function resetInstance() {
		self::$instance = null;
	}

	/**
	 * @param array[] $config
	 * @param callable|null $callback
	 * @param string|null $trigger Value to manually trigger a test.
	 */
	public function __construct( array $config, $callback = null, $trigger = '' ) {
		if ( $callback === null ) {
			$callback = [ __CLASS__, 'oneIn' ];
		}
		foreach ( $config as $testName => $testConfig ) {
			if ( $trigger ) {
				foreach ( $testConfig['buckets'] as $bucket => $bucketConfig ) {
					'@phan-var array $bucketConfig';
					if ( isset( $bucketConfig['trigger'] ) && $bucketConfig['trigger'] === $trigger ) {
						$this->activateTest( $testName, $bucket, $testConfig );
						break;
					}
				}
			} elseif ( isset( $testConfig['sampleRate'] ) && $testConfig['sampleRate'] > 0 ) {
				$bucketProbability = call_user_func( $callback, $testName, $testConfig['sampleRate'] );
				if ( $bucketProbability > 0 ) {
					$this->activateTest( $testName, $bucketProbability, $testConfig );
				}
			}
		}
	}

	/**
	 * @param string $testName Name of a test being run
	 * @return bool True when the request is participating in the named test
	 */
	public function isParticipatingIn( $testName ) {
		return isset( $this->tests[$testName] );
	}

	/**
	 * @param string $testName Name of a test being run
	 * @return string The bucket the request has been placed in for the named
	 *  test. If the request is not participating in the test the bucket will
	 *  be the empty string.
	 */
	public function getBucket( $testName ) {
		return $this->tests[$testName] ?? '';
	}

	/**
	 * @return string[] List of tests that are active for the current request.
	 */
	public function getActiveTestNames() {
		return array_keys( $this->tests );
	}

	/**
	 * @return string[]
	 */
	public function getActiveTestNamesWithBucket() {
		$result = [];
		foreach ( $this->tests as $test => $bucket ) {
			$result[] = "$test:$bucket";
		}
		return $result;
	}

	/**
	 * @param string $testName Name of the test to activate.
	 * @param float|string $bucket Either the name of a bucket, or a number
	 *  between 0 and 1 for determining bucket.
	 * @param array $testConfig Configuration of the test to activate.
	 */
	protected function activateTest( $testName, $bucket, array $testConfig ) {
		$this->tests[$testName] = '';
		$globals = [];
		if ( isset( $testConfig['globals'] ) ) {
			$globals = $testConfig['globals'];
		}
		if ( isset( $testConfig['buckets'] ) ) {
			if ( !is_string( $bucket ) ) {
				$bucket = $this->chooseBucket( $bucket, array_keys( $testConfig['buckets'] ) );
			}
			$this->tests[$testName] = $bucket;
			if ( isset( $testConfig['buckets'][$bucket]['globals'] ) ) {
				$globals = array_merge( $globals, $testConfig['buckets'][$bucket]['globals'] );
			}
		}

		foreach ( $globals as $key => $value ) {
			if ( array_key_exists( $key, $GLOBALS ) ) {
				$GLOBALS[$key] = $value;
			}
		}
	}

	/**
	 * @param float $probability A number between 0 and 1
	 * @param string[] $buckets List of buckets to choose from.
	 * @return string The chosen bucket.
	 */
	public static function chooseBucket( $probability, $buckets ) {
		$num = count( $buckets );
		$each = 1 / $num;
		$current = 0;
		foreach ( $buckets as $bucket ) {
			$current += $each;
			if ( $current >= $probability ) {
				return $bucket;
			}
		}
		// >= should ensure we never get here,
		// unless probability > 1
		return end( $buckets );
	}

	/**
	 * Converts a hex string into a probability between 0 and 1.
	 * Retains uniform distribution of incoming hash string.
	 *
	 * @param string $hash
	 * @return float Probability between 0 and 1
	 */
	public static function hexToProbability( $hash ) {
		if ( strlen( $hash ) === 0 ) {
			throw new \RuntimeException( 'Empty hash provided' );
		}
		$len = strlen( $hash );
		$sum = 0;
		for ( $i = 0; $i < $len; $i += 4 ) {
			$piece = substr( $hash, $i, 4 );
			$dec = hexdec( $piece );
			// xor will retain the uniform distribution
			$sum = $sum ^ $dec;
		}
		return $sum / ( ( 1 << 16 ) - 1 );
	}

	/**
	 * @param string $testName
	 * @param int $sampleRate
	 * @return float for 1 in $sampleRate calls to this method
	 *  returns a stable probability between 0 and 1. for all other
	 *  requests returns 0.
	 */
	public static function oneIn( $testName, $sampleRate ) {
		$hash = Util::generateIdentToken( $testName );
		$probability = self::hexToProbability( $hash );
		$rateThreshold = 1 / $sampleRate;
		if ( $rateThreshold >= $probability ) {
			return $probability / $rateThreshold;
		}
		return 0;
	}
}
