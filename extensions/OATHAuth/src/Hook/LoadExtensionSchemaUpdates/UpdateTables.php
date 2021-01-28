<?php

namespace MediaWiki\Extension\OATHAuth\Hook\LoadExtensionSchemaUpdates;

use ConfigException;
use DatabaseUpdater;
use FormatJson;
use MediaWiki\MediaWikiServices;
use Wikimedia;
use Wikimedia\Rdbms\IDatabase;

class UpdateTables {
	/**
	 * @var DatabaseUpdater
	 */
	protected $updater;

	/**
	 * @var string
	 */
	protected $base;

	/**
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function callback( $updater ) {
		$dir = dirname( dirname( dirname( __DIR__ ) ) );
		$handler = new static( $updater, $dir );
		return $handler->execute();
	}

	/**
	 * @param DatabaseUpdater $updater
	 * @param string $base
	 */
	protected function __construct( $updater, $base ) {
		$this->updater = $updater;
		$this->base = $base;
	}

	protected function execute() {
		$type = $this->updater->getDB()->getType();
		switch ( $type ) {
			case 'mysql':
			case 'sqlite':
				$this->updater->addExtensionTable( 'oathauth_users', "{$this->base}/sql/mysql/tables.sql" );
				$this->updater->addExtensionUpdate( [ [ $this, 'schemaUpdateOldUsersFromInstaller' ] ] );
				$this->updater->dropExtensionField(
					'oathauth_users',
					'secret_reset',
					"{$this->base}/sql/mysql/patch-remove_reset.sql"
				);

				$this->updater->addExtensionField(
					'oathauth_users',
					'module',
					"{$this->base}/sql/{$type}/patch-add_generic_fields.sql"
				);

				$this->updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateSubstituteForGenericFields' ] ]
				);
				$this->updater->dropExtensionField(
					'oathauth_users',
					'secret',
					"{$this->base}/sql/{$type}/patch-remove_module_specific_fields.sql"
				);

				$this->updater->addExtensionUpdate(
					[ [ __CLASS__, 'schemaUpdateTOTPToMultipleKeys' ] ]
				);

				break;

			case 'postgres':
				$this->updater->addExtensionTable( 'oathauth_users', "{$this->base}/sql/postgres/tables.sql" );
				break;
		}

		return true;
	}

	/**
	 * @return Wikimedia\Rdbms\DBConnRef
	 */
	private static function getDatabase() {
		global $wgOATHAuthDatabase;
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $wgOATHAuthDatabase );
		return $lb->getConnectionRef( DB_MASTER, [], $wgOATHAuthDatabase );
	}

	/**
	 * Helper function for converting old users to the new schema
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public function schemaUpdateOldUsersFromInstaller( DatabaseUpdater $updater ) {
		return self::schemaUpdateOldUsers( self::getDatabase() );
	}

	/**
	 * Helper function for converting old, TOTP specific, column values to new structure
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateSubstituteForGenericFields( DatabaseUpdater $updater ) {
		return self::convertToGenericFields( self::getDatabase() );
	}

	/**
	 * Helper function for converting single TOTP keys to multi-key system
	 * @param DatabaseUpdater $updater
	 * @return bool
	 * @throws ConfigException
	 */
	public static function schemaUpdateTOTPToMultipleKeys( DatabaseUpdater $updater ) {
		return self::switchTOTPToMultipleKeys( self::getDatabase() );
	}

	/**
	 * Converts old, TOTP specific, column values to new structure
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function convertToGenericFields( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret', __METHOD__ ) ) {
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$batchSize = $services->getMainConfig()->get( 'UpdateRowsPerQuery' );
		$lbFactory = $services->getDBLoadBalancerFactory();
		while ( true ) {
			$lbFactory->waitForReplication();

			$res = $db->select(
				'oathauth_users',
				[ 'id', 'secret', 'scratch_tokens' ],
				[
					'module' => '',
					'data IS NULL',
					'secret IS NOT NULL'
				],
				__METHOD__,
				[ 'LIMIT' => $batchSize ]
			);

			if ( $res->numRows() === 0 ) {
				return true;
			}

			foreach ( $res as $row ) {
				$db->update(
					'oathauth_users',
					[
						'module' => 'totp',
						'data' => FormatJson::encode( [
							'keys' => [ [
								'secret' => $row->secret,
								'scratch_tokens' => $row->scratch_tokens
							] ]
						] )
					],
					[ 'id' => $row->id ],
					__METHOD__
				);
			}
		}

		return true;
	}

	/**
	 * Switch from using single keys to multi-key support
	 *
	 * @param IDatabase $db
	 * @return bool
	 * @throws ConfigException
	 */
	public static function switchTOTPToMultipleKeys( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'data', __METHOD__ ) ) {
			return true;
		}

		$res = $db->select(
			'oathauth_users',
			[ 'id', 'data' ],
			[
				'module' => 'totp'
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$data = FormatJson::decode( $row->data, true );
			if ( isset( $data['keys'] ) ) {
				continue;
			}
			$db->update(
				'oathauth_users',
				[
					'data' => FormatJson::encode( [
						'keys' => [ $data ]
					] )
				],
				[ 'id' => $row->id ],
				__METHOD__
			);
		}

		return true;
	}

	/**
	 * Helper function for converting old users to the new schema
	 *
	 * @param IDatabase $db
	 * @return bool
	 */
	public static function schemaUpdateOldUsers( IDatabase $db ) {
		if ( !$db->fieldExists( 'oathauth_users', 'secret_reset', __METHOD__ ) ) {
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
