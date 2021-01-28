<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\IPUtils;

/**
 * Demo CAPTCHA (not for production usage) and base class for real CAPTCHAs
 */
class SimpleCaptcha {
	protected static $messagePrefix = 'captcha-';

	/** @var boolean|null Was the CAPTCHA already passed and if yes, with which result? */
	private $captchaSolved = null;

	/**
	 * Used to select the right message.
	 * One of sendmail, createaccount, badlogin, edit, create, addurl.
	 * @var string
	 */
	protected $action;

	/** @var string Used in log messages. */
	protected $trigger;

	/**
	 * @param string $action
	 */
	public function setAction( $action ) {
		$this->action = $action;
	}

	/**
	 * @param string $trigger
	 */
	public function setTrigger( $trigger ) {
		$this->trigger = $trigger;
	}

	/**
	 * Return the error from the last passCaptcha* call.
	 * Not implemented but needed by some child classes.
	 * @return mixed
	 */
	public function getError() {
		return null;
	}

	/**
	 * Returns an array with 'question' and 'answer' keys.
	 * Subclasses might use different structure.
	 * Since MW 1.27 all subclasses must implement this method.
	 * @return array
	 */
	public function getCaptcha() {
		$a = mt_rand( 0, 100 );
		$b = mt_rand( 0, 10 );

		/* Minus sign is used in the question. UTF-8,
		   since the api uses text/plain, not text/html */
		$op = mt_rand( 0, 1 ) ? '+' : '−';

		// No space before and after $op, to ensure correct
		// directionality.
		$test = "$a$op$b";
		$answer = ( $op == '+' ) ? ( $a + $b ) : ( $a - $b );
		return [ 'question' => $test, 'answer' => $answer ];
	}

	/**
	 * @param array &$resultArr
	 */
	protected function addCaptchaAPI( &$resultArr ) {
		$captcha = $this->getCaptcha();
		$index = $this->storeCaptcha( $captcha );
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['id'] = $index;
		$resultArr['captcha']['question'] = $captcha['question'];
	}

	/**
	 * Describes the captcha type for API clients.
	 * @return array An array with keys 'type' and 'mime', and possibly other
	 *   implementation-specific
	 */
	public function describeCaptchaType() {
		return [
			'type' => 'simple',
			'mime' => 'text/plain',
		];
	}

	/**
	 * Insert a captcha prompt into the edit form.
	 * This sample implementation generates a simple arithmetic operation;
	 * it would be easy to defeat by machine.
	 *
	 * Override this!
	 *
	 * It is not guaranteed that the CAPTCHA will load synchronously with the main page
	 * content. So you can not rely on registering handlers before page load. E.g.:
	 *
	 * NOT SAFE: $( window ).on( 'load', handler )
	 * SAFE: $( handler )
	 *
	 * However, if the HTML is loaded dynamically via AJAX, the following order will
	 * be used.
	 *
	 * headitems => modulestyles + modules => add main HTML to DOM when modulestyles +
	 * modules are ready.
	 *
	 * @param int $tabIndex Tab index to start from
	 *
	 * @return array Associative array with the following keys:
	 *   string html - Main HTML
	 *   array modules (optional) - Array of ResourceLoader module names
	 *   array modulestyles (optional) - Array of ResourceLoader module names to be
	 * 		included as style-only modules.
	 *   array headitems (optional) - Head items (see OutputPage::addHeadItems), as a numeric array
	 * 		of raw HTML strings. Do not use unless no other option is feasible.
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		$captcha = $this->getCaptcha();
		$index = $this->storeCaptcha( $captcha );

		return [
			'html' =>
				new OOUI\FieldLayout(
					new OOUI\NumberInputWidget( [
						'name' => 'wpCaptchaWord',
						'classes' => [ 'simplecaptcha-answer' ],
						'id' => 'wpCaptchaWord',
						'autocomplete' => 'off',
						// tab in before the edit textarea
						'tabIndex' => $tabIndex
					] ),
					[
						'align' => 'left',
						'label' => $captcha['question'] . ' = ',
						'classes' => [ 'simplecaptcha-field' ],
					]
				) .
				new OOUI\HiddenInputWidget( [
					'name' => 'wpCaptchaId',
					'id' => 'wpCaptchaId',
					'value' => $index
				] ),
			'modulestyles' => [
				'ext.confirmEdit.simpleCaptcha'
			]
		];
	}

	/**
	 * @return string[]
	 */
	public static function getCSPUrls() {
		return [];
	}

	/**
	 * Adds the CSP policies necessary for the captcha module to work in a CSP enforced
	 * setup.
	 *
	 * @param ContentSecurityPolicy $csp The CSP instance to add the policies to, usually
	 * obtained from {@link OutputPage::getCSP()}
	 */
	public static function addCSPSources( ContentSecurityPolicy $csp ) {
		foreach ( static::getCSPUrls() as $src ) {
			$csp->addScriptSrc( $src );
			$csp->addStyleSrc( $src );
		}
	}

	/**
	 * Uses getFormInformation() to get the CAPTCHA form and adds it to the given
	 * OutputPage object.
	 *
	 * @param OutputPage $out The OutputPage object to which the form should be added
	 * @param int $tabIndex See self::getFormInformation
	 */
	public function addFormToOutput( OutputPage $out, $tabIndex = 1 ) {
		$this->addFormInformationToOutput( $out, $this->getFormInformation( $tabIndex ) );
	}

	/**
	 * Processes the given $formInformation array and adds the options (see getFormInformation())
	 * to the given OutputPage object.
	 *
	 * @param OutputPage $out The OutputPage object to which the form should be added
	 * @param array $formInformation
	 */
	public function addFormInformationToOutput( OutputPage $out, array $formInformation ) {
		static::addCSPSources( $out->getCSP() );

		if ( !$formInformation ) {
			return;
		}
		if ( isset( $formInformation['html'] ) ) {
			$out->addHTML( $formInformation['html'] );
		}
		if ( isset( $formInformation['modules'] ) ) {
			$out->addModules( $formInformation['modules'] );
		}
		if ( isset( $formInformation['modulestyles'] ) ) {
			$out->addModuleStyles( $formInformation['modulestyles'] );
		}
		if ( isset( $formInformation['headitems'] ) ) {
			$out->addHeadItems( $formInformation['headitems'] );
		}
	}

	/**
	 * @param array $captchaData Data given by getCaptcha
	 * @param string $id ID given by storeCaptcha
	 * @return string Description of the captcha. Format is not specified; could be text, HTML, URL...
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return array_key_exists( 'question', $captchaData ) ? ( $captchaData['question'] . ' =' ) : '';
	}

	/**
	 * Show error message for missing or incorrect captcha on EditPage.
	 * @param EditPage $editPage
	 * @param OutputPage $out
	 */
	public function showEditFormFields( EditPage $editPage, OutputPage $out ) {
		$out->enableOOUI();
		$page = $editPage->getArticle()->getPage();
		if ( !isset( $page->ConfirmEdit_ActivateCaptcha ) ) {
			return;
		}

		if ( $this->action !== 'edit' ) {
			unset( $page->ConfirmEdit_ActivateCaptcha );
			$out->addHTML( $this->getMessage( $this->action )->parseAsBlock() );
			$this->addFormToOutput( $out );
		}
	}

	/**
	 * Insert the captcha prompt into an edit form.
	 * @param EditPage $editPage
	 */
	public function editShowCaptcha( $editPage ) {
		$context = $editPage->getArticle()->getContext();
		$page = $editPage->getArticle()->getPage();
		$out = $context->getOutput();
		if ( isset( $page->ConfirmEdit_ActivateCaptcha ) ||
			$this->shouldCheck( $page, '', '', $context )
		) {
			$out->addHTML( $this->getMessage( $this->action )->parseAsBlock() );
			$this->addFormToOutput( $out );
		}
		unset( $page->ConfirmEdit_ActivateCaptcha );
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param string $action Action being performed
	 * @return Message
	 */
	public function getMessage( $action ) {
		// one of captcha-edit, captcha-addurl, captcha-badlogin, captcha-createaccount,
		// captcha-create, captcha-sendemail
		$name = static::$messagePrefix . $action;
		$msg = wfMessage( $name );
		// obtain a more tailored message, if possible, otherwise, fall back to
		// the default for edits
		return $msg->isDisabled() ? wfMessage( static::$messagePrefix . 'edit' ) : $msg;
	}

	/**
	 * Inject whazawhoo
	 * @fixme if multiple thingies insert a header, could break
	 * @param HTMLForm $form
	 * @return bool true to keep running callbacks
	 */
	public function injectEmailUser( HTMLForm $form ) {
		$out = $form->getOutput();
		$user = $form->getUser();
		if ( $this->triggersCaptcha( CaptchaTriggers::SENDEMAIL ) ) {
			$this->action = 'sendemail';
			if ( $this->canSkipCaptcha( $user, $form->getConfig() ) ) {
				return true;
			}
			$formInformation = $this->getFormInformation();
			$formMetainfo = $formInformation;
			unset( $formMetainfo['html'] );
			$this->addFormInformationToOutput( $out, $formMetainfo );
			$form->addFooterText(
				"<div class='captcha'>" .
				$this->getMessage( 'sendemail' )->parseAsBlock() .
				$formInformation['html'] .
				"</div>\n" );
		}
		return true;
	}

	/**
	 * Increase bad login counter after a failed login.
	 * The user might be required to solve a captcha if the count is high.
	 * @param string $username
	 * TODO use Throttler
	 */
	public function increaseBadLoginCounter( $username ) {
		global $wgCaptchaBadLoginExpiration, $wgCaptchaBadLoginPerUserExpiration;

		$cache = ObjectCache::getLocalClusterInstance();

		if ( $this->triggersCaptcha( CaptchaTriggers::BAD_LOGIN ) ) {
			$key = $this->badLoginKey( $cache );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginExpiration );
		}

		if ( $this->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER ) && $username ) {
			$key = $this->badLoginPerUserKey( $username, $cache );
			$cache->incrWithInit( $key, $wgCaptchaBadLoginPerUserExpiration );
		}
	}

	/**
	 * Reset bad login counter after a successful login.
	 * @param string $username
	 */
	public function resetBadLoginCounter( $username ) {
		if ( $this->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER ) && $username ) {
			$cache = ObjectCache::getLocalClusterInstance();
			$cache->delete( $this->badLoginPerUserKey( $username, $cache ) );
		}
	}

	/**
	 * Check if a bad login has already been registered for this
	 * IP address. If so, require a captcha.
	 * @return bool
	 * @private
	 */
	public function isBadLoginTriggered() {
		global $wgCaptchaBadLoginAttempts;

		$cache = ObjectCache::getLocalClusterInstance();
		return $this->triggersCaptcha( CaptchaTriggers::BAD_LOGIN )
			&& (int)$cache->get( $this->badLoginKey( $cache ) ) >= $wgCaptchaBadLoginAttempts;
	}

	/**
	 * Is the per-user captcha triggered?
	 *
	 * @param User|string $u User object, or name
	 * @return bool|null False: no, null: no, but it will be triggered next time
	 */
	public function isBadLoginPerUserTriggered( $u ) {
		global $wgCaptchaBadLoginPerUserAttempts;

		$cache = ObjectCache::getLocalClusterInstance();

		if ( is_object( $u ) ) {
			$u = $u->getName();
		}
		$badLoginPerUserKey = $this->badLoginPerUserKey( $u, $cache );
		return $this->triggersCaptcha( CaptchaTriggers::BAD_LOGIN_PER_USER )
			&& (int)$cache->get( $badLoginPerUserKey ) >= $wgCaptchaBadLoginPerUserAttempts;
	}

	/**
	 * Check if the current IP is allowed to skip captchas. This checks
	 * the whitelist from two sources.
	 *  1) From the server-side config array $wgCaptchaWhitelistIP
	 *  2) From the local [[MediaWiki:Captcha-ip-whitelist]] message
	 *
	 * @return bool true if whitelisted, false if not
	 */
	private function isIPWhitelisted() {
		global $wgCaptchaWhitelistIP, $wgRequest;
		$ip = $wgRequest->getIP();

		if ( $wgCaptchaWhitelistIP ) {
			if ( IPUtils::isInRanges( $ip, $wgCaptchaWhitelistIP ) ) {
				return true;
			}
		}

		$whitelistMsg = wfMessage( 'captcha-ip-whitelist' )->inContentLanguage();
		if ( !$whitelistMsg->isDisabled() ) {
			$whitelistedIPs = $this->getWikiIPWhitelist( $whitelistMsg );
			if ( IPUtils::isInRanges( $ip, $whitelistedIPs ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the on-wiki IP whitelist stored in [[MediaWiki:Captcha-ip-whitelist]]
	 * page from cache if possible.
	 *
	 * @param Message $msg whitelist Message on wiki
	 * @return array whitelisted IP addresses or IP ranges, empty array if no whitelist
	 */
	private function getWikiIPWhitelist( Message $msg ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cacheKey = $cache->makeKey( 'confirmedit', 'ipwhitelist' );

		$cachedWhitelist = $cache->get( $cacheKey );
		if ( $cachedWhitelist === false ) {
			// Could not retrieve from cache so build the whitelist directly
			// from the wikipage
			$whitelist = $this->buildValidIPs(
				explode( "\n", $msg->plain() )
			);
			// And then store it in cache for one day. This cache is cleared on
			// modifications to the whitelist page.
			// @see ConfirmEditHooks::onPageSaveComplete()
			$cache->set( $cacheKey, $whitelist, 86400 );
		} else {
			// Whitelist from the cache
			$whitelist = $cachedWhitelist;
		}

		return $whitelist;
	}

	/**
	 * From a list of unvalidated input, get all the valid
	 * IP addresses and IP ranges from it.
	 *
	 * Note that only lines with just the IP address or IP range is considered
	 * as valid. Whitespace is allowed but if there is any other character on
	 * the line, it's not considered as a valid entry.
	 *
	 * @param string[] $input
	 * @return string[] of valid IP addresses and IP ranges
	 */
	private function buildValidIPs( array $input ) {
		// Remove whitespace and blank lines first
		$ips = array_map( 'trim', $input );
		$ips = array_filter( $ips );

		$validIPs = [];
		foreach ( $ips as $ip ) {
			if ( IPUtils::isIPAddress( $ip ) ) {
				$validIPs[] = $ip;
			}
		}

		return $validIPs;
	}

	/**
	 * Internal cache key for badlogin checks.
	 * @param BagOStuff $cache
	 * @return string
	 */
	private function badLoginKey( BagOStuff $cache ) {
		global $wgRequest;
		$ip = $wgRequest->getIP();

		return $cache->makeGlobalKey( 'captcha', 'badlogin', 'ip', $ip );
	}

	/**
	 * Cache key for badloginPerUser checks.
	 * @param string $username
	 * @param BagOStuff $cache
	 * @return string
	 */
	private function badLoginPerUserKey( $username, BagOStuff $cache ) {
		$username = User::getCanonicalName( $username, 'usable' ) ?: $username;

		return $cache->makeGlobalKey(
			'captcha', 'badlogin', 'user', md5( $username )
		);
	}

	/**
	 * Check if the submitted form matches the captcha session data provided
	 * by the plugin when the form was generated.
	 *
	 * Override this!
	 *
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	protected function keyMatch( $answer, $info ) {
		return $answer == $info['answer'];
	}

	// ----------------------------------

	/**
	 * @param Title $title
	 * @param string $action (edit/create/addurl...)
	 * @return bool true if action triggers captcha on $title's namespace
	 * @deprecated since 1.5.1 Use triggersCaptcha instead
	 */
	public function captchaTriggers( $title, $action ) {
		return $this->triggersCaptcha( $action, $title );
	}

	/**
	 * Checks, whether the passed action should trigger a CAPTCHA. The optional $title parameter
	 * will be used to check namespace specific CAPTCHA triggers.
	 *
	 * @param string $action The CAPTCHA trigger to check (see CaptchaTriggers for ConfirmEdit
	 * built-in triggers)
	 * @param Title|null $title An optional Title object, if the namespace specific triggers
	 * should be checked, too.
	 * @return bool True, if the action should trigger a CAPTCHA, false otherwise
	 */
	public function triggersCaptcha( $action, $title = null ) {
		global $wgCaptchaTriggers, $wgCaptchaTriggersOnNamespace;

		$result = false;
		$triggers = $wgCaptchaTriggers;
		$attributeCaptchaTriggers = ExtensionRegistry::getInstance()
			->getAttribute( CaptchaTriggers::EXT_REG_ATTRIBUTE_NAME );
		if ( is_array( $attributeCaptchaTriggers ) ) {
			$triggers += $attributeCaptchaTriggers;
		}

		if ( isset( $triggers[$action] ) ) {
			$result = $triggers[$action];
		}

		if (
			$title !== null &&
			isset( $wgCaptchaTriggersOnNamespace[$title->getNamespace()][$action] )
		) {
			$result = $wgCaptchaTriggersOnNamespace[$title->getNamespace()][$action];
		}

		return $result;
	}

	/**
	 * @param WikiPage $page
	 * @param Content|string $content
	 * @param string $section
	 * @param IContextSource $context
	 * @param string|null $oldtext The content of the revision prior to $content When
	 *  null this will be loaded from the database.
	 * @return bool true if the captcha should run
	 */
	public function shouldCheck( WikiPage $page, $content, $section, $context, $oldtext = null ) {
		if ( !$context instanceof IContextSource ) {
			$context = RequestContext::getMain();
		}

		$request = $context->getRequest();
		$user = $context->getUser();

		if ( $this->canSkipCaptcha( $user, $context->getConfig() ) ) {
			return false;
		}

		$title = $page->getTitle();
		$this->trigger = '';

		if ( $content instanceof Content ) {
			if ( $content->getModel() == CONTENT_MODEL_WIKITEXT ) {
				$newtext = $content->getNativeData();
			} else {
				$newtext = null;
			}
			$isEmpty = $content->isEmpty();
		} else {
			$newtext = $content;
			$isEmpty = $content === '';
		}

		if ( $this->triggersCaptcha( 'edit', $title ) ) {
			// Check on all edits
			$this->trigger = sprintf( "edit trigger by '%s' at [[%s]]",
				$user->getName(),
				$title->getPrefixedText() );
			$this->action = 'edit';
			wfDebug( "ConfirmEdit: checking all edits...\n" );
			return true;
		}

		if ( $this->triggersCaptcha( 'create', $title ) && !$title->exists() ) {
			// Check if creating a page
			$this->trigger = sprintf( "Create trigger by '%s' at [[%s]]",
				$user->getName(),
				$title->getPrefixedText() );
			$this->action = 'create';
			wfDebug( "ConfirmEdit: checking on page creation...\n" );
			return true;
		}

		// The following checks are expensive and should be done only,
		// if we can assume, that the edit will be saved
		if ( !$request->wasPosted() ) {
			wfDebug(
				"ConfirmEdit: request not posted, assuming that no content will be saved -> no CAPTCHA check"
			);
			return false;
		}

		if ( !$isEmpty && $this->triggersCaptcha( 'addurl', $title ) ) {
			// Only check edits that add URLs
			if ( $content instanceof Content ) {
				// Get links from the database
				$oldLinks = $this->getLinksFromTracker( $title );
				// Share a parse operation with Article::doEdit()
				$editInfo = $page->prepareContentForEdit( $content );
				if ( $editInfo->output ) {
					$newLinks = array_keys( $editInfo->output->getExternalLinks() );
				} else {
					$newLinks = [];
				}
			} else {
				// Get link changes in the slowest way known to man
				if ( $oldtext === null ) {
					$oldtext = $this->loadText( $title, $section );
				}
				$oldLinks = $this->findLinks( $title, $oldtext );
				$newLinks = $this->findLinks( $title, $newtext );
			}

			$unknownLinks = array_filter( $newLinks, [ $this, 'filterLink' ] );
			$addedLinks = array_diff( $unknownLinks, $oldLinks );
			$numLinks = count( $addedLinks );

			if ( $numLinks > 0 ) {
				$this->trigger = sprintf( "%dx url trigger by '%s' at [[%s]]: %s",
					$numLinks,
					$user->getName(),
					$title->getPrefixedText(),
					implode( ", ", $addedLinks ) );
				$this->action = 'addurl';
				return true;
			}
		}

		global $wgCaptchaRegexes;
		if ( $newtext !== null && $wgCaptchaRegexes ) {
			if ( !is_array( $wgCaptchaRegexes ) ) {
				throw new UnexpectedValueException(
					'$wgCaptchaRegexes is required to be an array, ' . gettype( $wgCaptchaRegexes ) . ' given.'
				);
			}
			// Custom regex checks. Reuse $oldtext if set above.
			if ( $oldtext === null ) {
				$oldtext = $this->loadText( $title, $section );
			}

			foreach ( $wgCaptchaRegexes as $regex ) {
				$newMatches = [];
				if ( preg_match_all( $regex, $newtext, $newMatches ) ) {
					$oldMatches = [];
					preg_match_all( $regex, $oldtext, $oldMatches );

					$addedMatches = array_diff( $newMatches[0], $oldMatches[0] );

					$numHits = count( $addedMatches );
					if ( $numHits > 0 ) {
						$this->trigger = sprintf( "%dx %s at [[%s]]: %s",
							$numHits,
							$regex,
							$user->getName(),
							$title->getPrefixedText(),
							implode( ", ", $addedMatches ) );
						$this->action = 'edit';
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Filter callback function for URL whitelisting
	 * @param string $url string to check
	 * @return bool true if unknown, false if whitelisted
	 */
	private function filterLink( $url ) {
		global $wgCaptchaWhitelist;
		static $regexes = null;

		if ( $regexes === null ) {
			$source = wfMessage( 'captcha-addurl-whitelist' )->inContentLanguage();

			$regexes = $source->isDisabled()
				? []
				: $this->buildRegexes( explode( "\n", $source->plain() ) );

			if ( $wgCaptchaWhitelist !== false ) {
				array_unshift( $regexes, $wgCaptchaWhitelist );
			}
		}

		foreach ( $regexes as $regex ) {
			if ( preg_match( $regex, $url ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build regex from whitelist
	 * @param string[] $lines string from [[MediaWiki:Captcha-addurl-whitelist]]
	 * @return string[] Regexes
	 * @private
	 */
	private function buildRegexes( $lines ) {
		# Code duplicated from the SpamBlacklist extension (r19197)
		# and later modified.

		# Strip comments and whitespace, then remove blanks
		$lines = array_filter( array_map( 'trim', preg_replace( '/#.*$/', '', $lines ) ) );

		# No lines, don't make a regex which will match everything
		if ( count( $lines ) == 0 ) {
			wfDebug( "No lines\n" );
			return [];
		} else {
			# Make regex
			# It's faster using the S modifier even though it will usually only be run once
			// $regex = 'http://+[a-z0-9_\-.]*(' . implode( '|', $lines ) . ')';
			// return '/' . str_replace( '/', '\/', preg_replace('|\\\*/|', '/', $regex) ) . '/Si';
			$regexes = [];
			$regexStart = [
				'normal' => '/^(?:https?:)?\/\/+[a-z0-9_\-.]*(?:',
				'noprotocol' => '/^(?:',
			];
			$regexEnd = [
				'normal' => ')/Si',
				'noprotocol' => ')/Si',
			];
			$regexMax = 4096;
			$build = [];
			foreach ( $lines as $line ) {
				# Extract flags from the line
				$options = [];
				if ( preg_match( '/^(.*?)\s*<([^<>]*)>$/', $line, $matches ) ) {
					if ( $matches[1] === '' ) {
						wfDebug( "Line with empty regex\n" );
						continue;
					}
					$line = $matches[1];
					$opts = preg_split( '/\s*\|\s*/', trim( $matches[2] ) );
					foreach ( $opts as $opt ) {
						$opt = strtolower( $opt );
						if ( $opt == 'noprotocol' ) {
							$options['noprotocol'] = true;
						}
					}
				}

				$key = isset( $options['noprotocol'] ) ? 'noprotocol' : 'normal';

				// FIXME: not very robust size check, but should work. :)
				if ( !isset( $build[$key] ) ) {
					$build[$key] = $line;
				} elseif ( strlen( $build[$key] ) + strlen( $line ) > $regexMax ) {
					$regexes[] = $regexStart[$key] .
						str_replace( '/', '\/', preg_replace( '|\\\*/|', '/', $build[$key] ) ) .
						$regexEnd[$key];
					$build[$key] = $line;
				} else {
					$build[$key] .= '|' . $line;
				}
			}
			foreach ( $build as $key => $value ) {
				$regexes[] = $regexStart[$key] .
					str_replace( '/', '\/', preg_replace( '|\\\*/|', '/', $value ) ) .
					$regexEnd[$key];
			}
			return $regexes;
		}
	}

	/**
	 * Load external links from the externallinks table
	 * @param Title $title
	 * @return array
	 */
	private function getLinksFromTracker( $title ) {
		$dbr = wfGetDB( DB_REPLICA );
		// should be zero queries
		$id = $title->getArticleID();
		$res = $dbr->select( 'externallinks', [ 'el_to' ],
			[ 'el_from' => $id ], __METHOD__ );
		$links = [];
		foreach ( $res as $row ) {
			$links[] = $row->el_to;
		}
		return $links;
	}

	/**
	 * Backend function for confirmEditMerged()
	 * @param WikiPage $page
	 * @param Content|string $newtext
	 * @param string $section
	 * @param IContextSource $context
	 * @param User $user
	 * @return bool false if the CAPTCHA is rejected, true otherwise
	 */
	private function doConfirmEdit(
		WikiPage $page,
		$newtext,
		$section,
		IContextSource $context,
		User $user
	) {
		global $wgRequest;
		$request = $context->getRequest();

		// FIXME: Stop using wgRequest in other parts of ConfirmEdit so we can
		// stop having to duplicate code for it.
		if ( $request->getVal( 'captchaid' ) ) {
			$request->setVal( 'wpCaptchaId', $request->getVal( 'captchaid' ) );
			$wgRequest->setVal( 'wpCaptchaId', $request->getVal( 'captchaid' ) );
		}
		if ( $request->getVal( 'captchaword' ) ) {
			$request->setVal( 'wpCaptchaWord', $request->getVal( 'captchaword' ) );
			$wgRequest->setVal( 'wpCaptchaWord', $request->getVal( 'captchaword' ) );
		}
		if ( $this->shouldCheck( $page, $newtext, $section, $context ) ) {
			return $this->passCaptchaLimitedFromRequest( $wgRequest, $user );
		} else {
			wfDebug( "ConfirmEdit: no need to show captcha.\n" );
			return true;
		}
	}

	/**
	 * An efficient edit filter callback based on the text after section merging
	 * @param RequestContext $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minorEdit
	 * @return bool
	 */
	public function confirmEditMerged( $context, $content, $status, $summary, $user, $minorEdit ) {
		if ( !$context->canUseWikiPage() ) {
			// we check WikiPage only
			// try to get an appropriate title for this page
			$title = $context->getTitle();
			if ( $title instanceof Title ) {
				$title = $title->getFullText();
			} else {
				// otherwise it's an unknown page where this function is called from
				$title = 'unknown';
			}
			// log this error, it could be a problem in another extension,
			// edits should always have a WikiPage if
			// they go through EditFilterMergedContent.
			wfDebug( __METHOD__ . ': Skipped ConfirmEdit check: No WikiPage for title ' . $title );
			return true;
		}
		$page = $context->getWikiPage();
		if ( !$this->doConfirmEdit( $page, $content, '', $context, $user ) ) {
			$status->value = EditPage::AS_HOOK_ERROR_EXPECTED;
			$status->apiHookResult = [];
			// give an error message for the user to know, what goes wrong here.
			// this can't be done for addurl trigger, because this requires one "free" save
			// for the user, which we don't know, when he did it.
			if ( $this->action === 'edit' ) {
				$status->fatal(
					new RawMessage(
						Html::element(
							'div',
							[ 'class' => 'errorbox' ],
							$context->msg( 'captcha-edit-fail' )->text()
						)
					)
				);
			}
			$this->addCaptchaAPI( $status->apiHookResult );
			$page->ConfirmEdit_ActivateCaptcha = true;
			return false;
		}
		return true;
	}

	/**
	 * Logic to check if we need to pass a captcha for the current user
	 * to create a new account, or not
	 *
	 * @param User $creatingUser
	 * @return bool true to show captcha, false to skip captcha
	 */
	public function needCreateAccountCaptcha( User $creatingUser ) {
		if ( $this->triggersCaptcha( CaptchaTriggers::CREATE_ACCOUNT ) ) {
			if ( $this->canSkipCaptcha( $creatingUser,
				\MediaWiki\MediaWikiServices::getInstance()->getMainConfig() ) ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Check the captcha on Special:EmailUser
	 * @param MailAddress $from
	 * @param MailAddress $to
	 * @param string $subject
	 * @param string $text
	 * @param string &$error
	 * @return bool true to continue saving, false to abort and show a captcha form
	 */
	public function confirmEmailUser( $from, $to, $subject, $text, &$error ) {
		global $wgRequest;

		$user = RequestContext::getMain()->getUser();
		if ( $this->triggersCaptcha( CaptchaTriggers::SENDEMAIL ) ) {
			if ( $this->canSkipCaptcha( $user,
				\MediaWiki\MediaWikiServices::getInstance()->getMainConfig() ) ) {
				return true;
			}

			if ( defined( 'MW_API' ) ) {
				# API mode
				# Asking for captchas in the API is really silly
				$error = Status::newFatal( 'captcha-disabledinapi' );
				return false;
			}
			$this->trigger = "{$user->getName()} sending email";
			if ( !$this->passCaptchaLimitedFromRequest( $wgRequest, $user ) ) {
				$error = Status::newFatal( 'captcha-sendemail-fail' );
				return false;
			}
		}
		return true;
	}

	/**
	 * @param ApiBase $module
	 * @return bool
	 */
	protected function isAPICaptchaModule( $module ) {
		return $module instanceof ApiEditPage;
	}

	/**
	 * @param ApiBase $module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
		if ( $this->isAPICaptchaModule( $module ) ) {
			$params['captchaword'] = [
				ApiBase::PARAM_HELP_MSG => 'captcha-apihelp-param-captchaword',
			];
			$params['captchaid'] = [
				ApiBase::PARAM_HELP_MSG => 'captcha-apihelp-param-captchaid',
			];
		}

		return true;
	}

	/**
	 * Checks, if the user reached the amount of false CAPTCHAs and give him some vacation
	 * or run self::passCaptcha() and clear counter if correct.
	 *
	 * @param WebRequest $request
	 * @param User $user
	 * @return bool
	 */
	public function passCaptchaLimitedFromRequest( WebRequest $request, User $user ) {
		list( $index, $word ) = $this->getCaptchaParamsFromRequest( $request );
		return $this->passCaptchaLimited( $index, $word, $user );
	}

	/**
	 * @param WebRequest $request
	 * @return array [ captcha ID, captcha solution ]
	 */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		$index = $request->getVal( 'wpCaptchaId' );
		$word = $request->getVal( 'wpCaptchaWord' );
		return [ $index, $word ];
	}

	/**
	 * Checks, if the user reached the amount of false CAPTCHAs and give him some vacation
	 * or run self::passCaptcha() and clear counter if correct.
	 *
	 * @param string $index Captcha idenitifier
	 * @param string $word Captcha solution
	 * @param User $user User for throttling captcha solving attempts
	 * @return bool
	 * @see self::passCaptcha()
	 */
	public function passCaptchaLimited( $index, $word, User $user ) {
		// don't increase pingLimiter here, just check, if CAPTCHA limit exceeded
		if ( $user->pingLimiter( 'badcaptcha', 0 ) ) {
			// for debugging add an proper error message, the user just see an false captcha error message
			$this->log( 'User reached RateLimit, preventing action' );
			return false;
		}

		if ( $this->passCaptcha( $index, $word ) ) {
			return true;
		}

		// captcha was not solved: increase limit and return false
		$user->pingLimiter( 'badcaptcha' );
		return false;
	}

	/**
	 * Given a required captcha run, test form input for correct
	 * input on the open session.
	 * @param WebRequest $request
	 * @param User $user
	 * @return bool if passed, false if failed or new session
	 */
	public function passCaptchaFromRequest( WebRequest $request, User $user ) {
		list( $index, $word ) = $this->getCaptchaParamsFromRequest( $request );
		return $this->passCaptcha( $index, $word );
	}

	/**
	 * Given a required captcha run, test form input for correct
	 * input on the open session.
	 * @param string $index Captcha idenitifier
	 * @param string $word Captcha solution
	 * @return bool if passed, false if failed or new session
	 */
	protected function passCaptcha( $index, $word ) {
		// Don't check the same CAPTCHA twice in one session,
		// if the CAPTCHA was already checked - Bug T94276
		if ( isset( $this->captchaSolved ) ) {
			return $this->captchaSolved;
		}

		$info = $this->retrieveCaptcha( $index );
		if ( $info ) {
			if ( $this->keyMatch( $word, $info ) ) {
				$this->log( "passed" );
				$this->clearCaptcha( $index );
				$this->captchaSolved = true;
				return true;
			} else {
				$this->clearCaptcha( $index );
				$this->log( "bad form input" );
				$this->captchaSolved = false;
				return false;
			}
		} else {
			$this->log( "new captcha session" );
			return false;
		}
	}

	/**
	 * Log the status and any triggering info for debugging or statistics
	 * @param string $message
	 */
	protected function log( $message ) {
		wfDebugLog( 'captcha', 'ConfirmEdit: ' . $message . '; ' . $this->trigger );
	}

	/**
	 * Generate a captcha session ID and save the info in PHP's session storage.
	 * (Requires the user to have cookies enabled to get through the captcha.)
	 *
	 * A random ID is used so legit users can make edits in multiple tabs or
	 * windows without being unnecessarily hobbled by a serial order requirement.
	 * Pass the returned id value into the edit form as wpCaptchaId.
	 *
	 * @param array $info data to store
	 * @return string captcha ID key
	 */
	public function storeCaptcha( $info ) {
		if ( !isset( $info['index'] ) ) {
			// Assign random index if we're not udpating
			$info['index'] = strval( mt_rand() );
		}
		CaptchaStore::get()->store( $info['index'], $info );
		return $info['index'];
	}

	/**
	 * Fetch this session's captcha info.
	 * @param string $index
	 * @return array|false array of info, or false if missing
	 */
	public function retrieveCaptcha( $index ) {
		return CaptchaStore::get()->retrieve( $index );
	}

	/**
	 * Clear out existing captcha info from the session, to ensure
	 * it can't be reused.
	 * @param string $index
	 */
	public function clearCaptcha( $index ) {
		CaptchaStore::get()->clear( $index );
	}

	/**
	 * Retrieve the current version of the page or section being edited...
	 * @param Title $title
	 * @param string $section
	 * @param int $flags Flags for Revision loading methods
	 * @return string
	 * @private
	 */
	private function loadText( $title, $section, $flags = RevisionLookup::READ_LATEST ) {
		$revRecord = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $title, 0, $flags );

		if ( $revRecord === null ) {
			return "";
		}

		try {
			$content = $revRecord->getContent( SlotRecord::MAIN );
		} catch ( RevisionAccessException $e ) {
			return '';
		}

		$text = ContentHandler::getContentText( $content );
		if ( $section !== '' ) {
			return MediaWikiServices::getInstance()->getParser()
				->getSection( $text, $section );
		}

		return $text;
	}

	/**
	 * Extract a list of all recognized HTTP links in the text.
	 * @param Title $title
	 * @param string $text
	 * @return string[]
	 */
	private function findLinks( $title, $text ) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$user = $parser->getUser();
		$options = new ParserOptions( $user );
		$text = $parser->preSaveTransform( $text, $title, $user, $options );
		$out = $parser->parse( $text, $title, $options );

		return array_keys( $out->getExternalLinks() );
	}

	/**
	 * Show a page explaining what this wacky thing is.
	 */
	public function showHelp() {
		global $wgOut;
		$wgOut->setPageTitle( wfMessage( 'captchahelp-title' )->text() );
		$wgOut->addWikiMsg( 'captchahelp-text' );
		if ( CaptchaStore::get()->cookiesNeeded() ) {
			$wgOut->addWikiMsg( 'captchahelp-cookies-needed' );
		}
	}

	/**
	 * @return CaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		$captchaData = $this->getCaptcha();
		$id = $this->storeCaptcha( $captchaData );
		return new CaptchaAuthenticationRequest( $id, $captchaData );
	}

	/**
	 * Modify the appearance of the captcha field
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo Field description as given by AuthenticationRequest::mergeFieldInfo
	 * @param array &$formDescriptor A form descriptor suitable for the HTMLForm constructor
	 * @param string $action One of the AuthManager::ACTION_* constants
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		$req = AuthenticationRequest::getRequestByClass( $requests,
			CaptchaAuthenticationRequest::class );
		if ( !$req ) {
			return;
		}

		$formDescriptor['captchaWord'] = [
			'label-message' => null,
			'autocomplete' => false,
			'persistent' => false,
			'required' => true,
		] + $formDescriptor['captchaWord'];
	}

	/**
	 * Check whether the user provided / IP making the request is allowed to skip captchas
	 * @param User $user
	 * @param Config $config
	 * @return bool
	 * @throws ConfigException
	 */
	public function canSkipCaptcha( $user, Config $config ) {
		$allowConfirmEmail = $config->get( 'AllowConfirmedEmail' );

		if ( $user->isAllowed( 'skipcaptcha' ) ) {
			wfDebug( "ConfirmEdit: user group allows skipping captcha\n" );
			return true;
		}

		if ( $this->isIPWhitelisted() ) {
			wfDebug( "ConfirmEdit: user IP is whitelisted" );
			return true;
		}

		if ( $allowConfirmEmail && $user->isEmailConfirmed() ) {
			wfDebug( "ConfirmEdit: user has confirmed mail, skipping captcha\n" );
			return true;
		}

		return false;
	}
}
