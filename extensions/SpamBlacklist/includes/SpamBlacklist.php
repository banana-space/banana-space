<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;

class SpamBlacklist extends BaseBlacklist {
	const STASH_TTL = 180;
	const STASH_AGE_DYING = 150;

	/**
	 * Returns the code for the blacklist implementation
	 *
	 * @return string
	 */
	protected function getBlacklistType() {
		return 'spam';
	}

	/**
	 * Apply some basic anti-spoofing to the links before they get filtered,
	 * see @bug 12896
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected function antiSpoof( $text ) {
		$text = str_replace( '．', '.', $text );
		return $text;
	}

	/**
	 * @param string[] $links An array of links to check against the blacklist
	 * @param ?Title $title The title of the page to which the filter shall be applied.
	 *               This is used to load the old links already on the page, so
	 *               the filter is only applied to links that got added. If not given,
	 *               the filter is applied to all $links.
	 * @param bool $preventLog Whether to prevent logging of hits. Set to true when
	 *               the action is testing the links rather than attempting to save them
	 *               (e.g. the API spamblacklist action)
	 * @param string $mode Either 'check' or 'stash'
	 * @param User|null $user Relevant user, only optional for backwards compatibility
	 *
	 * @return string[]|bool Matched text(s) if the edit should not be allowed; false otherwise
	 */
	public function filter(
		array $links,
		?Title $title,
		$preventLog = false,
		$mode = 'check',
		User $user = null
	) {
		$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$cache = ObjectCache::getLocalClusterInstance();

		if ( !$links ) {
			return false;
		}

		if ( $user === null ) {
			wfDebugLog( 'SpamBlacklist', 'filter called without user specified' );
			$user = RequestContext::getMain()->getUser();
		}

		sort( $links );
		$key = $cache->makeKey(
			'blacklist',
			$this->getBlacklistType(),
			'pass',
			sha1( implode( "\n", $links ) ),
			md5( (string)$title )
		);
		// Skip blacklist checks if nothing matched during edit stashing...
		$knownNonMatchAsOf = $cache->get( $key );
		if ( $mode === 'check' ) {
			if ( $knownNonMatchAsOf ) {
				$statsd->increment( 'spamblacklist.check-stash.hit' );

				return false;
			} else {
				$statsd->increment( 'spamblacklist.check-stash.miss' );
			}
		} elseif ( $mode === 'stash' ) {
			if ( $knownNonMatchAsOf && ( time() - $knownNonMatchAsOf ) < self::STASH_AGE_DYING ) {
				return false; // OK; not about to expire soon
			}
		}

		$blacklists = $this->getBlacklists();
		$whitelists = $this->getWhitelists();

		if ( count( $blacklists ) ) {
			// poor man's anti-spoof, see bug 12896
			$newLinks = array_map( [ $this, 'antiSpoof' ], $links );

			$oldLinks = [];
			if ( $title !== null ) {
				$oldLinks = $this->getCurrentLinks( $title );
				$addedLinks = array_diff( $newLinks, $oldLinks );
			} else {
				// can't load old links, so treat all links as added.
				$addedLinks = $newLinks;
			}

			wfDebugLog( 'SpamBlacklist', "Old URLs: " . implode( ', ', $oldLinks ) );
			wfDebugLog( 'SpamBlacklist', "New URLs: " . implode( ', ', $newLinks ) );
			wfDebugLog( 'SpamBlacklist', "Added URLs: " . implode( ', ', $addedLinks ) );

			$links = implode( "\n", $addedLinks );

			# Strip whitelisted URLs from the match
			if ( is_array( $whitelists ) ) {
				wfDebugLog( 'SpamBlacklist', "Excluding whitelisted URLs from " . count( $whitelists ) .
					" regexes: " . implode( ', ', $whitelists ) . "\n" );
				foreach ( $whitelists as $regex ) {
					Wikimedia\suppressWarnings();
					$newLinks = preg_replace( $regex, '', $links );
					Wikimedia\restoreWarnings();
					if ( is_string( $newLinks ) ) {
						// If there wasn't a regex error, strip the matching URLs
						$links = $newLinks;
					}
				}
			}

			# Do the match
			wfDebugLog( 'SpamBlacklist', "Checking text against " . count( $blacklists ) .
				" regexes: " . implode( ', ', $blacklists ) . "\n" );
			$retVal = false;
			foreach ( $blacklists as $regex ) {
				Wikimedia\suppressWarnings();
				$matches = [];
				$check = ( preg_match_all( $regex, $links, $matches ) > 0 );
				Wikimedia\restoreWarnings();
				if ( $check ) {
					wfDebugLog( 'SpamBlacklist', "Match!\n" );
					$ip = RequestContext::getMain()->getRequest()->getIP();
					$fullUrls = [];
					$fullLineRegex = substr( $regex, 0, strrpos( $regex, '/' ) ) . '.*/Sim';
					preg_match_all( $fullLineRegex, $links, $fullUrls );
					$imploded = implode( ' ', $fullUrls[0] );
					wfDebugLog( 'SpamBlacklistHit', "$ip caught submitting spam: $imploded\n" );
					if ( !$preventLog && $title ) {
						$this->logFilterHit( $user, $title, $imploded ); // Log it
					}
					if ( $retVal === false ) {
						$retVal = [];
					}
					$retVal = array_merge( $retVal, $fullUrls[1] );
				}
			}
			if ( is_array( $retVal ) ) {
				$retVal = array_unique( $retVal );
			}
		} else {
			$retVal = false;
		}

		if ( $retVal === false ) {
			// Cache the typical negative results
			$cache->set( $key, time(), self::STASH_TTL );
			if ( $mode === 'stash' ) {
				$statsd->increment( 'spamblacklist.check-stash.store' );
			}
		}

		return $retVal;
	}

	/**
	 * Look up the links currently in the article, so we can
	 * ignore them on a second run.
	 *
	 * WARNING: I can add more *of the same link* with no problem here.
	 * @param Title $title
	 * @return array
	 */
	public function getCurrentLinks( Title $title ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;
		return $cache->getWithSetCallback(
			// Key is warmed via warmCachesForFilter() from ApiStashEdit
			$cache->makeKey( 'external-link-list', $title->getLatestRevID() ),
			$cache::TTL_MINUTE,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $title, $fname ) {
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				return $dbr->selectFieldValues(
					'externallinks',
					'el_to',
					[ 'el_from' => $title->getArticleID() ], // should be zero queries
					$fname
				);
			}
		);
	}

	public function warmCachesForFilter( Title $title, array $entries ) {
		$this->filter( $entries, $title, true /* no logging */, 'stash' );
	}

	/**
	 * Returns the start of the regex for matches
	 *
	 * @return string
	 */
	public function getRegexStart() {
		return '/(?:https?:)?\/\/+[a-z0-9_\-.]*(';
	}

	/**
	 * Returns the end of the regex for matches
	 *
	 * @param int $batchSize
	 * @return string
	 */
	public function getRegexEnd( $batchSize ) {
		return ')' . parent::getRegexEnd( $batchSize );
	}

	/**
	 * Logs the filter hit to Special:Log if
	 * $wgLogSpamBlacklistHits is enabled.
	 *
	 * @param User $user
	 * @param Title $title
	 * @param string $url URL that the user attempted to add
	 */
	public function logFilterHit( User $user, $title, $url ) {
		global $wgLogSpamBlacklistHits;
		if ( $wgLogSpamBlacklistHits ) {
			$logEntry = new ManualLogEntry( 'spamblacklist', 'hit' );
			$logEntry->setPerformer( $user );
			$logEntry->setTarget( $title );
			$logEntry->setParameters( [
				'4::url' => $url,
			] );
			$logid = $logEntry->insert();
			$log = new LogPage( 'spamblacklist' );
			if ( $log->isRestricted() ) {
				// Make sure checkusers can see this action if the log is restricted
				// (which is the default)
				if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' )
					&& class_exists( CheckUserHooks::class )
				) {
					$rc = $logEntry->getRecentChange( $logid );
					CheckUserHooks::updateCheckUserData( $rc );
				}
			} else {
				// If the log is unrestricted, publish normally to RC,
				// which will also update checkuser
				$logEntry->publish( $logid, "rc" );
			}
		}
	}
}
