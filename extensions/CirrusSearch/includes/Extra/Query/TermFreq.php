<?php

namespace CirrusSearch\Extra\Query;

use Elastica\Query\AbstractQuery;
use Wikimedia\Assert\Assert;

/**
 * Filtering based on integer comparisons on the frequency of a term
 *
 * @link https://github.com/wikimedia/search-extra/blob/master/docs/term_freq_token_filter.md
 *
 * NOTE: only available if CirrusSearchWikimediaExtraPlugin['term_freq'] is set to true.
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

class TermFreq extends AbstractQuery {

	private static $map = [
		'>' => 'gt',
		'>=' => 'gte',
		'<' => 'lt',
		'<=' => 'lte',
		'=' => 'eq',
	];

	/**
	 * @param string $field The name of the field to search
	 * @param string $term The term to search for
	 * @param string $operator A comparison operator. One of [ '<', '<=', '>', '>=', '=' ]
	 * @param int $number The number to compare against
	 */
	public function __construct( $field, $term, $operator, $number ) {
		Assert::parameter(
			isset( self::$map[$operator] ),
			$operator,
			"operator must be one of " . implode( ', ', array_keys( self::$map ) )
		);
		if ( !empty( $field ) && !empty( $term ) ) {
			$this->setParam( 'field', $field );
			$this->setParam( 'term', $term );
			$this->setParam( self::$map[ $operator ], $number );
		}
	}

}
