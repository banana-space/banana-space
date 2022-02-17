<?php

namespace CirrusSearch\Jenkins;

use Maintenance;

/**
 * Deletes pages created by the browser test suite. cleanSetup.php
 * in this jenkins directory runs much faster if it doesn't have to
 * reindex all these old unnecessary pages.
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

class DeleteBrowserTestPages extends Maintenance {
	public function execute() {
		$pattern = implode( '|', [
			'IAmABad RedirectChain',
			'IAmABad RedirectSelf',
			'IDontExistLink',
			'IDontExistRdir',
			'IDontExistRdirLinked',
			'ILinkIRedirectToNonExistentPages',
			'ILinkToNonExistentPages',
			'IRedirectToNonExistentPages',
			'IRedirectToNonExistentPagesLinked',
			'Move',
			'PreferRecent First exists with contents ',
			'PreferRecent Second Second exists with contents ',
			'PreferRecent Third exists with contents ',
			'ReallyLongLink',
			'StartsAsRedirect',
			'ToBeRedirect',
			'WeightedLink',
			'WeightedLinkRdir',
			'WeightedLinkRemoveUpdate',
			'WLDoubleRdir',
			'WLRURdir',
		] );
		$pattern = "/$pattern/";

		$dbw = wfGetDB( DB_MASTER );
		$it = new \BatchRowIterator( $dbw, 'page', 'page_id', 500 );
		$it->setFetchColumns( [ '*' ] );
		$it = new \RecursiveIteratorIterator( $it );

		$user = \User::newSystemUser( 'Maintenance script', [ 'steal' => true ] );
		foreach ( $it as $row ) {
			if ( preg_match( $pattern, $row->page_title ) !== 1 ) {
				continue;
			}
			$title = \Title::newFromRow( $row );
			$pageObj = \WikiPage::factory( $title );
			echo "Deleting page $title\n";
			$pageObj->doDeleteArticleReal( 'cirrussearch maint task', $user );
		}
	}
}

$maintClass = DeleteBrowserTestPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
