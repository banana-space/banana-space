<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

class ConfirmEditHooks {
	protected static $instanceCreated = false;

	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	public static function getInstance() {
		global $wgCaptcha, $wgCaptchaClass;

		if ( !static::$instanceCreated ) {
			static::$instanceCreated = true;
			$class = $wgCaptchaClass ?: SimpleCaptcha::class;
			$wgCaptcha = new $class;
		}

		return $wgCaptcha;
	}

	/**
	 * @param RequestContext $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minorEdit
	 * @return bool
	 */
	public static function confirmEditMerged( $context, $content, $status, $summary, $user,
		$minorEdit
	) {
		return self::getInstance()->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
	}

	/**
	 * PageSaveComplete hook handler.
	 * Clear IP whitelist cache on page saves for [[MediaWiki:Captcha-ip-whitelist]].
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $userIdentity
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 *
	 * @return bool true
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $userIdentity,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getText() === 'Captcha-ip-whitelist' && $title->getNamespace() === NS_MEDIAWIKI ) {
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cache->delete( $cache->makeKey( 'confirmedit', 'ipwhitelist' ) );
		}

		return true;
	}

	/**
	 * @param EditPage $editpage
	 */
	public static function confirmEditPage( EditPage $editpage ) {
		self::getInstance()->editShowCaptcha( $editpage );
	}

	/**
	 * @param EditPage $editPage
	 * @param OutputPage $out
	 */
	public static function showEditFormFields( EditPage $editPage, OutputPage $out ) {
		self::getInstance()->showEditFormFields( $editPage, $out );
	}

	/**
	 * @param HTMLForm $form
	 * @return bool
	 */
	public static function injectEmailUser( $form ) {
		return self::getInstance()->injectEmailUser( $form );
	}

	/**
	 * @param MailAddress $from
	 * @param MailAddress $to
	 * @param string $subject
	 * @param string $text
	 * @param string &$error
	 * @return bool
	 */
	public static function confirmEmailUser( $from, $to, $subject, $text, &$error ) {
		return self::getInstance()->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	/**
	 * APIGetAllowedParams hook handler
	 * Default $flags to 1 for backwards-compatible behavior
	 * @param ApiBase $module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public static function onAPIGetAllowedParams( ApiBase $module, &$params, $flags = 1 ) {
		return self::getInstance()->apiGetAllowedParams( $module, $params, $flags );
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		self::getInstance()->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
	}

	public static function confirmEditSetup() {
		global $wgCaptchaTriggers, $wgWikimediaJenkinsCI;

		// There is no need to run (core) tests with enabled ConfirmEdit - bug T44145
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI === true ) {
			$wgCaptchaTriggers = array_fill_keys( array_keys( $wgCaptchaTriggers ), false );
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
	 * AlternateEditPreview hook handler.
	 *
	 * Replaces the preview with a check of all lines for the [[MediaWiki:Captcha-ip-whitelist]]
	 * interface message, if it validates as an IP address.
	 *
	 * @param EditPage $editor
	 * @param Content $content
	 * @param string &$html
	 * @param ?ParserOutput $parserOutput
	 * @return bool
	 */
	public static function onAlternateEditPreview(
		EditPage $editor,
		Content $content,
		&$html,
		$parserOutput
	) {
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
			if ( IPUtils::isIPAddress( $ip ) ) {
				$validity = $ctx->msg( 'confirmedit-preview-valid' )->escaped();
				$css = 'valid';
			} else {
				$validity = $ctx->msg( 'confirmedit-preview-invalid' )->escaped();
				$css = 'notvalid';
			}
			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
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
					$lang->truncateForVisual( $ip, 50 )
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

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$messages = [];

		$messages[] = 'colon-separator';
		$messages[] = 'captcha-edit';
		$messages[] = 'captcha-label';

		if ( $extensionRegistry->isLoaded( 'QuestyCaptcha' ) ) {
			$messages[] = 'questycaptcha-edit';
		}

		if ( $extensionRegistry->isLoaded( 'FancyCaptcha' ) ) {
			$messages[] = 'fancycaptcha-edit';
			$messages[] = 'fancycaptcha-reload-text';
			$messages[] = 'fancycaptcha-imgcaptcha-ph';
		}

		$resourceLoader->register( [
			'ext.confirmEdit.CaptchaInputWidget' => [
				'localBasePath' => dirname( __DIR__ ),
				'remoteExtPath' => 'ConfirmEdit',
				'scripts' => 'resources/libs/ext.confirmEdit.CaptchaInputWidget.js',
				'styles' => 'resources/libs/ext.confirmEdit.CaptchaInputWidget.less',
				'messages' => $messages,
				'dependencies' => 'oojs-ui-core',
				'targets' => [ 'desktop', 'mobile' ],
			]
		] );
	}

}
