<?php
/**
 * Maintenance script to clean up after incomplete user renames
 * Sometimes user edits are left lying around under the old name,
 * check for that and assign them to the new username
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
 * @ingroup Maintenance
 * @author Ariel Glenn <ariel@wikimedia.org>
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class RenameUserCleanup extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Maintenance script to finish incomplete rename user,'
			. ' in particular to reassign edits that were missed' );
		$this->addOption( 'olduser', 'Old user name', true, true );
		$this->addOption( 'newuser', 'New user name', true, true );
		$this->addOption( 'olduid', 'Old user id in revision records (DANGEROUS)', false, true );
		$this->setBatchSize( 1000 );

		$this->requireExtension( 'Renameuser' );
	}

	public function execute() {
		if ( !RenameuserSQL::actorMigrationWriteOld() ) {
			$this->output( "Core xx_user_text fields are no longer used, no updates should be needed.\n" );
			return;
		}

		$this->output( "Rename User Cleanup starting...\n\n" );
		$olduser = User::newFromName( $this->getOption( 'olduser' ) );
		$newuser = User::newFromName( $this->getOption( 'newuser' ) );
		$olduid = $this->getOption( 'olduid' );

		$this->checkUserExistence( $olduser, $newuser );
		$this->checkRenameLog( $olduser, $newuser );

		if ( $olduid ) {
			$this->doUpdates( $olduser, $newuser, $olduid );
		}
		$this->doUpdates( $olduser, $newuser, $newuser->getId() );
		$this->doUpdates( $olduser, $newuser, 0 );

		$this->output( "Done!\n" );
	}

	/**
	 * @param User $olduser
	 * @param User $newuser
	 */
	public function checkUserExistence( $olduser, $newuser ) {
		if ( !$newuser->getId() ) {
			$this->fatalError( 'No such user: ' . $this->getOption( 'newuser' ) );
		}
		if ( $olduser->getId() ) {
			$this->output( 'WARNING!!: Old user still exists: ' . $this->getOption( 'olduser' ) . "\n" );
			$this->output( 'We\'ll only re-attribute edits that have the new user uid (or 0) ' );
			$this->output( 'or the uid specified by the caller, and the old user name.' );
			$this->output( 'Proceed anyway? [N/y] ' );

			$stdin = fopen( 'php://stdin', 'rt' );
			$line = fgets( $stdin );
			fclose( $stdin );

			if ( $line[0] !== 'Y' && $line[0] !== 'y' ) {
				$this->output( "Exiting at users request\n" );
			}
		}
	}

	/**
	 * @param User $olduser
	 * @param User $newuser
	 */
	public function checkRenameLog( $olduser, $newuser ) {
		$dbr = wfGetDB( DB_REPLICA );

		$oldTitle = Title::makeTitle( NS_USER, $olduser->getName() );

		$result = $dbr->select( 'logging', '*',
			[ 'log_type' => 'renameuser',
				'log_action' => 'renameuser',
				'log_namespace' => NS_USER,
				'log_title' => $oldTitle->getDBkey(),
				'log_params' => $newuser->getName()
			],
			__METHOD__
		);
		if ( !$result || !$result->numRows() ) {
			// try the old format
			if ( class_exists( CommentStore::class ) ) {
				$commentStore = CommentStore::getStore();
				$commentQuery = $commentStore->getJoin( 'log_comment' );
			} else {
				$commentStore = null;
				$commentQuery = [
					'tables' => [],
					'fields' => [ 'log_comment' => 'log_comment' ],
					'joins' => [],
				];
			}
			$result = $dbr->select(
				[ 'logging' ] + $commentQuery['tables'],
				[ 'log_title', 'log_timestamp' ] + $commentQuery['fields'],
				[
					'log_type' => 'renameuser',
					'log_action' => 'renameuser',
					'log_namespace' => NS_USER,
					'log_title' => $olduser->getName(),
				],
				__METHOD__,
				[],
				$commentQuery['joins']
			);
			if ( !$result || !$result->numRows() ) {
				$this->output( 'No log entry found for a rename of ' . $olduser->getName() .
					' to ' . $newuser->getName() . ', proceed anyways? [N/y] ' );

				$stdin = fopen( 'php://stdin', 'rt' );
				$line = fgets( $stdin );
				fclose( $stdin );

				if ( $line[0] !== 'Y' && $line[0] !== 'y' ) {
					$this->output( "Exiting at user's request\n" );
					exit( 1 );
				}
			} else {
				foreach ( $result as $row ) {
					$comment = $commentStore
						? $commentStore->getComment( 'log_comment', $row )->text
						: $row->log_comment;
					$this->output( 'Found possible log entry of the rename, please check: ' .
						$row->log_title . ' with comment ' . $comment .
						" on $row->log_timestamp\n" );
				}
			}
		} else {
			foreach ( $result as $row ) {
				$this->output( 'Found log entry of the rename: ' . $olduser->getName() .
					' to ' . $newuser->getName() . " on $row->log_timestamp\n" );
			}
		}
		if ( $result && $result->numRows() > 1 ) {
			print 'More than one rename entry found in the log, not sure ' .
				'what to do. Proceed anyways? [N/y] ';

			$stdin = fopen( 'php://stdin', 'rt' );
			$line = fgets( $stdin );
			fclose( $stdin );

			if ( $line[0] !== 'Y' && $line[0] !== 'y' ) {
				$this->output( "Exiting at users request\n" );
				exit( 1 );
			}
		}
	}

	/**
	 * @param User $olduser
	 * @param User $newuser
	 * @param int $uid
	 */
	public function doUpdates( $olduser, $newuser, $uid ) {
		$this->updateTable(
			'revision',
			'rev_user_text',
			'rev_user',
			'rev_timestamp',
			$olduser,
			$newuser,
			$uid
		);
		$this->updateTable(
			'archive',
			'ar_user_text',
			'ar_user',
			'ar_timestamp',
			$olduser,
			$newuser,
			$uid
		);
		$this->updateTable(
			'logging',
			'log_user_text',
			'log_user',
			'log_timestamp',
			$olduser,
			$newuser,
			$uid
		);
		$this->updateTable(
			'image',
			'img_user_text',
			'img_user',
			'img_timestamp',
			$olduser,
			$newuser,
			$uid
		);
		$this->updateTable(
			'oldimage',
			'oi_user_text',
			'oi_user',
			'oi_timestamp',
			$olduser,
			$newuser,
			$uid
		);
		$this->updateTable(
			'filearchive',
			'fa_user_text',
			'fa_user',
			'fa_timestamp',
			$olduser,
			$newuser,
			$uid
		);
	}

	/**
	 * @param string $table
	 * @param string $usernamefield
	 * @param string $useridfield
	 * @param string $timestampfield
	 * @param User $olduser
	 * @param User $newuser
	 * @param int $uid
	 */
	public function updateTable( $table, $usernamefield, $useridfield,
		$timestampfield, $olduser, $newuser, $uid
	) {
		$dbw = wfGetDB( DB_MASTER );

		$contribs = $dbw->selectField(
			$table,
			'count(*)',
			[
				$usernamefield => $olduser->getName(),
				$useridfield => $uid
			],
			__METHOD__
		);

		if ( $contribs === 0 ) {
			$this->output( "No edits to be re-attributed from table $table for uid $uid\n" );

			return;
		}

		$this->output( "Found $contribs edits to be re-attributed from table $table for uid $uid\n" );
		if ( $uid !== $newuser->getId() ) {
			$this->output( 'If you proceed, the uid field will be set to that ' .
				'of the new user name (i.e. ' . $newuser->getId() . ") in these rows.\n" );
		}

		$this->output( 'Proceed? [N/y] ' );

		$stdin = fopen( 'php://stdin', 'rt' );
		$line = fgets( $stdin );
		fclose( $stdin );

		if ( $line[0] !== 'Y' && $line[0] !== 'y' ) {
			$this->output( "Skipping at user's request\n" );
			return;
		}

		$selectConds = [ $usernamefield => $olduser->getName(), $useridfield => $uid ];
		$updateFields = [ $usernamefield => $newuser->getName(), $useridfield => $newuser->getId() ];

		while ( $contribs > 0 ) {
			$this->output( 'Doing batch of up to approximately ' . $this->mBatchSize . "\n" );
			$this->output( 'Do this batch? [N/y] ' );

			$stdin = fopen( 'php://stdin', 'rt' );
			$line = fgets( $stdin );
			fclose( $stdin );

			if ( $line[0] !== 'Y' && $line[0] !== 'y' ) {
				$this->output( "Skipping at user's request\n" );
				return;
			}

			$this->beginTransaction( $dbw, __METHOD__ );
			$result = $dbw->select(
				$table,
				$timestampfield,
				$selectConds,
				__METHOD__,
				[
					'ORDER BY' => $timestampfield . ' DESC',
					'LIMIT' => $this->mBatchSize
				]
			);

			if ( !$result ) {
				$this->output( "There were rows for updating but now they are gone. Skipping.\n" );
				$this->rollbackTransaction( $dbw, __METHOD__ );

				return;
			}

			$result->seek( $result->numRows() - 1 );
			$row = $result->fetchObject();
			$timestamp = $dbw->addQuotes( $row->$timestampfield );
			$updateCondsWithTime = array_merge( $selectConds, [ "$timestampfield >= $timestamp" ] );
			$success = $dbw->update(
				$table,
				$updateFields,
				$updateCondsWithTime,
				__METHOD__
			);

			if ( $success ) {
				$rowsDone = $dbw->affectedRows();
				$this->commitTransaction( $dbw, __METHOD__ );
			} else {
				$this->rollbackTransaction( $dbw, __METHOD__ );
				$this->fatalError( "Problem with the update, rolling back and exiting\n" );
				throw new LogicException();
			}

			// $contribs = User::edits( $olduser->getId() );
			$contribs = $dbw->selectField( $table, 'count(*)', $selectConds, __METHOD__ );
			$this->output( "Updated $rowsDone edits; $contribs edits remaining to be re-attributed\n" );
		}
	}
}

$maintClass = RenameUserCleanup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
