<?php

namespace CirrusSearch\Jenkins;

use CirrusSearch\Maintenance\Maintenance;

/**
 * Calls maintenance scripts properly to get an empty and configured index and
 * anything else required for browser tests to pass.
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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../../includes/Maintenance/Maintenance.php";

class CleanSetup extends Maintenance {
	public function execute() {
		$child = $this->runChild( \CirrusSearch\Maintenance\Metastore::class );
		$child->mOptions['upgrade'] = true;
		$child->execute();
		$child = $this->runChild( \CirrusSearch\Maintenance\UpdateSearchIndexConfig::class );
		$child->mOptions[ 'startOver' ] = true;
		$child->execute();
		$child = $this->runChild( \CirrusSearch\Maintenance\ForceSearchIndex::class );
		$child->mOptions[ 'skipLinks' ] = true;
		$child->mOptions[ 'indexOnSkip' ] = true;
		$child->execute();
		$child = $this->runChild( \CirrusSearch\Maintenance\ForceSearchIndex::class );
		$child->mOptions[ 'skipParse' ] = true;
		$child->execute();
	}
}

$maintClass = CleanSetup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
