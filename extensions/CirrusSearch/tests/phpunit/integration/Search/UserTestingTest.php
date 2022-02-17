<?php

namespace CirrusSearch;

/**
 * Make sure cirrus doens't break any hooks.
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
 *
 * @group CirrusSearch
 * @covers \CirrusSearch\UserTesting
 */
class UserTestingTest extends CirrusIntegrationTestCase {
	public function setUp() : void {
		parent::setUp();
		Util::resetExecutionId();
		UserTesting::resetInstance();
	}

	public function testParticipationInTest() {
		$config = $this->config( 'test' );
		$ut = $this->ut( $config, true );
		$this->assertTrue( $ut->isParticipatingIn( 'test' ) );
		$ut = $this->ut( $config, false );
		$this->assertFalse( $ut->isParticipatingIn( 'test' ) );
	}

	// There is no way to run this test correctly...random values mean
	// best we can do is measure distribution over a decent sample size
	public function testSamplesFairlyWithDefaultCallback() {
		$mockReq = $this->getMockBuilder( \WebRequest::class )
			->disableOriginalConstructor()
			->getMock();
		$mockReq->expects( $this->any() )
			->method( 'getIP' )
			->will( $this->returnCallback( function () {
				return mt_rand();
			} ) );
		$mockReq->expects( $this->any() )
			->method( 'getHeader' )
			->will( $this->returnCallback( function () {
				return mt_rand();
			} ) );

		\RequestContext::getMain()->setRequest( $mockReq );

		$config = $this->config( 'test', 3 );
		$samples = 3000;
		$expected = $samples / $config['test']['sampleRate'];
		$expectedPerBucket = $expected / count( $config['test']['buckets'] );
		$allowedError = 0.25;
		$buckets = [];
		for ( $i = 0; $i < $samples; ++$i ) {
			$ut = new UserTesting( $config );
			if ( $ut->isParticipatingIn( 'test' ) ) {
				$bucket = $ut->getBucket( 'test' );
				if ( isset( $buckets[$bucket] ) ) {
					$buckets[$bucket]++;
				} else {
					$buckets[$bucket] = 1;
				}
			}
		}
		unset( $buckets[''] );
		$participants = array_sum( $buckets );
		$this->assertGreaterThan( $expected * ( 1 - $allowedError ), $participants );
		$this->assertLessThan( $expected * ( 1 + $allowedError ), $participants );
		foreach ( $buckets as $bucket => $participants ) {
			$this->assertGreaterThan( $expectedPerBucket * ( 1 - $allowedError ), $participants );
			$this->assertLessThan( $expectedPerBucket * ( 1 + $allowedError ), $participants );
		}
	}

	public function testListsTestsCurrentlyParticipatingIn() {
		$config = $this->config( [ 'test', 'foo', 'bar' ] );
		$ut = $this->ut( $config, true );
		$this->assertEquals( [ 'test', 'foo', 'bar' ], $ut->getActiveTestNames() );
		$ut = $this->ut( $config, [ false, true, true ] );
		$this->assertEquals( [ 'foo', 'bar' ], $ut->getActiveTestNames() );
	}

	public function testActiveTestOverridesGlobalVariables() {
		$config = $this->config( 'test', 10, [
			'wgCirrusSearchRescoreProfile' => 'test',
			'dontsetthisvariable' => true,
		] );

		$this->setMwGlobals( 'wgCirrusSearchRescoreProfile', 'global' );
		$this->ut( $config, true );
		$this->assertEquals( 'test', $GLOBALS['wgCirrusSearchRescoreProfile'] );
		$this->assertArrayNotHasKey( 'dontsetthisvariable', $GLOBALS );
		$this->setMwGlobals( 'wgCirrusSearchRescoreProfile', 'global' );
		$this->ut( $config, false );
		$this->assertEquals( 'global', $GLOBALS['wgCirrusSearchRescoreProfile'] );
	}

	public function testDoesNotReinitializeFromGetInstance() {
		$this->setMwGlobals( [
			'wgCirrusSearchUserTesting' => $this->config( 'test', 10, [
				'wgCirrusSearchRescoreProfile' => 'test',
			] ),
			'wgCirrusSearchRescoreProfile' => 'global',
		] );
		UserTesting::getInstance(
			function () {
				return true;
			}
		);
		$this->assertEquals( 'test', $GLOBALS['wgCirrusSearchRescoreProfile'] );
		$GLOBALS['wgCirrusSearchRescoreProfile'] = 'global';
		UserTesting::getInstance(
			function () {
				return true;
			}
		);
		$this->assertEquals( 'global', $GLOBALS['wgCirrusSearchRescoreProfile'] );
	}

	public function testPerBucketGlobalsOverridePerTestGlobals() {
		$this->setMwGlobals( 'wgCirrusSearchRescoreProfile', 'global' );
		$config = $this->config( 'test', 10, [
			'wgCirrusSearchRescoreProfile' => 'test',
		] );
		$config['test']['buckets']['a']['globals']['wgCirrusSearchRescoreProfile'] = 'bucket';
		$config['test']['buckets']['b']['globals']['wgCirrusSearchRescoreProfile'] = 'bucket';

		$this->ut( $config, true );
		$this->assertEquals( 'bucket', $GLOBALS['wgCirrusSearchRescoreProfile'] );
	}

	public function providerChooseBucket() {
		return [
			[ 'a', 0, [ 'a', 'b', 'c' ] ],
			[ 'a', 0, [ 'a', 'b', 'c', 'd' ] ],
			[ 'a', 0.24, [ 'a', 'b', 'c', 'd' ] ],
			[ 'a', 0.25, [ 'a', 'b', 'c', 'd' ] ],
			[ 'b', 0.26, [ 'a', 'b', 'c', 'd' ] ],
			[ 'b', 0.49, [ 'a', 'b', 'c', 'd' ] ],
			[ 'b', 0.50, [ 'a', 'b', 'c', 'd' ] ],
			[ 'c', 0.51, [ 'a', 'b', 'c', 'd' ] ],
			[ 'd', 1, [ 'a', 'b', 'c', 'd' ] ],
		];
	}

	/**
	 * @dataProvider providerChooseBucket
	 */
	public function testChooseBucket( $expect, $probability, array $buckets ) {
		$this->assertEquals( $expect, UserTesting::chooseBucket( $probability, $buckets ) );
	}

	public function testTrigger() {
		$config = [
			'someTest' => [
				'buckets' => [
					'a' => [
						'trigger' => 'hi there',
					],
					'b' => [
						'trigger' => 'or this one',
					],
				],
			],
		];

		$req = new \FauxRequest( [ 'cirrusUserTesting' => 'hi there' ] );
		$this->setMwGlobals( 'wgCirrusSearchUserTesting', $config );
		\RequestContext::getMain()->setRequest( $req );
		$this->assertEquals( [ 'someTest:a' ], UserTesting::getInstance()->getActiveTestNamesWithBucket() );

		$ut = new UserTesting( $config, null, 'hi there' );
		$this->assertEquals( [ 'someTest:a' ], $ut->getActiveTestNamesWithBucket() );

		$ut = new UserTesting( $config, null, 'or this one' );
		$this->assertEquals( [ 'someTest:b' ], $ut->getActiveTestNamesWithBucket() );
	}

	protected function config( $testNames, $sampleRate = 10, $globals = [] ) {
		if ( $globals ) {
			$globals = [ 'globals' => $globals ];
		}
		$config = [];
		foreach ( (array)$testNames as $name ) {
			$config[$name] = $globals + [
				'sampleRate' => $sampleRate,
				'buckets' => [
					'a' => [],
					'b' => [],
				],
			];
		}
		return $config;
	}

	protected function ut( $config, $callback ) {
		if ( is_array( $callback ) ) {
			// reverse so pop in reverse order
			$retvals = array_reverse( $callback );
			$callback = function () use ( &$retvals ) {
				$retval = array_pop( $retvals );
				return $retval ? mt_rand( 0, mt_getrandmax() ) / mt_getrandmax() : 0;
			};
		} elseif ( is_bool( $callback ) ) {
			$retval = $callback;
			$callback = function () use ( $retval ) {
				return $retval ? mt_rand( 0, mt_getrandmax() ) / mt_getrandmax() : 0;
			};
		}
		return new UserTesting( $config, $callback );
	}
}
