<?php
/**
 * Title Blacklist class
 * @author Victor Vasiliev
 * @copyright © 2007-2010 Victor Vasiliev et al
 * @license GPL-2.0-or-later
 * @file
 */

use MediaWiki\MediaWikiServices;

/**
 * @ingroup Extensions
 */

/**
 * Represents a title blacklist entry
 */
class TitleBlacklistEntry {
	/**
	 * Raw line
	 * @var string
	 */
	private $mRaw;

	/**
	 * Regular expression to match
	 * @var string
	 */
	private $mRegex;

	/**
	 * Parameters for this entry
	 * @var array
	 */
	private $mParams;

	/**
	 * Entry format version
	 * @var int
	 */
	private $mFormatVersion;

	/**
	 * Source of this entry
	 * @var string
	 */
	private $mSource;

	/**
	 * @param string $regex Regular expression to match
	 * @param array $params Parameters for this entry
	 * @param string $raw Raw contents of this line
	 * @param string $source
	 */
	private function __construct( $regex, $params, $raw, $source ) {
		$this->mRaw = $raw;
		$this->mRegex = $regex;
		$this->mParams = $params;
		$this->mFormatVersion = TitleBlacklist::VERSION;
		$this->mSource = $source;
	}

	/**
	 * Returns whether this entry is capable of filtering new accounts.
	 * @return bool
	 */
	private function filtersNewAccounts() {
		global $wgTitleBlacklistUsernameSources;

		if ( $wgTitleBlacklistUsernameSources === '*' ) {
			return true;
		}

		if ( !$wgTitleBlacklistUsernameSources ) {
			return false;
		}

		if ( !is_array( $wgTitleBlacklistUsernameSources ) ) {
			throw new Exception(
				'$wgTitleBlacklistUsernameSources must be "*", false or an array' );
		}

		return in_array( $this->mSource, $wgTitleBlacklistUsernameSources, true );
	}

	/**
	 * Check whether a user can perform the specified action on the specified Title
	 *
	 * @param string $title Title to check
	 * @param string $action Action to check
	 * @return bool TRUE if the regex matches the title, and is not overridden
	 * else false if it doesn't match (or was overridden)
	 */
	public function matches( $title, $action ) {
		if ( $title == '' ) {
			return false;
		}

		if ( $action === 'new-account' && !$this->filtersNewAccounts() ) {
			return false;
		}

		if ( isset( $this->mParams['antispoof'] )
			&& ExtensionRegistry::getInstance()->isLoaded( 'AntiSpoof' )
		) {
			if ( $action === 'edit' ) {
				// Use process cache for frequently edited pages
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$status = $cache->getWithSetCallback(
					$cache->makeKey( 'titleblacklist', 'normalized-unicode-status', md5( $title ) ),
					$cache::TTL_MONTH,
					function () use ( $title ) {
						return AntiSpoof::checkUnicodeStringStatus( $title );
					},
					[ 'pcTTL' => $cache::TTL_PROC_LONG ]
				);
			} else {
				$status = AntiSpoof::checkUnicodeStringStatus( $title );
			}

			if ( $status->isOK() ) {
				// Remove version from return value
				list( , $title ) = explode( ':', $status->getValue(), 2 );
			} else {
				wfDebugLog( 'TitleBlacklist', 'AntiSpoof could not normalize "' . $title . '" ' .
					$status->getMessage( false, false, 'en' )->text() . '.'
				);
			}
		}

		Wikimedia\suppressWarnings();
		$match = preg_match(
			"/^(?:{$this->mRegex})$/us" . ( isset( $this->mParams['casesensitive'] ) ? '' : 'i' ),
			$title
		);
		Wikimedia\restoreWarnings();

		if ( $match ) {
			if ( isset( $this->mParams['moveonly'] ) && $action != 'move' ) {
				return false;
			}
			if ( isset( $this->mParams['newaccountonly'] ) && $action != 'new-account' ) {
				return false;
			}
			if ( !isset( $this->mParams['noedit'] ) && $action == 'edit' ) {
				return false;
			}
			if ( isset( $this->mParams['reupload'] ) && $action == 'upload' ) {
				// Special:Upload also checks 'create' permissions when not reuploading
				return false;
			}
			return true;
		}

		return false;
	}

	/**
	 * Create a new TitleBlacklistEntry from a line of text
	 *
	 * @param string $line String containing a line of blacklist text
	 * @param string $source
	 * @return TitleBlacklistEntry|null
	 */
	public static function newFromString( $line, $source ) {
		$raw = $line; // Keep line for raw data
		$options = [];
		// Strip comments
		$line = preg_replace( "/^\\s*([^#]*)\\s*((.*)?)$/", "\\1", $line );
		$line = trim( $line );
		// A blank string causes problems later on
		if ( $line === '' ) {
			return null;
		}
		// Parse the rest of message
		$pockets = [];
		if ( !preg_match( '/^(.*?)(\s*<([^<>]*)>)?$/', $line, $pockets ) ) {
			return null;
		}
		$regex = trim( $pockets[1] );
		$regex = str_replace( '_', ' ', $regex ); // We'll be matching against text form
		$opts_str = isset( $pockets[3] ) ? trim( $pockets[3] ) : '';
		// Parse opts
		$opts = preg_split( '/\s*\|\s*/', $opts_str );
		foreach ( $opts as $opt ) {
			$opt2 = strtolower( $opt );
			if ( $opt2 == 'autoconfirmed' ) {
				$options['autoconfirmed'] = true;
			}
			if ( $opt2 == 'moveonly' ) {
				$options['moveonly'] = true;
			}
			if ( $opt2 == 'newaccountonly' ) {
				$options['newaccountonly'] = true;
			}
			if ( $opt2 == 'noedit' ) {
				$options['noedit'] = true;
			}
			if ( $opt2 == 'casesensitive' ) {
				$options['casesensitive'] = true;
			}
			if ( $opt2 == 'reupload' ) {
				$options['reupload'] = true;
			}
			if ( preg_match( '/errmsg\s*=\s*(.+)/i', $opt, $matches ) ) {
				$options['errmsg'] = $matches[1];
			}
			if ( $opt2 == 'antispoof' ) {
				$options['antispoof'] = true;
			}
		}
		// Process magic words
		preg_match_all( '/{{\s*([a-z]+)\s*:\s*(.+?)\s*}}/', $regex, $magicwords, PREG_SET_ORDER );
		foreach ( $magicwords as $mword ) {
			switch ( strtolower( $mword[1] ) ) {
				case 'ns':
					$cpf_result = CoreParserFunctions::ns(
						MediaWikiServices::getInstance()->getParser(),
						$mword[2]
					);
					if ( is_string( $cpf_result ) ) {
						// All result will have the same value, so we can just use str_seplace()
						$regex = str_replace( $mword[0], $cpf_result, $regex );
					}
					break;
				case 'int':
					$cpf_result = wfMessage( $mword[2] )->inContentLanguage()->text();
					if ( is_string( $cpf_result ) ) {
						$regex = str_replace( $mword[0], $cpf_result, $regex );
					}
			}
		}
		// Return result
		if ( $regex ) {
			return new TitleBlacklistEntry( $regex, $options, $raw, $source );
		} else {
			return null;
		}
	}

	/**
	 * @return string This entry's regular expression
	 */
	public function getRegex() {
		return $this->mRegex;
	}

	/**
	 * @return string This entry's raw line
	 */
	public function getRaw() {
		return $this->mRaw;
	}

	/**
	 * @return array This entry's parameters
	 */
	public function getParams() {
		return $this->mParams;
	}

	/**
	 * @return string Custom message for this entry
	 */
	public function getCustomMessage() {
		return $this->mParams['errmsg'] ?? null;
	}

	/**
	 * @return int The format version
	 */
	public function getFormatVersion() {
		return $this->mFormatVersion;
	}

	/**
	 * Set the format version
	 *
	 * @param int $v New version to set
	 */
	public function setFormatVersion( $v ) {
		$this->mFormatVersion = $v;
	}

	/**
	 * Return the error message name for the blacklist entry.
	 *
	 * @param string $operation Operation name (as in titleblacklist-forbidden message name)
	 *
	 * @return string The error message name
	 */
	public function getErrorMessage( $operation ) {
		$message = $this->getCustomMessage();
		// For grep:
		// titleblacklist-forbidden-edit, titleblacklist-forbidden-move,
		// titleblacklist-forbidden-upload, titleblacklist-forbidden-new-account
		return $message ?: "titleblacklist-forbidden-{$operation}";
	}
}
