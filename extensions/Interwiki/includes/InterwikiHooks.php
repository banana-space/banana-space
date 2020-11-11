<?php

class InterwikiHooks {
	public static function onExtensionFunctions() {
		global $wgInterwikiViewOnly;

		if ( $wgInterwikiViewOnly === false ) {
			global $wgAvailableRights, $wgLogTypes, $wgLogActionsHandlers;

			// New user right, required to modify the interwiki table through Special:Interwiki
			$wgAvailableRights[] = 'interwiki';

			// Set up the new log type - interwiki actions are logged to this new log
			$wgLogTypes[] = 'interwiki';
			// interwiki, iw_add, iw_delete, iw_edit
			$wgLogActionsHandlers['interwiki/*'] = 'InterwikiLogFormatter';
		}

		return true;
	}

	public static function onInterwikiLoadPrefix( $prefix, &$iwData ) {
		global $wgInterwikiCentralDB;
		// docs/hooks.txt says: Return true without providing an interwiki to continue interwiki search.
		if ( $wgInterwikiCentralDB === null || $wgInterwikiCentralDB === wfWikiID() ) {
			// No global set or this is global, nothing to add
			return true;
		}
		if ( !Language::fetchLanguageName( $prefix ) ) {
			// Check if prefix exists locally and skip
			foreach ( Interwiki::getAllPrefixes( null ) as $id => $localPrefixInfo ) {
				if ( $prefix === $localPrefixInfo['iw_prefix'] ) {
					return true;
				}
			}
			$dbr = wfGetDB( DB_REPLICA, [], $wgInterwikiCentralDB );
			$res = $dbr->selectRow(
				'interwiki',
				'*',
				[ 'iw_prefix' => $prefix ],
				__METHOD__
			);
			if ( !$res ) {
				return true;
			}
			// Excplicitly make this an array since it's expected to be one
			$iwData = (array)$res;
			// At this point, we can safely return false because we know that we have something
			return false;
		}
		return true;
	}

}
