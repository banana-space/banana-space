<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\DataSender;

/**
 * Freeze/thaw writes to the elasticsearch cluster. This effects all wikis in a
 * multi-wiki environment.
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
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class FreezeWritesToCluster extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Freeze/thaw writes to the elasticsearch cluster. This effects "
			. "all wikis in a multi-wiki environment. This always operates on a single cluster." );
		$this->addOption( 'thaw', 'Re-allow writes to the elasticsearch cluster.' );
	}

	public function execute() {
		$sender = new DataSender( $this->getConnection(), $this->getSearchConfig() );
		if ( $this->hasOption( 'thaw' ) ) {
			$sender->thawIndexes();
			$this->output( "Thawed any existing cluster-wide freeze\n\n" );
		} else {
			// Any additional input is considered the 'reason'
			$reason = implode( ' ', $this->mArgs );
			$sender->freezeIndexes( $reason );
			$this->output( "Applied cluster-wide freeze\n\n" );
		}

		return true;
	}
}

$maintClass = FreezeWritesToCluster::class;
require_once RUN_MAINTENANCE_IF_MAIN;
