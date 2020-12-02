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
 * @since 1.35
 */

namespace Vector;

use Config;
use User;
use WebRequest;

/**
 * Given initial dependencies, retrieve the current skin version. This class does no parsing, just
 * the lookup.
 *
 * Skin version is evaluated in the following order:
 *
 * - useskinversion URL query parameter override. See readme.
 *
 * - User preference. The User object for new accounts is updated (persisted as a user preference)
 *   by hook according to VectorDefaultSkinVersionForNewAccounts. See Hooks and skin.json. The user
 *   may then change the preference at will.
 *
 * - Site configuration default. The default is controlled by VectorDefaultSkinVersion and
 *   VectorDefaultSkinVersionForExistingAccounts based on login state. The former is used
 *   for anonymous users and as a fallback configuration, the latter is logged in users (existing
 *   accounts). See skin.json.
 *
 * @unstable
 *
 * @package Vector
 * @internal
 */
final class SkinVersionLookup {
	/**
	 * @var WebRequest
	 */
	private $request;
	/**
	 * @var User
	 */
	private $user;
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * This constructor accepts all dependencies needed to obtain the skin version. The dependencies
	 * are lazily evaluated, not cached, meaning they always return the current results.
	 *
	 * @param WebRequest $request
	 * @param User $user
	 * @param Config $config
	 */
	public function __construct( WebRequest $request, User $user, Config $config ) {
		$this->request = $request;
		$this->user = $user;
		$this->config = $config;
	}

	/**
	 * Whether or not the legacy skin is being used.
	 *
	 * @return bool
	 * @throws \ConfigException
	 */
	public function isLegacy(): bool {
		return $this->getVersion() === Constants::SKIN_VERSION_LEGACY;
	}

	/**
	 * The skin version as a string. E.g., `Constants::SKIN_VERSION_LATEST`,
	 * `Constants::SKIN_VERSION_LATEST`, or maybe 'beta'. Note: it's likely someone will put arbitrary
	 * strings in the query parameter which means this function returns those strings as is.
	 *
	 * @return string
	 * @throws \ConfigException
	 */
	public function getVersion(): string {
		// Obtain the skin version from the 1) `useskinversion` URL query parameter override, 2) the
		// user preference, 3) the configured default for logged in users, 4) or the site default.
		//
		// The latter two configurations cannot be set by `Hooks::onUserGetDefaultOptions()` as user
		// sessions are unavailable at that time so it's not possible to determine whether the
		// preference is for a logged in user or an anonymous user. Since new users are known to have
		// had their user preferences initialized in `Hooks::onLocalUserCreated()`, that means all
		// subsequent requests to `User->getOption()` that do not have a preference set are either
		// existing accounts or anonymous users. Login state makes the distinction.
		return (string)$this->request->getVal(
			Constants::QUERY_PARAM_SKIN_VERSION,
			$this->user->getOption(
				Constants::PREF_KEY_SKIN_VERSION,
				$this->config->get(
					$this->user->isLoggedIn()
						? Constants::CONFIG_KEY_DEFAULT_SKIN_VERSION_FOR_EXISTING_ACCOUNTS
						: Constants::CONFIG_KEY_DEFAULT_SKIN_VERSION
				)
			)
		);
	}
}
