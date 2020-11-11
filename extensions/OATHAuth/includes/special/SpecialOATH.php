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
 */

/**
 * Proxy page that redirects to the proper OATH special page
 */
class SpecialOATH extends ProxySpecialPage {
	/**
	 * If the user already has OATH enabled, show them a page to disable
	 * If the user has OATH disabled, show them a page to enable
	 *
	 * @return SpecialOATHDisable|SpecialOATHEnable
	 */
	protected function getTargetPage() {
		$repo = OATHAuthHooks::getOATHUserRepository();

		$user = $repo->findByUser( $this->getUser() );

		if ( $user->getKey() === null ) {
			return new SpecialOATHEnable( $repo, $user );
		} else {
			return new SpecialOATHDisable( $repo, $user );
		}
	}

	protected function getGroupName() {
		return 'oath';
	}
}
