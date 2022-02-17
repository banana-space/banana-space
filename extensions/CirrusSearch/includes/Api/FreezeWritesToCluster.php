<?php

namespace CirrusSearch\Api;

use CirrusSearch\DataSender;

/**
 * Freeze/thaw writes to the ES cluster. This should *never* be made
 * available in a production environment and is used for browser tests.
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
class FreezeWritesToCluster extends \ApiBase {
	use ApiTrait;

	public function execute() {
		$sender = new DataSender( $this->getCirrusConnection(), $this->getSearchConfig() );

		if ( $this->getParameter( 'thaw' ) ) {
			// Allow writes to the elasticsearch cluster.
			$sender->thawIndexes();
		} else {
			// When thaw param not provided writes will be frozen.
			$sender->freezeIndexes( 'frozen via api' );
		}
	}

	public function getAllowedParams() {
		return [
			'thaw' => []
		];
	}
}
