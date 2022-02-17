<?php
/**
 * Implementation of "sltr" query from ltr-query plugin
 *
 * @link https://github.com/o19s/elasticsearch-learning-to-rank
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

namespace CirrusSearch\Elastica;

use Elastica\Query\AbstractQuery;

class LtrQuery extends AbstractQuery {
	/**
	 * @param string $model The stored model to use
	 * @param string[] $params map of string -> string containing templating
	 *  parameters for ltr features.
	 */
	public function __construct( $model, array $params ) {
		$this->setModel( $model )
			->setLtrParams( $params );
	}

	/**
	 * @return string
	 */
	protected function _getBaseName() {
		return "sltr";
	}

	/**
	 * The stored model to use.
	 *
	 * @param string $model
	 * @return self
	 */
	public function setModel( $model ) {
		$this->setParam( 'model', $model );

		return $this;
	}

	/**
	 * The name of the feature store to find the model in.
	 *
	 * @param string $store
	 * @return self
	 */
	public function setStore( $store ) {
		$this->setParam( 'store', $store );

		return $this;
	}

	/**
	 * Add a parameter used for templated features
	 *
	 * @param string $key
	 * @param string $value
	 * @return self
	 */
	public function addLtrParam( $key, $value ) {
		$this->_params['params'][$key] = $value;

		return $this;
	}

	/**
	 * Set all parameters used for templated features.
	 *
	 * @param string[] $params map of string -> string containing templating
	 *  parameters for ltr features.
	 * @return self
	 */
	public function setLtrParams( array $params ) {
		$this->setParam( 'params', $params );

		return $this;
	}
}
