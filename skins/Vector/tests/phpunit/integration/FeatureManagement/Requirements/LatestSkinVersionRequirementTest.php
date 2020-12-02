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

use Vector\FeatureManagement\Requirements\LatestSkinVersionRequirement;
use Vector\SkinVersionLookup;

/**
 * @group Vector
 * @coversDefaultClass \Vector\FeatureManagement\Requirements\LatestSkinVersionRequirement
 */
class LatestSkinVersionRequirementTest extends \MediaWikiTestCase {

	/**
	 * @covers ::isMet
	 */
	public function testUnmet() {
		$config = new HashConfig( [ 'VectorDefaultSkinVersionForExistingAccounts' => '1' ] );

		$requirement = new LatestSkinVersionRequirement(
			new SkinVersionLookup( new WebRequest(), $this->getTestUser()->getUser(), $config )
		);

		$this->assertFalse( $requirement->isMet(), '"1" isn\'t considered latest.' );
	}

	/**
	 * @covers ::isMet
	 */
	public function testMet() {
		$config = new HashConfig( [ 'VectorDefaultSkinVersionForExistingAccounts' => '2' ] );

		$requirement = new LatestSkinVersionRequirement(
			new SkinVersionLookup( new WebRequest(), $this->getTestUser()->getUser(), $config )
		);

		$this->assertTrue( $requirement->isMet(), '"2" is considered latest.' );
	}
}
