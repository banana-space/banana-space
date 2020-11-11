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

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Hooks for Extension:OATHAuth
 *
 * @ingroup Extensions
 */
class OATHAuthHooks {
	/**
	 * Get the singleton OATH user repository
	 *
	 * @return OATHUserRepository
	 */
	public static function getOATHUserRepository() {
		global $wgOATHAuthDatabase;

		static $service = null;

		if ( $service == null ) {
			$factory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();
			$service = new OATHUserRepository(
				$factory->getMainLB( $wgOATHAuthDatabase ),
				new HashBagOStuff(
					[
						'maxKeys' => 5,
					]
				)
			);
		}

		return $service;
	}

	/**
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo Field information array (union of the
	 *    AuthenticationRequest::getFieldInfo() responses).
	 * @param array &$formDescriptor HTMLForm descriptor. The special key 'weight' can be set
	 *   to change the order of the fields.
	 * @param string $action One of the AuthManager::ACTION_* constants.
	 * @return bool
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		if ( isset( $fieldInfo['OATHToken'] ) ) {
			$formDescriptor['OATHToken'] += [
				'cssClass' => 'loginText',
				'id' => 'wpOATHToken',
				'size' => 20,
				'autofocus' => true,
				'persistent' => false,
				'autocomplete' => false,
				'spellcheck' => false,
			];
		}
		return true;
	}

	/**
	 * Determine if two-factor authentication is enabled for $wgUser
	 *
	 * This isn't the preferred mechanism for controlling access to sensitive features
	 * (see AuthManager::securitySensitiveOperationStatus() for that) but there is no harm in
	 * keeping it.
	 *
	 * @param bool &$isEnabled Will be set to true if enabled, false otherwise
	 * @return bool False if enabled, true otherwise
	 */
	public static function onTwoFactorIsEnabled( &$isEnabled ) {
		global $wgUser;

		$user = self::getOATHUserRepository()->findByUser( $wgUser );
		if ( $user && $user->getKey() !== null ) {
			$isEnabled = true;
			# This two-factor extension is enabled by the user,
			# we don't need to check others.
			return false;
		} else {
			$isEnabled = false;
			# This two-factor extension isn't enabled by the user,
			# but others may be.
			return true;
		}
	}

	/**
	 * Add the necessary user preferences for OATHAuth
	 *
	 * @param User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$oathUser = self::getOATHUserRepository()->findByUser( $user );

		// If there is no existing key, and the user is not allowed to enable it,
		// we have nothing to show. (
		if ( $oathUser->getKey() === null && !$user->isAllowed( 'oathauth-enable' ) ) {
			return true;
		}

		$title = SpecialPage::getTitleFor( 'OATH' );
		$msg = $oathUser->getKey() !== null ? 'oathauth-disable' : 'oathauth-enable';

		$preferences[$msg] = [
			'type' => 'info',
			'raw' => 'true',
			'default' => Linker::link(
				$title,
				wfMessage( $msg )->escaped(),
				[],
				[ 'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText() ]
			),
			'label-message' => 'oathauth-prefs-label',
			'section' => 'personal/info', ];

		return true;
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$base = dirname( __DIR__ );
		switch ( $updater->getDB()->getType() ) {
			case 'mysql':
			case 'sqlite':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/mysql/tables.sql" );
				$updater->addExtensionUpdate( [ [ __CLASS__, 'schemaUpdateOldUsersFromInstaller' ] ] );
				$updater->dropExtensionField(
					'oathauth_users',
					'secret_reset',
					"$base/sql/mysql/patch-remove_reset.sql"
				);
				break;

			case 'oracle':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/oracle/tables.sql" );
				break;

			case 'postgres':
				$updater->addExtensionTable( 'oathauth_users', "$base/sql/postgres/tables.sql" );
				break;
		}

		return true;
	}

	/**
	 * Helper function for converting old users to the new schema
	 * @see OATHAuthHooks::OATHAuthSchemaUpdates
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function schemaUpdateOldUsersFromInstaller( DatabaseUpdater $updater ) {
		return self::schemaUpdateOldUsers( $updater->getDB() );
	}

	/**
	 * Helper function for converting old users to the new schema
	 * @see OATHAuthHooks::OATHAuthSchemaUpdates
	 *
	 * @param IDatabase $db
	 * @return bool
	 */
	public static function schemaUpdateOldUsers( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret_reset' ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'scratch_tokens' ],
			[ 'is_validated != 0' ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			Wikimedia\suppressWarnings();
			$scratchTokens = unserialize( base64_decode( $row->scratch_tokens ) );
			Wikimedia\restoreWarnings();
			if ( $scratchTokens ) {
				$db->update(
					'oathauth_users',
					[ 'scratch_tokens' => implode( ',', $scratchTokens ) ],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}

		// Remove rows from the table where user never completed the setup process
		$db->delete( 'oathauth_users', [ 'is_validated' => 0 ], __METHOD__ );

		return true;
	}
}
