<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Script;
use Elastica\Query\Term;

/**
 * Test for filter utilities.
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
 * @covers \CirrusSearch\Search\Filters
 */
class FiltersTest extends CirrusTestCase {
	/**
	 * @dataProvider unifyTestCases
	 */
	public function testUnify( $expected, $mustFilters, $mustNotFilters ) {
		if ( !is_array( $mustFilters ) ) {
			$mustFilters = [ $mustFilters ];
		}
		if ( !is_array( $mustNotFilters ) ) {
			$mustNotFilters = [ $mustNotFilters ];
		}
		$this->assertEquals( $expected, Filters::unify( $mustFilters, $mustNotFilters ) );
	}

	public static function unifyTestCases() {
		$scriptOne = new Script( 'dummy1' );
		$scriptTwo = new Script( 'dummy2' );
		$scriptThree = new Script( 'dummy3' );
		$foo = new Term( [ 'test' => 'foo' ] );
		$bar = new Term( [ 'test' => 'bar' ] );
		$baz = new Term( [ 'test' => 'baz' ] );

		$notScriptOne = new BoolQuery();
		$notScriptOne->addMustNot( $scriptOne );
		$notScriptThree = new BoolQuery();
		$notScriptThree->addMustNot( $scriptThree );
		$notFoo = new BoolQuery();
		$notFoo->addMustNot( $foo );

		return [
			'empty input gives empty output' => [ null, [], [] ],
			'a single must script returns itself' => [ $scriptOne, $scriptOne, [] ],
			'a single must not script returns bool mustNot' => [ $notScriptOne, [], $scriptOne ],
			'a single must query returns itself' => [ $foo, $foo, [] ],
			'a single must not query return bool mustNot' => [ $notFoo, [], $foo ],
			'multiple must return bool must' => [
				self::newBool( [ $foo, $bar ], [] ),
				[ $foo, $bar ],
				[]
			],
			'multiple must not' => [
				self::newBool( [], [ $foo, $bar ] ),
				[],
				[ $foo, $bar ],
			],
			'must and multiple must not' => [
				self::newBool( [ $baz ], [ $foo, $bar ] ),
				[ $baz ],
				[ $foo, $bar ],
			],
			'must and multiple must not with a filtered script' => [
				self::newAnd(
					self::newBool( [ $baz ], [ $foo, $bar ] ),
					$scriptOne
				),
				[ $scriptOne, $baz ],
				[ $foo, $bar ],
			],
			'must and multiple must not with multiple filtered scripts' => [
				self::newAnd(
					self::newBool( [ $baz ], [ $foo, $bar ] ),
					$scriptOne,
					$scriptTwo,
					$notScriptThree
				),
				[ $scriptOne, $baz, $scriptTwo ],
				[ $foo, $scriptThree, $bar ],
			],
		];
	}

	/**
	 * Convenient helper for building bool filters.
	 * @param AbstractQuery|AbstractQuery[] $must must filters
	 * @param AbstractQuery|AbstractQuery[] $mustNot must not filters
	 * @return BoolQuery a bool filter containing $must and $mustNot
	 */
	private static function newBool( $must, $mustNot ) {
		$bool = new BoolQuery();
		if ( is_array( $must ) ) {
			foreach ( $must as $m ) {
				$bool->addMust( $m );
			}
		} else {
			$bool->addMust( $must );
		}
		if ( is_array( $mustNot ) ) {
			foreach ( $mustNot as $m ) {
				$bool->addMustNot( $m );
			}
		} else {
			$bool->addMustNot( $mustNot );
		}

		return $bool;
	}

	private static function newAnd( /* args */ ) {
		$and = new BoolQuery();
		foreach ( func_get_args() as $query ) {
			$and->addFilter( $query );
		}
		return $and;
	}
}
