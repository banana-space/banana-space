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

namespace Vector\FeatureManagement\Requirements;

use Vector\Constants;
use Vector\FeatureManagement\Requirement;
use Vector\SkinVersionLookup;

/**
 * Retrieve the skin version for the request and compare it with `Constants::SKIN_VERSION_LATEST`.
 * This requirement is met if the two are equal.
 *
 * Skin version is evaluated in the following order:
 *
 * - `useskinversion` URL query parameter override. See `README.md`.
 *
 * - User preference. The `User` object for new and existing accounts are updated by hook according
 *   to the `VectorDefaultSkinVersionForNewAccounts` and
 *  `VectorDefaultSkinVersionForExistingAccounts` config values. See the `Vector\Hooks` class and
 *  `skin.json`.
 *
 *   If the skin version is evaluated prior to `User` preference hook invocations, an incorrect
 *   version may be returned as only query parameter and site configuration will be known.
 *
 * - Site configuration default. The default is controlled by the `VectorDefaultSkinVersion` config
 *   value. This is used for anonymous users and as a fallback configuration. See `skin.json`.
 *
 * This majority of this class is taken from Stephen Niedzielski's `Vector\SkinVersionLookup` class,
 * which was introduced in `d1072d0fdfb1`.
 *
 * @unstable
 *
 * @package Vector\FeatureManagement\Requirements
 * @internal
 */
final class LatestSkinVersionRequirement implements Requirement {

	/**
	 * @var SkinVersionLookup
	 */
	private $skinVersionLookup;

	/**
	 * This constructor accepts all dependencies needed to obtain the skin version.
	 *
	 * @param SkinVersionLookup $skinVersionLookup
	 */
	public function __construct( SkinVersionLookup $skinVersionLookup ) {
		$this->skinVersionLookup = $skinVersionLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() : string {
		return Constants::REQUIREMENT_LATEST_SKIN_VERSION;
	}

	/**
	 * @inheritDoc
	 * @throws \ConfigException
	 */
	public function isMet() : bool {
		return $this->skinVersionLookup->getVersion() === Constants::SKIN_VERSION_LATEST;
	}
}
