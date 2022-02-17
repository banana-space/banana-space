<?php

namespace CirrusSearch\Api;

use ApiMain;
use CirrusSearch\Profile\SearchProfileOverride;
use CirrusSearch\Profile\SearchProfileService;

/**
 * Dumps CirrusSearch profiles for easy viewing.
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
class ProfilesDump extends \ApiBase {
	use ApiTrait;

	/** @var SearchProfileService */
	private $service;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 * @param SearchProfileService|null $service
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '', SearchProfileService $service = null ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->service = $service ?: $this->getSearchConfig()->getProfileService();
	}

	public function execute() {
		$service = $this->service;
		$verbose = $this->getParameter( 'verbose' );
		$contexts = [];
		foreach ( $service->listProfileTypes() as $type ) {
			foreach ( $service->listProfileRepositories( $type ) as $repository ) {
				$this->getResult()->addValue( [ 'profiles', $repository->repositoryType(), 'repositories' ], $repository->repositoryName(),
					$verbose ? $repository->listExposedProfiles() : array_keys( $repository->listExposedProfiles() ) );
			}
			foreach ( $service->listProfileContexts( $type ) as $context => $default ) {
				$this->getResult()->addValue( [ 'profiles', $type, 'contexts', $context ], 'code_default', $default );
				$this->getResult()->addValue( [ 'profiles', $type, 'contexts', $context ], 'actual_default',
					$service->getProfileName( $type, $context, [] ) );
				$overriders = $service->listProfileOverrides( $type, $context );
				usort( $overriders, function ( SearchProfileOverride $a, SearchProfileOverride $b ) {
					return $a->priority() <=> $b->priority();
				} );
				$overriders = array_map( function ( SearchProfileOverride $o ) {
					return $o->explain();
				}, $overriders );
				$this->getResult()->addValue( [ 'profiles', $type, 'contexts', $context ], 'overriders', $overriders );
				if ( !isset( $contexts[$context] ) ) {
					$contexts[$context] = [];
				}
				$contexts[$context][] = $type;
			}
		}

		foreach ( $contexts as $context => $types ) {
			foreach ( $types as $type ) {
				$this->getResult()->addValue( [ 'contexts', $context ], $type, $service->getProfileName( $type, $context, [] ) );
			}
		}
	}

	public function getAllowedParams() {
		return [
			'verbose' => [
				self::PARAM_DFLT => false,
				self::PARAM_HELP_MSG => 'apihelp-cirrus-profiles-dump-param-verbose',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=cirrus-profiles-dump' =>
				'apihelp-cirrus-profiles-dump-example'
		];
	}

}
