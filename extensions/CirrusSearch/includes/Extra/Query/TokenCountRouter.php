<?php

namespace CirrusSearch\Extra\Query;

use Elastica\Query\AbstractQuery;

/**
 * TokenCountRouter query used to trigger a particular query by counting
 * the number of tokens in the user query.
 *
 * @link https://github.com/wikimedia/search-extra/blob/master/docs/token_count_router.md
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

class TokenCountRouter extends AbstractQuery {
	/**
	 * @const string greater than
	 */
	const GT = 'gt';

	/**
	 * @const string greater or equal
	 */
	const GTE = 'gte';

	/**
	 * @const string equal
	 */
	const EQ = 'eq';

	/**
	 * @const string not equal
	 */
	const NEQ = 'neq';

	/**
	 * @const string less than or equal
	 */
	const LTE = 'lte';

	/**
	 * @const string less than
	 */
	const LT = 'lt';

	/**
	 * @param string $text the text to analyze
	 * @param AbstractQuery $fallbackQuery the query to run when no
	 * conditions match
	 * @param string|null $field use the analyzer of this field
	 * @param string|null $analyzer use this analyzer
	 */
	public function __construct( $text, AbstractQuery $fallbackQuery, $field = null, $analyzer = null ) {
		$this->setText( $text );
		$this->setFallback( $fallbackQuery );
		if ( $field ) {
			$this->setField( $field );
		}
		if ( $analyzer ) {
			$this->setAnalyzer( $analyzer );
		}
	}

	/**
	 * @param string $text count tokens from this text
	 * @return self
	 */
	public function setText( $text ) {
		return $this->setParam( 'text', $text );
	}

	/**
	 * @param AbstractQuery $query
	 * @return self
	 */
	public function setFallback( AbstractQuery $query ) {
		return $this->setParam( 'fallback', $query );
	}

	/**
	 * @param string $field the field to fetch analyzer info
	 * @return self
	 */
	public function setField( $field ) {
		return $this->setParam( 'field', $field );
	}

	/**
	 * @param string $analyzer the field to fetch analyzer info
	 * @return self
	 */
	public function setAnalyzer( $analyzer ) {
		return $this->setParam( 'analyzer', $analyzer );
	}

	/**
	 * Adds a new condition
	 * The first condition that evaluates to true is applied.
	 * If none match the fallback query is applied.
	 *
	 * @param string $type the condition to apply
	 * @param int $value the value to compare
	 * @param AbstractQuery $query the query to run if the condition is
	 * true ignoring all remaining conditions
	 * @return self
	 */
	public function addCondition( $type, $value, AbstractQuery $query ) {
		switch ( $type ) {
		case self::GT:
		case self::GTE:
		case self::EQ:
		case self::NEQ:
		case self::LT:
		case self::LTE:
			break;
		default:
			throw new \InvalidArgumentException( "$type is not allowed as a condition type" );
		}
		return $this->addParam( 'conditions', [
			$type => $value,
			'query' => $query,
		] );
	}
}
