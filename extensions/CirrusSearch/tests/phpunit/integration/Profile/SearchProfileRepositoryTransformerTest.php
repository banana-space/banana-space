<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;

/**
 * @covers \CirrusSearch\Profile\SearchProfileRepositoryTransformer
 * @covers \CirrusSearch\Profile\ArrayPathSetter
 * @group CirrusSearch
 */
class SearchProfileRepositoryTransformerTest extends CirrusIntegrationTestCase {

	public function provideRepositories() {
		$tests = [
			'simple' => [
				[ 'prof1' => [ 'replace' => 'me' ] ],
				[ 'replace' => 'replaced' ],
				[ 'prof1' => [ 'replace' => 'replaced' ] ],
			],
			'simple nothing replaced' => [
				[ 'prof1' => [ 'replace' => 'me' ] ],
				[ 'notfound' => 'replaced' ],
				[ 'prof1' => [ 'replace' => 'me' ] ],
			],
			'multiple replacement' => [
				[ 'prof1' => [
					'replace' => 'me',
					'and' => [
						'replace' => [ 'me' ]
					]
				] ],
				[
					'replace' => 'replaced',
					'and.replace' => 'types do not matter'
				],
				[ 'prof1' => [
					'replace' => 'replaced',
					'and' => [
						'replace' => 'types do not matter'
					]
				] ],
			],
			'lookahead with wildcard' => [
				[ 'prof1' => [
					[
						'field' => 'one',
						'boost' => 1.0,
					],
					[
						'field' => 'two',
						'boost' => 1.0,
					]
				] ],
				[
					'*[field=two].boost' => 2.0,
				],
				[ 'prof1' => [
					[
						'field' => 'one',
						'boost' => 1.0,
					],
					[
						'field' => 'two',
						'boost' => 2.0,
					]
				] ],
			],
			'lookahead with wildcard and automatic type conversion' => [
				[ 'prof1' => [
					[
						'field' => 'one',
						'boost' => 2.0,
					],
					[
						'field' => 'two',
						'boost' => 1.0,
					]
				] ],
				[
					'*[boost=2].boost' => 3.0,
				],
				[ 'prof1' => [
					[
						'field' => 'one',
						'boost' => 3.0,
					],
					[
						'field' => 'two',
						'boost' => 1.0,
					]
				] ],
			],
			'lookahead assertion' => [
				[
					'prof1' => [
						'query' => [
							'field' => 'one',
							'boost' => 1.0,
						]
					],
					'prof2' => [
						'query' => [
							'field' => 'two',
							'boost' => 1.0,
						]
					]
				],
				[
					'query[field=two].boost' => 2.0,
				],
				[
					'prof1' => [
						'query' => [
							'field' => 'one',
							'boost' => 1.0,
						]
					],
					'prof2' => [
						'query' => [
							'field' => 'two',
							'boost' => 2.0,
						]
					]
				],
			],
			'lookahead last assertion' => [
				[
					'prof1' => [
						'query' => [
							'fields' => 'placeholder',
							'boost' => 1.0,
						]
					],
					'prof2' => [
						'query' => [
							'fields' => [ 'field1', 'field2' ],
							'boost' => 1.0,
						]
					]
				],
				[
					'query[fields=placeholder].fields' => [ 'field' ],
				],
				[
					'prof1' => [
						'query' => [
							'fields' => [ 'field' ],
							'boost' => 1.0,
						]
					],
					'prof2' => [
						'query' => [
							'fields' => [ 'field1', 'field2' ],
							'boost' => 1.0,
						]
					]
				],
			]
		];
		foreach ( $tests as $name => $testCase ) {
			$bcTestCase = array_merge( $testCase, [ true ] );
			$tests[$name . '_bc'] = $bcTestCase;
		}
		return $tests;
	}

	/**
	 * @dataProvider provideRepositories
	 * @param array $profiles
	 * @param array $replacements
	 * @param array $expectedProfiles
	 * @param array $testBcConstructor
	 */
	public function test( $profiles, $replacements, $expectedProfiles, $testBcConstructor = false ) {
		if ( $testBcConstructor ) {
			$transformer = $replacements;
		} else {
			$transformer = new ArrayPathSetter( $replacements );
		}
		$repo = new SearchProfileRepositoryTransformer(
			ArrayProfileRepository::fromArray( 'my_type', 'my_name', $profiles ),
			$transformer
		);
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_name', $repo->repositoryName() );
		$this->assertArrayEquals( $expectedProfiles, $repo->listExposedProfiles() );
		foreach ( $expectedProfiles as $name => $profile ) {
			$this->assertArrayEquals( $profile, $repo->getProfile( $name ) );
			$this->assertTrue( $repo->hasProfile( $name ) );
		}
	}

	public function provideBadReplacements() {
		return [
			'empty' => [ '' ],
			'start with dot' => [ '.df' ],
			'unbalanced bracket' => [ 'df[' ],
			'wrong assertion' => [ 'df[test]' ],
			'wrong type' => [ 0 ],
		];
	}

	/**
	 * @dataProvider provideBadReplacements
	 */
	public function testBadSyntax( $badRepl ) {
		$transformer = new ArrayPathSetter( [ $badRepl => '' ] );
		$repo = new SearchProfileRepositoryTransformer(
			ArrayProfileRepository::fromArray( 'my_type', 'my_name', [ 'hop' => [] ] ),
			$transformer
		);
		$this->expectException( SearchProfileException::class );
		$repo->getProfile( 'hop' );
	}
}
