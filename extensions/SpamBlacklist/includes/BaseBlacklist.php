<?php

/**
 * Base class for different kinds of blacklists
 */
abstract class BaseBlacklist {
	/**
	 * Array of blacklist sources
	 *
	 * @var array
	 */
	public $files = [];

	/**
	 * Array containing regexes to test against
	 *
	 * @var bool|array
	 */
	protected $regexes = false;

	/**
	 * Chance of receiving a warning when the filter is hit
	 *
	 * @var int
	 */
	public $warningChance = 100;

	/**
	 * @var int
	 */
	public $warningTime = 600;

	/**
	 * @var int
	 */
	public $expiryTime = 900;

	/**
	 * Array containing blacklists that extend BaseBlacklist
	 *
	 * @var array
	 */
	private static $blacklistTypes = [
		'spam' => 'SpamBlacklist',
		'email' => 'EmailBlacklist',
	];

	/**
	 * Array of blacklist instances
	 *
	 * @var array
	 */
	private static $instances = [];

	/**
	 * Constructor
	 *
	 * @param array $settings
	 */
	function __construct( $settings = [] ) {
		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
		}
	}

	/**
	 * @param array $links
	 * @param Title $title
	 * @param bool $preventLog
	 * @return mixed
	 */
	abstract public function filter( array $links, Title $title, $preventLog = false );

	/**
	 * Adds a blacklist class to the registry
	 *
	 * @param string $type
	 * @param string $class
	 */
	public static function addBlacklistType( $type, $class ) {
		self::$blacklistTypes[$type] = $class;
	}

	/**
	 * Return the array of blacklist types currently defined
	 *
	 * @return array
	 */
	public static function getBlacklistTypes() {
		return self::$blacklistTypes;
	}

	/**
	 * @return SpamBlacklist
	 */
	public static function getSpamBlacklist() {
		return self::getInstance( 'spam' );
	}

	/**
	 * @return EmailBlacklist
	 */
	public static function getEmailBlacklist() {
		return self::getInstance( 'email' );
	}

	/**
	 * Returns an instance of the given blacklist
	 *
	 * @deprecated Use getSpamBlacklist() or getEmailBlacklist() instead
	 * @param string $type Code for the blacklist
	 * @return BaseBlacklist
	 * @throws Exception
	 */
	public static function getInstance( $type ) {
		if ( !isset( self::$blacklistTypes[$type] ) ) {
			throw new Exception( "Invalid blacklist type '$type' passed to " . __METHOD__ );
		}

		if ( !isset( self::$instances[$type] ) ) {
			global $wgBlacklistSettings;

			// Prevent notices
			if ( !isset( $wgBlacklistSettings[$type] ) ) {
				$wgBlacklistSettings[$type] = [];
			}

			$class = self::$blacklistTypes[$type];
			self::$instances[$type] = new $class( $wgBlacklistSettings[$type] );
		}

		return self::$instances[$type];
	}

	/**
	 * Returns the code for the blacklist implementation
	 *
	 * @return string
	 */
	abstract protected function getBlacklistType();

	/**
	 * Check if the given local page title is a spam regex source.
	 *
	 * @param Title $title
	 * @return bool
	 */
	public static function isLocalSource( Title $title ) {
		global $wgDBname, $wgBlacklistSettings;

		if ( $title->getNamespace() == NS_MEDIAWIKI ) {
			$sources = [];
			foreach ( self::$blacklistTypes as $type => $class ) {
				$type = ucfirst( $type );
				$sources += [
					"$type-blacklist",
					"$type-whitelist"
				];
			}

			if ( in_array( $title->getDBkey(), $sources ) ) {
				return true;
			}
		}

		$thisHttp = wfExpandUrl( $title->getFullUrl( 'action=raw' ), PROTO_HTTP );
		$thisHttpRegex = '/^' . preg_quote( $thisHttp, '/' ) . '(?:&.*)?$/';

		$files = [];
		foreach ( self::$blacklistTypes as $type => $class ) {
			if ( isset( $wgBlacklistSettings[$type]['files'] ) ) {
				$files += $wgBlacklistSettings[$type]['files'];
			}
		}

		foreach ( $files as $fileName ) {
			$matches = [];
			if ( preg_match( '/^DB: (\w*) (.*)$/', $fileName, $matches ) ) {
				if ( $wgDBname == $matches[1] ) {
					if ( $matches[2] == $title->getPrefixedDbKey() ) {
						// Local DB fetch of this page...
						return true;
					}
				}
			} elseif ( preg_match( $thisHttpRegex, $fileName ) ) {
				// Raw view of this page
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the type of blacklist from the given title
	 *
	 * @todo building a regex for this is pretty overkill
	 * @param Title $title
	 * @return bool|string
	 */
	public static function getTypeFromTitle( Title $title ) {
		global $wgContLang;

		$types = array_map( [ $wgContLang, 'ucfirst' ], array_keys( self::$blacklistTypes ) );
		$regex = '/(' . implode( '|', $types ).  ')-(?:blacklist|whitelist)/';

		if ( preg_match( $regex, $title->getDBkey(), $m ) ) {
			return strtolower( $m[1] );
		}

		return false;
	}

	/**
	 * Fetch local and (possibly cached) remote blacklists.
	 * Will be cached locally across multiple invocations.
	 * @return array set of regular expressions, potentially empty.
	 */
	function getBlacklists() {
		if ( $this->regexes === false ) {
			$this->regexes = array_merge(
				$this->getLocalBlacklists(),
				$this->getSharedBlacklists() );
		}
		return $this->regexes;
	}

	/**
	 * Returns the local blacklist
	 *
	 * @return array Regular expressions
	 */
	public function getLocalBlacklists() {
		$that = $this;
		$type = $this->getBlacklistType();

		return ObjectCache::getMainWANInstance()->getWithSetCallback(
			wfMemcKey( 'spamblacklist', $type, 'blacklist-regex' ),
			$this->expiryTime,
			function () use ( $that, $type ) {
				return SpamRegexBatch::regexesFromMessage( "{$type}-blacklist", $that );
			}
		);
	}

	/**
	 * Returns the (local) whitelist
	 *
	 * @return array Regular expressions
	 */
	public function getWhitelists() {
		$that = $this;
		$type = $this->getBlacklistType();

		return ObjectCache::getMainWANInstance()->getWithSetCallback(
			wfMemcKey( 'spamblacklist', $type, 'whitelist-regex' ),
			$this->expiryTime,
			function () use ( $that, $type ) {
				return SpamRegexBatch::regexesFromMessage( "{$type}-whitelist", $that );
			}
		);
	}

	/**
	 * Fetch (possibly cached) remote blacklists.
	 * @return array
	 */
	function getSharedBlacklists() {
		$listType = $this->getBlacklistType();

		wfDebugLog( 'SpamBlacklist', "Loading $listType regex..." );

		if ( count( $this->files ) == 0 ) {
			# No lists
			wfDebugLog( 'SpamBlacklist', "no files specified\n" );
			return [];
		}

		$miss = false;

		$that = $this;
		$regexes = ObjectCache::getMainWANInstance()->getWithSetCallback(
			// This used to be cached per-site, but that could be bad on a shared
			// server where not all wikis have the same configuration.
			wfMemcKey( 'spamblacklist', $listType, 'shared-blacklist-regex' ),
			$this->expiryTime,
			function () use ( $that, &$miss ) {
				$miss = true;
				return $that->buildSharedBlacklists();
			}
		);

		if ( !$miss ) {
			wfDebugLog( 'SpamBlacklist', "Got shared spam regexes from cache\n" );
		}

		return $regexes;
	}

	/**
	 * Clear all primary blacklist cache keys
	 *
	 * @note: this method is unused atm
	 */
	function clearCache() {
		$listType = $this->getBlacklistType();

		$cache = ObjectCache::getMainWANInstance();
		$cache->delete( wfMemcKey( 'spamblacklist', $listType, 'shared-blacklist-regex' ) );
		$cache->delete( wfMemcKey( 'spamblacklist', $listType, 'blacklist-regex' ) );
		$cache->delete( wfMemcKey( 'spamblacklist', $listType, 'whitelist-regex' ) );

		wfDebugLog( 'SpamBlacklist', "$listType blacklist local cache cleared.\n" );
	}

	function buildSharedBlacklists() {
		$regexes = [];
		$listType = $this->getBlacklistType();
		# Load lists
		wfDebugLog( 'SpamBlacklist', "Constructing $listType blacklist\n" );
		foreach ( $this->files as $fileName ) {
			$matches = [];
			if ( preg_match( '/^DB: ([\w-]*) (.*)$/', $fileName, $matches ) ) {
				$text = $this->getArticleText( $matches[1], $matches[2] );
			} elseif ( preg_match( '/^(https?:)?\/\//', $fileName ) ) {
				$text = $this->getHttpText( $fileName );
			} else {
				$text = file_get_contents( $fileName );
				wfDebugLog( 'SpamBlacklist', "got from file $fileName\n" );
			}

			// Build a separate batch of regexes from each source.
			// While in theory we could squeeze a little efficiency
			// out of combining multiple sources in one regex, if
			// there's a bad line in one of them we'll gain more
			// from only having to break that set into smaller pieces.
			$regexes = array_merge( $regexes,
				SpamRegexBatch::regexesFromText( $text, $this, $fileName ) );
		}

		return $regexes;
	}

	function getHttpText( $fileName ) {
		global $wgDBname, $messageMemc;
		$listType = $this->getBlacklistType();

		# HTTP request
		# To keep requests to a minimum, we save results into $messageMemc, which is
		# similar to $wgMemc except almost certain to exist. By default, it is stored
		# in the database
		# There are two keys, when the warning key expires, a random thread will refresh
		# the real key. This reduces the chance of multiple requests under high traffic
		# conditions.
		$key = "{$listType}_blacklist_file:$fileName";
		$warningKey = "$wgDBname:{$listType}filewarning:$fileName";
		$httpText = $messageMemc->get( $key );
		$warning = $messageMemc->get( $warningKey );

		if ( !is_string( $httpText ) || ( !$warning && !mt_rand( 0, $this->warningChance ) ) ) {
			wfDebugLog( 'SpamBlacklist', "Loading $listType blacklist from $fileName\n" );
			$httpText = Http::get( $fileName );
			if ( $httpText === false ) {
				wfDebugLog( 'SpamBlacklist', "Error loading $listType blacklist from $fileName\n" );
			}
			$messageMemc->set( $warningKey, 1, $this->warningTime );
			$messageMemc->set( $key, $httpText, $this->expiryTime );
		} else {
			wfDebugLog( 'SpamBlacklist', "Got $listType blacklist from HTTP cache for $fileName\n" );
		}
		return $httpText;
	}

	/**
	 * Fetch an article from this or another local MediaWiki database.
	 * This is probably *very* fragile, and shouldn't be used perhaps.
	 *
	 * @param string $wiki
	 * @param string $article
	 * @return string
	 */
	function getArticleText( $wiki, $article ) {
		wfDebugLog( 'SpamBlacklist',
			"Fetching {$this->getBlacklistType()} blacklist from '$article' on '$wiki'...\n" );

		$title = Title::newFromText( $article );
		// Load all the relevant tables from the correct DB.
		// This assumes that old_text is the actual text or
		// that the external store system is at least unified.
		if ( is_callable( [ Revision::class, 'getQueryInfo' ] ) ) {
			$revQuery = Revision::getQueryInfo( [ 'page', 'text' ] );
		} else {
			$revQuery = [
				'tables' => [ 'revision', 'page', 'text' ],
				'fields' => array_merge(
					Revision::selectFields(),
					Revision::selectPageFields(),
					Revision::selectTextFields()
				),
				'joins' => [
					'text' => [ 'JOIN', 'old_id=rev_text_id' ]
				],
			];
		}
		$row = wfGetDB( DB_REPLICA, [], $wiki )->selectRow(
			$revQuery['tables'],
			$revQuery['fields'],
			[
				'page_namespace' => $title->getNamespace(), // assume NS IDs match
				'page_title' => $title->getDBkey(), // assume same case rules
			],
			__METHOD__,
			[],
			[ 'page' => [ 'JOIN', 'rev_id=page_latest' ] ] + $revQuery['joins']
		);

		return $row
			? ContentHandler::getContentText( Revision::newFromRow( $row )->getContent() )
			: false;
	}

	/**
	 * Returns the start of the regex for matches
	 *
	 * @return string
	 */
	public function getRegexStart() {
		return '/[a-z0-9_\-.]*';
	}

	/**
	 * Returns the end of the regex for matches
	 *
	 * @param int $batchSize
	 * @return string
	 */
	public function getRegexEnd( $batchSize ) {
		return ( $batchSize > 0 ) ? '/Sim' : '/im';
	}

	/**
	 * @param Title $title
	 * @param string[] $entries
	 */
	public function warmCachesForFilter( Title $title, array $entries ) {
		// subclass this
	}
}
