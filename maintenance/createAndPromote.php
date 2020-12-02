<?php
/**
 * Creates an account and grants it rights.
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
 * @author Rob Church <robchur@gmail.com>
 * @author Pablo Castellano <pablo@anche.no>
 */

require_once __DIR__ . '/Maintenance.php';

use MediaWiki\MediaWikiServices;

/**
 * Maintenance script to create an account and grant it rights.
 *
 * @ingroup Maintenance
 */
class CreateAndPromote extends Maintenance {
	private static $permitRoles = [ 'sysop', 'bureaucrat', 'interface-admin', 'bot' ];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Create a new user account and/or grant it additional rights' );
		$this->addOption(
			'force',
			'If acccount exists already, just grant it rights or change password.'
		);
		foreach ( self::$permitRoles as $role ) {
			$this->addOption( $role, "Add the account to the {$role} group" );
		}

		$this->addOption(
			'custom-groups',
			'Comma-separated list of groups to add the user to',
			false,
			true
		);

		$this->addArg( "username", "Username of new user" );
		$this->addArg( "password", "Password to set", false );
	}

	public function execute() {
		$username = $this->getArg( 0 );
		$password = $this->getArg( 1 );
		$force = $this->hasOption( 'force' );
		$inGroups = [];

		$user = User::newFromName( $username );
		if ( !is_object( $user ) ) {
			$this->fatalError( "invalid username." );
		}

		$exists = ( $user->idForName() !== 0 );

		if ( $exists && !$force ) {
			$this->fatalError( "Account exists. Perhaps you want the --force option?" );
		} elseif ( !$exists && !$password ) {
			$this->error( "Argument <password> required!" );
			$this->maybeHelp( true );
		} elseif ( $exists ) {
			$inGroups = $user->getGroups();
		}

		$groups = array_filter( self::$permitRoles, [ $this, 'hasOption' ] );
		if ( $this->hasOption( 'custom-groups' ) ) {
			$allGroups = array_flip( User::getAllGroups() );
			$customGroupsText = $this->getOption( 'custom-groups' );
			if ( $customGroupsText !== '' ) {
				$customGroups = explode( ',', $customGroupsText );
				foreach ( $customGroups as $customGroup ) {
					if ( isset( $allGroups[$customGroup] ) ) {
						$groups[] = trim( $customGroup );
					} else {
						$this->output( "$customGroup is not a valid group, ignoring!\n" );
					}
				}
			}
		}

		$promotions = array_diff(
			$groups,
			$inGroups
		);

		if ( $exists && !$password && count( $promotions ) === 0 ) {
			$this->output( "Account exists and nothing to do.\n" );

			return;
		} elseif ( count( $promotions ) !== 0 ) {
			$dbDomain = WikiMap::getCurrentWikiDbDomain()->getId();
			$promoText = "User:{$username} into " . implode( ', ', $promotions ) . "...\n";
			if ( $exists ) {
				$this->output( "$dbDomain: Promoting $promoText" );
			} else {
				$this->output( "$dbDomain: Creating and promoting $promoText" );
			}
		}

		if ( !$exists ) {
			// Create the user via AuthManager as there may be various side
			// effects that are performed by the configured AuthManager chain.
			$status = MediaWikiServices::getInstance()->getAuthManager()->autoCreateUser(
				$user,
				MediaWiki\Auth\AuthManager::AUTOCREATE_SOURCE_MAINT,
				false
			);
			if ( !$status->isGood() ) {
				$this->fatalError( $status->getMessage( false, false, 'en' )->text() );
			}
		}

		if ( $password ) {
			# Try to set the password
			try {
				$status = $user->changeAuthenticationData( [
					'username' => $user->getName(),
					'password' => $password,
					'retype' => $password,
				] );
				if ( !$status->isGood() ) {
					throw new PasswordError( $status->getMessage( false, false, 'en' )->text() );
				}
				if ( $exists ) {
					$this->output( "Password set.\n" );
					$user->saveSettings();
				}
			} catch ( PasswordError $pwe ) {
				$this->fatalError( $pwe->getText() );
			}
		}

		# Promote user
		array_map( [ $user, 'addGroup' ], $promotions );

		if ( !$exists ) {
			# Increment site_stats.ss_users
			$ssu = SiteStatsUpdate::factory( [ 'users' => 1 ] );
			$ssu->doUpdate();
		}

		$this->output( "done.\n" );
	}
}

$maintClass = CreateAndPromote::class;
require_once RUN_MAINTENANCE_IF_MAIN;
