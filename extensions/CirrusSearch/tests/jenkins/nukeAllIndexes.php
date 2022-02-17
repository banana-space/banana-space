<?php

namespace CirrusSearch\Jenkins;

use CirrusSearch\Maintenance\Maintenance;

/**
 * Removes all indexes from the Elasticsearch cluster so Jenkins' Elasticsearch
 * won't get full!
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
require_once __DIR__ . '/../../includes/Maintenance/Maintenance.php';

class NukeAllIndexes extends Maintenance {
	public function execute() {
		global $wgCirrusSearchDevelOptions;

		if ( !isset( $wgCirrusSearchDevelOptions['allow_nuke'] ) || !$wgCirrusSearchDevelOptions['allow_nuke'] ) {
			$this->output( "Nuke not enabled. Please set \$wgCirrusSearchDevelOptions['allow_nuke'] = true" );
			return;
		}

		$client = $this->getConnection()->getClient();
		foreach ( $client->getStatus()->getIndexNames() as $index ) {
			$this->output( "Deleting $index..." );
			$client->getIndex( $index )->delete();
			$this->output( "done\n" );
		}
	}
}

$maintClass = NukeAllIndexes::class;
require_once RUN_MAINTENANCE_IF_MAIN;
