<?php

namespace CirrusSearch\Search;

use Elastica\ResultSet as ElasticaResultSet;

/**
 * Lightweight classes to describe specific result types we can return.
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
interface ResultsType {
	/**
	 * Get the source filtering to be used loading the result.
	 *
	 * @return false|string|array corresponding to Elasticsearch source filtering syntax
	 */
	public function getSourceFiltering();

	/**
	 * Get the fields to load.  Most of the time we'll use source filtering instead but
	 * some fields aren't part of the source.
	 *
	 * @return array corresponding to Elasticsearch fields syntax
	 */
	public function getStoredFields();

	/**
	 * Get the highlighting configuration.
	 *
	 * @param array $extraHighlightFields configuration for how to highlight regex matches.
	 *  Empty if regex should be ignored.
	 * @return array|null highlighting configuration for elasticsearch
	 */
	public function getHighlightingConfiguration( array $extraHighlightFields );

	/**
	 * @param ElasticaResultSet $resultSet
	 * @return mixed Set of search results, the types of which vary by implementation.
	 */
	public function transformElasticsearchResult( ElasticaResultSet $resultSet );

	/**
	 * @return mixed Empty set of search results
	 */
	public function createEmptyResult();
}
