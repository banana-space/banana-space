<?php

class ConfirmEditHooks {
	protected static $instanceCreated = false;

	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	public static function getInstance() {
		global $wgCaptcha, $wgCaptchaClass;

		$class = $wgCaptchaClass;
		if ( $class == null ) {
			$class = 'SimpleCaptcha';
		}
		if ( !static::$instanceCreated ) {
			static::$instanceCreated = true;
			$wgCaptcha = new $class;
		}

		return $wgCaptcha;
	}

	static function confirmEditMerged( $context, $content, $status, $summary, $user, $minorEdit ) {
		return self::getInstance()->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
	}

	/**
	 * PageContentSaveComplete hook handler.
	 * Clear IP whitelist cache on page saves for [[MediaWiki:Captcha-ip-whitelist]].
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $isWatch
	 * @param string $section
	 * @param int $flags
	 * @param int $revision
	 * @param Status $status
	 * @param int $baseRevId
	 *
	 * @return bool true
	 */
	static function onPageContentSaveComplete( WikiPage $wikiPage, User $user, Content $content,
		$summary, $isMinor, $isWatch, $section, $flags, $revision, Status $status, $baseRevId
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getText() === 'Captcha-ip-whitelist' && $title->getNamespace() === NS_MEDIAWIKI ) {
			$cache = ObjectCache::getMainWANInstance();
			$cache->delete( $cache->makeKey( 'confirmedit', 'ipwhitelist' ) );
		}

		return true;
	}

	static function confirmEditPage( $editpage, $buttons, $tabindex ) {
		self::getInstance()->editShowCaptcha( $editpage );
	}

	static function showEditFormFields( &$editPage, &$out ) {
		self::getInstance()->showEditFormFields( $editPage, $out );
	}

	static function injectEmailUser( &$form ) {
		return self::getInstance()->injectEmailUser( $form );
	}

	static function confirmEmailUser( $from, $to, $subject, $text, &$error ) {
		return self::getInstance()->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	// Default $flags to 1 for backwards-compatible behavior
	public static function APIGetAllowedParams( &$module, &$params, $flags = 1 ) {
		return self::getInstance()->APIGetAllowedParams( $module, $params, $flags );
	}

	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		self::getInstance()->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
	}

	public static function confirmEditSetup() {
		// @codingStandardsIgnoreStart MediaWiki.NamingConventions.ValidGlobalName.wgPrefix
		global $wgCaptchaTriggers, $wgAllowConfirmedEmail,
			$wgWikimediaJenkinsCI, $ceAllowConfirmedEmail;
		// @codingStandardsIgnoreEnd

		// There is no need to run (core) tests with enabled ConfirmEdit - bug T44145
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI === true ) {
			$wgCaptchaTriggers = array_fill_keys( array_keys( $wgCaptchaTriggers ), false );
		}

		// $ceAllowConfirmedEmail is deprecated and should be replaced by $wgAllowConfirmedEmail.
		// For backward-compatibility, keep the value for some time. T162641
		if ( isset( $ceAllowConfirmedEmail ) ) {
			wfDeprecated(
				'Using $ceAllowConfirmedEmail is deprecated, ' .
				'please migrate to $wgAllowConfirmedEmail as a replacement.' );
			$wgAllowConfirmedEmail = $ceAllowConfirmedEmail;
		}
	}

	/**
	 * TitleReadWhitelist hook handler.
	 *
	 * @param Title $title
	 * @param User $user
	 * @param bool &$whitelisted
	 */
	public static function onTitleReadWhitelist( Title $title, User $user, &$whitelisted ) {
		$image = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$help = SpecialPage::getTitleFor( 'Captcha', 'help' );
		if ( $title->equals( $image ) || $title->equals( $help ) ) {
			$whitelisted = true;
		}
	}

	/**
	 *
	 * Callback for extension.json of FancyCaptcha to set a default captcha directory,
	 * which depends on wgUploadDirectory
	 */
	public static function onFancyCaptchaSetup() {
		global $wgCaptchaDirectory, $wgUploadDirectory;
		if ( !$wgCaptchaDirectory ) {
			$wgCaptchaDirectory = "$wgUploadDirectory/captcha";
		}
	}

	/**
	 * Callback for extension.json of ReCaptcha to require the recaptcha library php file.
	 * FIXME: This should be done in a better way, e.g. only load the libraray, if really needed.
	 */
	public static function onReCaptchaSetup() {
		require_once __DIR__ . '/../ReCaptcha/recaptchalib.php';
	}

	/**
	 * Extension function, moved from ReCaptcha.php when that was decimated.
	 * Make sure the keys are defined.
	 */
	public static function efReCaptcha() {
		global $wgReCaptchaPublicKey, $wgReCaptchaPrivateKey;
		// @codingStandardsIgnoreStart
		global $recaptcha_public_key, $recaptcha_private_key;
		// @codingStandardsIgnoreEnd
		global $wgServerName;

		// Backwards compatibility
		if ( $wgReCaptchaPublicKey == '' ) {
			$wgReCaptchaPublicKey = $recaptcha_public_key;
		}
		if ( $wgReCaptchaPrivateKey == '' ) {
			$wgReCaptchaPrivateKey = $recaptcha_private_key;
		}

		if ( $wgReCaptchaPublicKey == '' || $wgReCaptchaPrivateKey == '' ) {
			die(
				'You need to set $wgReCaptchaPrivateKey and $wgReCaptchaPublicKey in LocalSettings.php to ' .
				"use the reCAPTCHA plugin. You can sign up for a key <a href='" .
				htmlentities( recaptcha_get_signup_url( $wgServerName, "mediawiki" ) ) .
				"'>here</a>."
			);
		}
	}

	/**
	 * AlternateEditPreview hook handler.
	 *
	 * Replaces the preview with a check of all lines for the [[MediaWiki:Captcha-ip-whitelist]]
	 * interface message, if it validates as an IP address.
	 *
	 * @param EditPage $editor
	 * @param Content &$content
	 * @param string &$html
	 * @param ParserOutput &$po
	 * @return bool
	 */
	public static function onAlternateEditPreview( EditPage $editor, &$content, &$html, &$po ) {
		$title = $editor->getTitle();
		$exceptionTitle = Title::makeTitle( NS_MEDIAWIKI, 'Captcha-ip-whitelist' );

		if ( !$title->equals( $exceptionTitle ) ) {
			return true;
		}

		$ctx = $editor->getArticle()->getContext();
		$out = $ctx->getOutput();
		$lang = $ctx->getLanguage();

		$lines = explode( "\n", $content->getNativeData() );
		$html .= Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
				$ctx->msg( 'confirmedit-preview-description' )->parse()
			) .
			Html::openElement(
				'table',
				[ 'class' => 'wikitable sortable' ]
			) .
			Html::openElement( 'thead' ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-line' )->text() ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-content' )->text() ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-validity' )->text() ) .
			Html::closeElement( 'thead' );

		foreach ( $lines as $count => $line ) {
			$ip = trim( $line );
			if ( $ip === '' || strpos( $ip, '#' ) !== false ) {
				continue;
			}
			if ( IP::isIPAddress( $ip ) ) {
				$validity = $ctx->msg( 'confirmedit-preview-valid' )->escaped();
				$css = 'valid';
			} else {
				$validity = $ctx->msg( 'confirmedit-preview-invalid' )->escaped();
				$css = 'notvalid';
			}
			$html .= Html::openElement( 'tr' ) .
				Html::element(
					'td',
					[],
					$lang->formatNum( $count + 1 )
				) .
				Html::element(
					'td',
					[],
					// IPv6 max length: 8 groups * 4 digits + 7 delimiter = 39
					// + 11 chars for safety
					$lang->truncate( $ip, 50 )
				) .
				Html::rawElement(
					'td',
					// possible values:
					// mw-confirmedit-ip-valid
					// mw-confirmedit-ip-notvalid
					[ 'class' => 'mw-confirmedit-ip-' . $css ],
					$validity
				) .
				Html::closeElement( 'tr' );
		}
		$html .= Html::closeElement( 'table' );
		$out->addModuleStyles( 'ext.confirmEdit.editPreview.ipwhitelist.styles' );

		return false;
	}
}
