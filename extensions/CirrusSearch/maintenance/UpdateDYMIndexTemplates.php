<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\Profile\SearchProfileService;

/**
 * Update the search configuration on the search backend.
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

/**
 * Update the elasticsearch configuration for this index.
 */
class UpdateDYMIndexTemplates extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( "Update the template for index lookup DYM fallback profiles." .
							   "operates on a single cluster." );
		$this->addOption( 'profile', 'Extract the template from this profile.', false, true );
	}

	public function execute() {
		$this->disablePoolCountersAndLogging();
		$profiles = [];
		$profileService = $this->getSearchConfig()->getProfileService();
		if ( $this->hasOption( 'profile' ) ) {
			$profileName = $this->getOption( 'profile' );
			$profiles[] = $profileService->loadProfileByName( SearchProfileService::INDEX_LOOKUP_FALLBACK,
				$profileName );
		} else {
			$profiles = $profileService->listExposedProfiles( SearchProfileService::INDEX_LOOKUP_FALLBACK );
		}

		/** @var IndexTemplateBuilder[] $templateBuilders */
		$templateBuilders = [];
		$configUtils = new ConfigUtils( $this->getConnection()->getClient(), $this );
		$availablePlugins = $configUtils->scanAvailablePlugins( $this->getConfig()->get( 'CirrusSearchBannedPlugins' ) );
		foreach ( $profiles as $profileName => $profile ) {
			if ( !isset( $profile['index_template'] ) ) {
				$this->output( "Skipping template for [$profileName], no template definition found." );
				continue;
			}

			try {
				 $templateBuilders[] = IndexTemplateBuilder::build(
					$this->getConnection(),
					$profile['index_template'],
					$availablePlugins
				 );
			} catch ( \InvalidArgumentException $iae ) {
				$this->fatalError( "Cannot load profile [$profileName]: {$iae->getMessage()}" );
			}
		}
		$namesSeen = [];
		foreach ( $templateBuilders as $profileName => $builder ) {
			$seen = $namesSeen[$builder->getTemplateName()] ?? null;
			if ( $seen !== null ) {
				$this->fatalError( "Found duplicate template name [{$builder->getTemplateName()}] " .
					"in profile [$seen] and [$profileName]" );
			}
		}
		foreach ( $templateBuilders as $builder ) {
			$this->output( "Creating template [{$builder->getTemplateName()}]...\n" );
			$builder->execute();
		}
	}
}
$maintClass = UpdateDYMIndexTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
