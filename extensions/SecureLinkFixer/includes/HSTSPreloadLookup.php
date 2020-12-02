<?php
/**
 * Copyright (C) 2018 Kunal Mehta <legoktm@member.fsf.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace MediaWiki\SecureLinkFixer;

class HSTSPreloadLookup {

	/**
	 * @var array
	 */
	private $domains;

	/**
	 * @todo turn into proper MWServices thing
	 * @codeCoverageIgnore
	 * @return HSTSPreloadLookup
	 */
	public static function getInstance() {
		static $instance;
		if ( !$instance ) {
			$instance = new self( require __DIR__ . '/../domains.php' );
		}

		return $instance;
	}

	/**
	 * @param array $domains
	 */
	public function __construct( array $domains ) {
		$this->domains = $domains;
	}

	/**
	 * @param string $host Hostname
	 *
	 * @return bool
	 */
	public function isPreloaded( $host ) {
		if ( isset( $this->domains[$host] ) ) {
			// Host is directly in the preload list
			return true;
		}
		// Check if parent subdomains are preloaded
		while ( strpos( $host, '.' ) !== false ) {
			$host = preg_replace( '/(.*?)\./', '', $host, 1 );
			$subdomains = $this->domains[$host] ?? false;
			if ( $subdomains === 1 ) {
				return true;
			} elseif ( $subdomains === 0 ) {
				return false;
			}
			// else it's not in the db, we might need to look it up again
		}

		// @todo should we keep a negative cache?

		return false;
	}
}
