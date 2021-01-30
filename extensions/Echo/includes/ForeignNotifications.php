<?php

/**
 * Caches the result of EchoUnreadWikis::getUnreadCounts() and interprets the results in various useful ways.
 *
 * If the user has disabled cross-wiki notifications in their preferences
 * (see {@see EchoForeignNotifications::isEnabledByUser}), this class
 * won't do anything and will behave as if the user has no foreign notifications. For example, getCount() will
 * return 0. If you need to get foreign notification information for a user even though they may not have
 * enabled the preference, set $forceEnable=true in the constructor.
 */
class EchoForeignNotifications {
	/**
	 * @var User
	 */
	protected $user;

	/**
	 * @var bool
	 */
	protected $enabled = false;

	/**
	 * @var int[] [(str) section => (int) count, ...]
	 */
	protected $counts = [ EchoAttributeManager::ALERT => 0, EchoAttributeManager::MESSAGE => 0 ];

	/**
	 * @var array[] [(str) section => (string[]) wikis, ...]
	 */
	protected $wikis = [ EchoAttributeManager::ALERT => [], EchoAttributeManager::MESSAGE => [] ];

	/**
	 * @var array [(str) section => (MWTimestamp) timestamp, ...]
	 */
	protected $timestamps = [ EchoAttributeManager::ALERT => false, EchoAttributeManager::MESSAGE => false ];

	/**
	 * @var array[] [(str) wiki => [ (str) section => (MWTimestamp) timestamp, ...], ...]
	 */
	protected $wikiTimestamps = [];

	/**
	 * @var bool
	 */
	protected $populated = false;

	/**
	 * @param User $user
	 * @param bool $forceEnable Ignore the user's preferences and act as if they've enabled cross-wiki notifications
	 */
	public function __construct( User $user, $forceEnable = false ) {
		$this->user = $user;
		$this->enabled = $forceEnable || $this->isEnabledByUser();
	}

	/**
	 * Whether the user has enabled cross-wiki notifications.
	 * @return bool
	 */
	public function isEnabledByUser() {
		return (bool)$this->user->getOption( 'echo-cross-wiki-notifications' );
	}

	/**
	 * @param string $section Name of section
	 * @return int
	 */
	public function getCount( $section = EchoAttributeManager::ALL ) {
		$this->populate();

		if ( $section === EchoAttributeManager::ALL ) {
			$count = array_sum( $this->counts );
		} else {
			$count = $this->counts[$section] ?? 0;
		}

		return MWEchoNotifUser::capNotificationCount( $count );
	}

	/**
	 * @param string $section Name of section
	 * @return MWTimestamp|false
	 */
	public function getTimestamp( $section = EchoAttributeManager::ALL ) {
		$this->populate();

		if ( $section === EchoAttributeManager::ALL ) {
			$max = false;
			/** @var MWTimestamp $timestamp */
			foreach ( $this->timestamps as $timestamp ) {
				// $timestamp < $max = invert 0
				// $timestamp > $max = invert 1
				if ( $timestamp !== false && ( $max === false || $timestamp->diff( $max )->invert === 1 ) ) {
					$max = $timestamp;
				}
			}

			return $max;
		}

		return $this->timestamps[$section] ?? false;
	}

	/**
	 * @param string $section Name of section
	 * @return string[]
	 */
	public function getWikis( $section = EchoAttributeManager::ALL ) {
		$this->populate();

		if ( $section === EchoAttributeManager::ALL ) {
			$all = [];
			foreach ( $this->wikis as $wikis ) {
				$all = array_merge( $all, $wikis );
			}

			return array_unique( $all );
		}

		return $this->wikis[$section] ?? [];
	}

	public function getWikiTimestamp( $wiki, $section = EchoAttributeManager::ALL ) {
		$this->populate();
		if ( !isset( $this->wikiTimestamps[$wiki] ) ) {
			return false;
		}
		if ( $section === EchoAttributeManager::ALL ) {
			$max = false;
			foreach ( $this->wikiTimestamps[$wiki] as $section => $ts ) {
				// $ts < $max = invert 0
				// $ts > $max = invert 1
				if ( $max === false || $ts->diff( $max )->invert === 1 ) {
					$max = $ts;
				}
			}
			return $max;
		}
		return $this->wikiTimestamps[$wiki][$section] ?? false;
	}

	protected function populate() {
		if ( $this->populated ) {
			return;
		}

		if ( !$this->enabled ) {
			return;
		}

		$unreadWikis = EchoUnreadWikis::newFromUser( $this->user );
		if ( !$unreadWikis ) {
			return;
		}
		$unreadCounts = $unreadWikis->getUnreadCounts();
		if ( !$unreadCounts ) {
			return;
		}

		foreach ( $unreadCounts as $wiki => $sections ) {
			// exclude current wiki
			if ( $wiki === wfWikiID() ) {
				continue;
			}

			foreach ( $sections as $section => $data ) {
				if ( $data['count'] > 0 ) {
					$this->counts[$section] += $data['count'];
					$this->wikis[$section][] = $wiki;

					$timestamp = new MWTimestamp( $data['ts'] );
					$this->wikiTimestamps[$wiki][$section] = $timestamp;

					// We need $this->timestamp[$section] to be the max timestamp
					// across all wikis.
					// $timestamp < $this->timestamps[$section] = invert 0
					// $timestamp > $this->timestamps[$section] = invert 1
					if (
						$this->timestamps[$section] === false ||
						$timestamp->diff( $this->timestamps[$section] )->invert === 1
					) {
						$this->timestamps[$section] = new MWTimestamp( $data['ts'] );
					}

				}
			}
		}

		$this->populated = true;
	}

	/**
	 * @param string[] $wikis
	 * @return array[] [(string) wiki => (array) data]
	 */
	public static function getApiEndpoints( array $wikis ) {
		global $wgConf;
		$wgConf->loadFullData();

		$data = [];
		foreach ( $wikis as $wiki ) {
			$siteFromDB = $wgConf->siteFromDB( $wiki );
			list( $major, $minor ) = $siteFromDB;
			$server = $wgConf->get( 'wgServer', $wiki, $major, [ 'lang' => $minor, 'site' => $major ] );
			$scriptPath = $wgConf->get( 'wgScriptPath', $wiki, $major, [ 'lang' => $minor, 'site' => $major ] );
			$articlePath = $wgConf->get( 'wgArticlePath', $wiki, $major, [ 'lang' => $minor, 'site' => $major ] );

			$data[$wiki] = [
				'title' => static::getWikiTitle( $wiki, $siteFromDB ),
				'url' => wfExpandUrl( $server . $scriptPath . '/api.php', PROTO_INTERNAL ),
				// We need this to link to Special:Notifications page
				'base' => wfExpandUrl( $server . $articlePath, PROTO_INTERNAL ),
			];
		}

		return $data;
	}

	/**
	 * @param string $wikiId
	 * @param array|null $siteFromDB $wgConf->siteFromDB( $wikiId ) result
	 * @return mixed|string
	 */
	protected static function getWikiTitle( $wikiId, array $siteFromDB = null ) {
		global $wgConf, $wgLang;

		$msg = wfMessage( 'project-localized-name-' . $wikiId );
		// check if WikimediaMessages localized project names are available
		if ( $msg->exists() ) {
			return $msg->text();
		} else {
			// don't fetch $site, $langCode if known already
			if ( $siteFromDB === null ) {
				$siteFromDB = $wgConf->siteFromDB( $wikiId );
			}
			list( $site, $langCode ) = $siteFromDB;

			// try to fetch site name for this specific wiki, or fallback to the
			// general project's sitename if there is no override
			$wikiName = $wgConf->get( 'wgSitename', $wikiId ) ?: $wgConf->get( 'wgSitename', $site );
			$langName = Language::fetchLanguageName( $langCode, $wgLang->getCode() );

			if ( !$langName ) {
				// if we can't find a language name (in language-agnostic
				// project like mediawikiwiki), including the language name
				// doesn't make much sense
				return $wikiName;
			}

			// ... or use generic fallback
			return wfMessage( 'echo-foreign-wiki-lang', $wikiName, $langName )->text();
		}
	}
}
