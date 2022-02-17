<?php

namespace CirrusSearch\Search;

use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;

/**
 * Utilities for dealing with filters.
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
class Filters {
	/**
	 * Turns a list of queries into a boolean OR, requiring only one
	 * of the provided queries to match.
	 *
	 * @param AbstractQuery[] $queries
	 * @param bool $matchAll When true (default) function never returns null,
	 *  when no queries are provided a MatchAll is returned.
	 * @return AbstractQuery|null The resulting OR query. Only returns null when
	 *  no queries are passed and $matchAll is false.
	 */
	public static function booleanOr( array $queries, $matchAll = true ) {
		if ( !$queries ) {
			return $matchAll ? new MatchAll() : null;
		} elseif ( count( $queries ) === 1 ) {
			return reset( $queries );
		} else {
			$bool = new BoolQuery();
			foreach ( $queries as $query ) {
				$bool->addShould( $query );
			}
			return $bool;
		}
	}

	/**
	 * Merges lists of include/exclude filters into a single filter that
	 * Elasticsearch will execute efficiently.
	 *
	 * @param AbstractQuery[] $mustFilters filters that must match all returned documents
	 * @param AbstractQuery[] $mustNotFilters filters that must not match all returned documents
	 * @return null|AbstractQuery null if there are no filters or one that will execute
	 *     all of the provided filters
	 */
	public static function unify( array $mustFilters, array $mustNotFilters ) {
		// We want to make sure that we execute script filters last.  So we do these steps:
		// 1.  Strip script filters from $must and $mustNot.
		// 2.  Unify the non-script filters.
		// 3.  Build a BoolAnd filter out of the script filters if there are any.
		$scriptFilters = [];
		$nonScriptMust = [];
		$nonScriptMustNot = [];
		foreach ( $mustFilters as $must ) {
			if ( $must->hasParam( 'script' ) ) {
				$scriptFilters[] = $must;
			} else {
				$nonScriptMust[] = $must;
			}
		}
		$scriptMustNotFilter = new BoolQuery();
		foreach ( $mustNotFilters as $mustNot ) {
			if ( $mustNot->hasParam( 'script' ) ) {
				$scriptMustNotFilter->addMustNot( $mustNot );
			} else {
				$nonScriptMustNot[] = $mustNot;
			}
		}
		if ( $scriptMustNotFilter->hasParam( 'must_not' ) ) {
			$scriptFilters[] = $scriptMustNotFilter;
		}

		$nonScript = self::unifyNonScript( $nonScriptMust, $nonScriptMustNot );
		$scriptFiltersCount = count( $scriptFilters );
		if ( $scriptFiltersCount === 0 ) {
			return $nonScript;
		}

		$bool = new BoolQuery();
		if ( $nonScript === null ) {
			if ( $scriptFiltersCount === 1 ) {
				return $scriptFilters[ 0 ];
			}
		} else {
			$bool->addFilter( $nonScript );
		}
		foreach ( $scriptFilters as $scriptFilter ) {
			$bool->addFilter( $scriptFilter );
		}
		return $bool;
	}

	/**
	 * Unify non-script filters into a single filter.
	 *
	 * @param AbstractQuery[] $mustFilters filters that must be found
	 * @param AbstractQuery[] $mustNotFilters filters that must not be found
	 * @return null|AbstractQuery null if there are no filters or one that will execute
	 *     all of the provided filters
	 */
	private static function unifyNonScript( array $mustFilters, array $mustNotFilters ) {
		$mustFilterCount = count( $mustFilters );
		$mustNotFilterCount = count( $mustNotFilters );
		if ( $mustFilterCount + $mustNotFilterCount === 0 ) {
			return null;
		}
		if ( $mustFilterCount === 1 && $mustNotFilterCount == 0 ) {
			return $mustFilters[ 0 ];
		}
		$bool = new BoolQuery();
		foreach ( $mustFilters as $must ) {
			$bool->addMust( $must );
		}
		foreach ( $mustNotFilters as $mustNot ) {
			$bool->addMustNot( $mustNot );
		}
		return $bool;
	}

	/**
	 * Create a query for insource: queries. This function is pure, deferring
	 * state changes to the reference-updating return function.
	 *
	 * @param Escaper $escaper
	 * @param string $value
	 * @return AbstractQuery
	 */
	public static function insource( Escaper $escaper, $value ) {
		return self::insourceOrIntitle( $escaper, $value, function () {
			return [ 'source_text.plain' ];
		} );
	}

	/**
	 * Create a query for intitle: queries.
	 *
	 * @param Escaper $escaper
	 * @param string $value
	 * @return AbstractQuery
	 */
	public static function intitle( Escaper $escaper, $value ) {
		return self::insourceOrIntitle( $escaper, $value, function ( $queryString ) {
			if ( preg_match( '/[?*]/u', $queryString ) ) {
				return [ 'title.plain', 'redirect.title.plain' ];
			} else {
				return [ 'title', 'title.plain', 'redirect.title', 'redirect.title.plain' ];
			}
		} );
	}

	/**
	 * @param Escaper $escaper
	 * @param string $value
	 * @param callable $fieldF
	 * @return AbstractQuery
	 */
	private static function insourceOrIntitle( Escaper $escaper, $value, $fieldF ) {
		$queryString = $escaper->fixupWholeQueryString(
			$escaper->fixupQueryStringPart( $value ) );
		$field = $fieldF( $queryString );
		$query = new \Elastica\Query\QueryString( $queryString );
		$query->setFields( $field );
		$query->setDefaultOperator( 'AND' );
		$query->setAllowLeadingWildcard( $escaper->getAllowLeadingWildcard() );
		$query->setFuzzyPrefixLength( 2 );
		$query->setRewrite( 'top_terms_boost_1024' );

		return $query;
	}
}
