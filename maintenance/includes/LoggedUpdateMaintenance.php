<?php
/**
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
 *
 * @file
 */

/**
 * Class for scripts that perform database maintenance and want to log the
 * update in `updatelog` so we can later skip it
 * @ingroup Maintenance
 */
abstract class LoggedUpdateMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'force', 'Run the update even if it was completed already' );
		$this->setBatchSize( 200 );
	}

	public function execute() {
		$db = $this->getDB( DB_MASTER );
		$key = $this->getUpdateKey();

		if ( !$this->hasOption( 'force' )
			&& $db->selectRow( 'updatelog', '1', [ 'ul_key' => $key ], __METHOD__ )
		) {
			$this->output( "..." . $this->updateSkippedMessage() . "\n" );

			return true;
		}

		if ( !$this->doDBUpdates() ) {
			return false;
		}

		$db->insert( 'updatelog', [ 'ul_key' => $key ], __METHOD__, [ 'IGNORE' ] );

		return true;
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 * @return string
	 */
	protected function updateSkippedMessage() {
		$key = $this->getUpdateKey();

		return "Update '{$key}' already logged as completed. Use --force to run it again.";
	}

	/**
	 * Do the actual work. All child classes will need to implement this.
	 * Return true to log the update as done or false (usually on failure).
	 * @return bool
	 */
	abstract protected function doDBUpdates();

	/**
	 * Get the update key name to go in the update log table
	 * @return string
	 */
	abstract protected function getUpdateKey();
}
