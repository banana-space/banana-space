<?php
/**
 * Counts the number of fancy captchas remaining.
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
 *
 * @file
 * @ingroup Maintenance
 */
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script that counts the number of captchas remaining.
 *
 * @ingroup Maintenance
 */
class CountFancyCaptchas extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Counts the number of fancy aptchas in storage" );
		$this->requireExtension( "FancyCaptcha" );
	}

	public function execute() {
		$instance = ConfirmEditHooks::getInstance();
		if ( !( $instance instanceof FancyCaptcha ) ) {
			$this->fatalError( "\$wgCaptchaClass is not FancyCaptcha.\n", 1 );
		}

		$countAct = $instance->getCaptchaCount();
		$this->output( "Current number of captchas is $countAct.\n" );
	}
}

$maintClass = CountFancyCaptchas::class;
require_once RUN_MAINTENANCE_IF_MAIN;
