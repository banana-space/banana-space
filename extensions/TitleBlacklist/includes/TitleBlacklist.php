<?php
/**
 * Title Blacklist class
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GPL-2.0-or-later
 * @file
 */

/**
 * @ingroup Extensions
 */

/**
 * Implements a title blacklist for MediaWiki
 */
class TitleBlacklist {
	/** @var array */
	private $mBlacklist = null;

	/** @var array */
	private $mWhitelist = null;

	/** @var TitleBlacklist */
	protected static $instance = null;

	const VERSION = 3;	// Blacklist format

	/**
	 * Get an instance of this class
	 *
	 * @return TitleBlacklist
	 */
	public static function singleton() {
		if ( self::$instance === null ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Destroy/reset the current singleton instance.
	 *
	 * This is solely for testing and will fail unless MW_PHPUNIT_TEST is
	 * defined.
	 */
	public static function destroySingleton() {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new MWException(
				'Can not invoke ' . __METHOD__ . '() ' .
				'out of tests (MW_PHPUNIT_TEST not set).'
			);
		}

		self::$instance = null;
	}

	/**
	 * Load all configured blacklist sources
	 */
	public function load() {
		global $wgTitleBlacklistSources, $wgTitleBlacklistCaching;

		$cache = ObjectCache::getMainWANInstance();
		// Try to find something in the cache
		$cachedBlacklist = $cache->get( wfMemcKey( "title_blacklist_entries" ) );
		if ( is_array( $cachedBlacklist ) && count( $cachedBlacklist ) > 0
			&& ( $cachedBlacklist[0]->getFormatVersion() == self::VERSION )
		) {
			$this->mBlacklist = $cachedBlacklist;
			return;
		}

		$sources = $wgTitleBlacklistSources;
		$sources['local'] = [ 'type' => 'message' ];
		$this->mBlacklist = [];
		foreach ( $sources as $sourceName => $source ) {
			$this->mBlacklist = array_merge(
				$this->mBlacklist,
				$this->parseBlacklist( $this->getBlacklistText( $source ), $sourceName )
			);
		}
		$cache->set( wfMemcKey( "title_blacklist_entries" ),
			$this->mBlacklist, $wgTitleBlacklistCaching['expiry'] );
		wfDebugLog( 'TitleBlacklist-cache', 'Updated ' . wfMemcKey( "title_blacklist_entries" )
			. ' with ' . count( $this->mBlacklist ) . ' entries.' );
	}

	/**
	 * Load local whitelist
	 */
	public function loadWhitelist() {
		global $wgTitleBlacklistCaching;

		$cache = ObjectCache::getMainWANInstance();
		$cachedWhitelist = $cache->get( wfMemcKey( "title_whitelist_entries" ) );
		if ( is_array( $cachedWhitelist ) && count( $cachedWhitelist ) > 0
			&& ( $cachedWhitelist[0]->getFormatVersion() != self::VERSION )
		) {
			$this->mWhitelist = $cachedWhitelist;
			return;
		}
		$this->mWhitelist = $this->parseBlacklist( wfMessage( 'titlewhitelist' )
				->inContentLanguage()->text(), 'whitelist' );
		$cache->set( wfMemcKey( "title_whitelist_entries" ),
			$this->mWhitelist, $wgTitleBlacklistCaching['expiry'] );
	}

	/**
	 * Get the text of a blacklist from a specified source
	 *
	 * @param array $source A blacklist source from $wgTitleBlacklistSources
	 * @return string The content of the blacklist source as a string
	 */
	private static function getBlacklistText( $source ) {
		if ( !is_array( $source ) || count( $source ) <= 0 ) {
			return '';	// Return empty string in error case
		}

		if ( $source['type'] == 'message' ) {
			return wfMessage( 'titleblacklist' )->inContentLanguage()->text();
		} elseif ( $source['type'] == 'localpage' && count( $source ) >= 2 ) {
			$title = Title::newFromText( $source['src'] );
			if ( is_null( $title ) ) {
				return '';
			}
			if ( $title->getNamespace() == NS_MEDIAWIKI ) {
				$msg = wfMessage( $title->getText() )->inContentLanguage();
				if ( !$msg->isDisabled() ) {
					return $msg->text();
				} else {
					return '';
				}
			} else {
				$page = WikiPage::factory( $title );
				if ( $page->exists() ) {
					return ContentHandler::getContentText( $page->getContent() );
				}
			}
		} elseif ( $source['type'] == 'url' && count( $source ) >= 2 ) {
			return self::getHttp( $source['src'] );
		} elseif ( $source['type'] == 'file' && count( $source ) >= 2 ) {
			if ( file_exists( $source['src'] ) ) {
				return file_get_contents( $source['src'] );
			} else {
				return '';
			}
		}

		return '';
	}

	/**
	 * Parse blacklist from a string
	 *
	 * @param string $list Text of a blacklist source
	 * @param string $sourceName
	 * @return array of TitleBlacklistEntry entries
	 */
	public static function parseBlacklist( $list, $sourceName ) {
		$lines = preg_split( "/\r?\n/", $list );
		$result = [];
		foreach ( $lines as $line ) {
			$line = TitleBlacklistEntry::newFromString( $line, $sourceName );
			if ( $line ) {
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Check whether the blacklist restricts given user
	 * performing a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param User $user User to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @param bool $override If set to true, overrides work
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if
	 * blacklisted; otherwise false
	 */
	public function userCannot( $title, $user, $action = 'edit', $override = true ) {
		$entry = $this->isBlacklisted( $title, $action );
		if ( !$entry ) {
			return false;
		}
		$params = $entry->getParams();
		if ( isset( $params['autoconfirmed'] ) && $user->isAllowed( 'autoconfirmed' ) ) {
			return false;
		}
		if ( $override && self::userCanOverride( $user, $action ) ) {
			return false;
		}
		return $entry;
	}

	/**
	 * Check whether the blacklist restricts
	 * performing a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @return TitleBlacklistEntry|bool The corresponding TitleBlacklistEntry if blacklisted;
	 *         otherwise FALSE
	 */
	public function isBlacklisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
			if ( !( $title instanceof Title ) ) {
				// The fact that the page name is invalid will stop whatever
				// action is going through. No sense in doing more work here.
				return false;
			}
		}
		$blacklist = $this->getBlacklist();
		$autoconfirmedItem = false;
		foreach ( $blacklist as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				if ( $this->isWhitelisted( $title, $action ) ) {
					return false;
				}
				$params = $item->getParams();
				if ( !isset( $params['autoconfirmed'] ) ) {
					return $item;
				}
				if ( !$autoconfirmedItem ) {
					$autoconfirmedItem = $item;
				}
			}
		}
		return $autoconfirmedItem;
	}

	/**
	 * Check whether it has been explicitly whitelisted that the
	 * current User may perform a specific action on the given Title
	 *
	 * @param Title $title Title to check
	 * @param string $action Action to check; 'edit' if unspecified
	 * @return bool True if whitelisted; otherwise false
	 */
	public function isWhitelisted( $title, $action = 'edit' ) {
		if ( !( $title instanceof Title ) ) {
			$title = Title::newFromText( $title );
		}
		$whitelist = $this->getWhitelist();
		foreach ( $whitelist as $item ) {
			if ( $item->matches( $title->getFullText(), $action ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the current blacklist
	 *
	 * @return TitleBlacklistEntry[]
	 */
	public function getBlacklist() {
		if ( is_null( $this->mBlacklist ) ) {
			$this->load();
		}
		return $this->mBlacklist;
	}

	/**
	 * Get the current whitelist
	 *
	 * @return Array of TitleBlacklistEntry items
	 */
	public function getWhitelist() {
		if ( is_null( $this->mWhitelist ) ) {
			$this->loadWhitelist();
		}
		return $this->mWhitelist;
	}

	/**
	 * Get the text of a blacklist source via HTTP
	 *
	 * @param string $url URL of the blacklist source
	 * @return string The content of the blacklist source as a string
	 */
	private static function getHttp( $url ) {
		global $messageMemc, $wgTitleBlacklistCaching;
		$key = "title_blacklist_source:" . md5( $url ); // Global shared
		$warnkey = wfMemcKey( "titleblacklistwarning", md5( $url ) );
		$result = $messageMemc->get( $key );
		$warn = $messageMemc->get( $warnkey );
		if ( !is_string( $result )
			|| ( !$warn && !mt_rand( 0, $wgTitleBlacklistCaching['warningchance'] ) )
		) {
			$result = Http::get( $url );
			$messageMemc->set( $warnkey, 1, $wgTitleBlacklistCaching['warningexpiry'] );
			$messageMemc->set( $key, $result, $wgTitleBlacklistCaching['expiry'] );
		}
		return $result;
	}

	/**
	 * Invalidate the blacklist cache
	 */
	public function invalidate() {
		$cache = ObjectCache::getMainWANInstance();
		$cache->delete( wfMemcKey( "title_blacklist_entries" ) );
	}

	/**
	 * Validate a new blacklist
	 *
	 * @suppress PhanParamSuspiciousOrder The preg_match() params are in the correct order
	 * @param array $blacklist
	 * @return Array of bad entries; empty array means blacklist is valid
	 */
	public function validate( $blacklist ) {
		$badEntries = [];
		foreach ( $blacklist as $e ) {
			wfSuppressWarnings();
			$regex = $e->getRegex();
			if ( preg_match( "/{$regex}/u", '' ) === false ) {
				$badEntries[] = $e->getRaw();
			}
			wfRestoreWarnings();
		}
		return $badEntries;
	}

	/**
	 * Inidcates whether user can override blacklist on certain action.
	 *
	 * @param User $user
	 * @param string $action Action
	 *
	 * @return bool
	 */
	public static function userCanOverride( $user, $action ) {
		return $user->isAllowed( 'tboverride' ) ||
			( $action == 'new-account' && $user->isAllowed( 'tboverride-account' ) );
	}
}
