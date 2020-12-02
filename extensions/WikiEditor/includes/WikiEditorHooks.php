<?php
/**
 * Hooks for WikiEditor extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;
use WikimediaEvents\WikimediaEventsHooks;

class WikiEditorHooks {
	// ID used for grouping entries all of a session's entries together in
	// EventLogging.
	private static $statsId = false;

	/* Static Methods */

	/**
	 * Log stuff to EventLogging's Schema:EditAttemptStep -
	 * see https://meta.wikimedia.org/wiki/Schema:EditAttemptStep
	 * If you don't have EventLogging installed, does nothing.
	 *
	 * @param string $action
	 * @param Article $article Which article (with full context, page, title, etc.)
	 * @param array $data Data to log for this action
	 * @return bool Whether the event was logged or not.
	 */
	public static function doEventLogging( $action, $article, $data = [] ) {
		global $wgWMESchemaEditAttemptStepSamplingRate;
		$extensionRegistry = ExtensionRegistry::getInstance();
		if ( !$extensionRegistry->isLoaded( 'EventLogging' ) ) {
			return false;
		}
		// Sample 6.25%
		$samplingRate = $wgWMESchemaEditAttemptStepSamplingRate ?? 0.0625;
		$inSample = EventLogging::sessionInSample(
			(int)( 1 / $samplingRate ), $data['editing_session_id']
		);
		$shouldOversample = $extensionRegistry->isLoaded( 'WikimediaEvents' ) &&
			WikimediaEventsHooks::shouldSchemaEditAttemptStepOversample( $article->getContext() );
		if ( !$inSample && !$shouldOversample ) {
			return false;
		}

		$user = $article->getContext()->getUser();
		$page = $article->getPage();
		$title = $article->getTitle();
		$revisionRecord = $page->getRevisionRecord();

		$data = [
			'action' => $action,
			'version' => 1,
			'is_oversample' => !$inSample,
			'editor_interface' => 'wikitext',
			'platform' => 'desktop', // FIXME
			'integration' => 'page',
			'page_id' => $page->getId(),
			'page_title' => $title->getPrefixedText(),
			'page_ns' => $title->getNamespace(),
			'revision_id' => $revisionRecord ? $revisionRecord->getId() : 0,
			'user_id' => $user->getId(),
			'user_editcount' => $user->getEditCount() ?: 0,
			'mw_version' => MW_VERSION,
		] + $data;

		if ( $user->isAnon() ) {
			$data['user_class'] = 'IP';
		}

		return EventLogging::logEvent( 'EditAttemptStep', 18530416, $data );
	}

	/**
	 * EditPage::showEditForm:initial hook
	 *
	 * Adds the modules to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 */
	public static function editPageShowEditFormInitial( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();

		// Add modules if enabled
		$user = $article->getContext()->getUser();
		if ( $user->getOption( 'usebetatoolbar' ) ) {
			$outputPage->addModuleStyles( 'ext.wikiEditor.styles' );
			$outputPage->addModules( 'ext.wikiEditor' );
		}

		// Don't run this if the request was posted - we don't want to log 'init' when the
		// user just pressed 'Show preview' or 'Show changes', or switched from VE keeping
		// changes.
		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) && !$request->wasPosted() ) {
			$data = [];
			$data['editing_session_id'] = self::getEditingStatsId( $request );
			if ( $request->getVal( 'section' ) ) {
				$data['init_type'] = 'section';
			} else {
				$data['init_type'] = 'page';
			}
			if ( $request->getHeader( 'Referer' ) ) {
				if (
					$request->getVal( 'section' ) === 'new'
					|| !$article->getPage()->exists()
				) {
					$data['init_mechanism'] = 'new';
				} else {
					$data['init_mechanism'] = 'click';
				}
			} else {
				$data['init_mechanism'] = 'url';
			}

			self::doEventLogging( 'init', $article, $data );
		}
	}

	/**
	 * EditPage::showEditForm:fields hook
	 *
	 * Adds the event fields to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 */
	public static function editPageShowEditFormFields( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$req = $outputPage->getRequest();
		$editingStatsId = self::getEditingStatsId( $req );

		$shouldOversample = ExtensionRegistry::getInstance()->isLoaded( 'WikimediaEvents' ) &&
			WikimediaEventsHooks::shouldSchemaEditAttemptStepOversample( $outputPage->getContext() );

		$outputPage->addHTML(
			Xml::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'editingStatsId',
					'id' => 'editingStatsId',
					'value' => $editingStatsId
				]
			)
		);

		if ( $shouldOversample ) {
			$outputPage->addHTML(
				Xml::element(
					'input',
					[
						'type' => 'hidden',
						'name' => 'editingStatsOversample',
						'id' => 'editingStatsOversample',
						'value' => 1
					]
				)
			);
		}
	}

	/**
	 * GetPreferences hook
	 *
	 * Adds WikiEditor-related items to the preferences
	 *
	 * @param User $user current user
	 * @param array &$defaultPreferences list of default user preference controls
	 */
	public static function getPreferences( $user, &$defaultPreferences ) {
		// Ideally this key would be 'wikieditor-toolbar'
		$defaultPreferences['usebetatoolbar'] = [
			'type' => 'toggle',
			'label-message' => 'wikieditor-toolbar-preference',
			'help-message' => 'wikieditor-toolbar-preference-help',
			'section' => 'editing/editor',
		];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleData( ResourceLoaderContext $context, Config $config ) {
		return [
			// expose magic words for use by the wikieditor toolbar
			'magicWords' => self::getMagicWords(),
			'signature' => self::getSignatureMessage( $context )
		];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleDataSummary( ResourceLoaderContext $context, Config $config ) {
		return [
			'magicWords' => self::getMagicWords(),
			'signature' => self::getSignatureMessage( $context, true )
		];
	}

	private static function getSignatureMessage( MessageLocalizer $ml, $raw = false ) {
		$msg = $ml->msg( 'sig-text' )->params( '~~~~' )->inContentLanguage();
		return $raw ? $msg->plain() : $msg->text();
	}

	/**
	 * Expose useful magic words which are used by the wikieditor toolbar
	 * @return string[]
	 */
	private static function getMagicWords() {
		$requiredMagicWords = [
			'redirect',
			'img_right',
			'img_left',
			'img_none',
			'img_center',
			'img_thumbnail',
			'img_framed',
			'img_frameless',
		];
		$magicWords = [];
		$factory = MediaWikiServices::getInstance()->getMagicWordFactory();
		foreach ( $requiredMagicWords as $name ) {
			$magicWords[$name] = $factory->get( $name )->getSynonym( 0 );
		}
		return $magicWords;
	}

	/**
	 * Gets a 32 character alphanumeric random string to be used for stats.
	 * @param WebRequest $request
	 * @return string
	 */
	private static function getEditingStatsId( WebRequest $request ) {
		$fromRequest = $request->getVal( 'editingStatsId' );
		if ( $fromRequest ) {
			return $fromRequest;
		}
		if ( !self::$statsId ) {
			self::$statsId = MWCryptRand::generateHex( 32 );
		}
		return self::$statsId;
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave' hook.
	 *
	 * @param EditPage $editPage
	 */
	public static function editPageAttemptSave( EditPage $editPage ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			self::doEventLogging(
				'saveAttempt',
				$article,
				[ 'editing_session_id' => $request->getVal( 'editingStatsId' ) ]
			);
		}
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave:after' hook.
	 *
	 * @param EditPage $editPage
	 * @param Status $status
	 */
	public static function editPageAttemptSaveAfter( EditPage $editPage, Status $status ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			$data = [];
			$data['editing_session_id'] = $request->getVal( 'editingStatsId' );

			if ( $status->isOK() ) {
				$action = 'saveSuccess';
			} else {
				$action = 'saveFailure';
				$errors = $status->getErrorsArray();

				if ( isset( $errors[0][0] ) ) {
					$data['save_failure_message'] = $errors[0][0];
				}

				$wikiPage = $editPage->getArticle()->getPage();
				if ( $status->value === EditPage::AS_CONFLICT_DETECTED ) {
					$data['save_failure_type'] = 'editConflict';
				} elseif ( $status->value === EditPage::AS_ARTICLE_WAS_DELETED ) {
					$data['save_failure_type'] = 'editPageDeleted';
				} elseif ( isset( $errors[0][0] ) && $errors[0][0] === 'abusefilter-disallowed' ) {
					$data['save_failure_type'] = 'extensionAbuseFilter';
				} elseif ( isset( $wikiPage->ConfirmEdit_ActivateCaptcha ) ) {
					// TODO: :(
					$data['save_failure_type'] = 'extensionCaptcha';
				} elseif ( isset( $errors[0][0] ) && $errors[0][0] === 'spam-blacklisted-link' ) {
					$data['save_failure_type'] = 'extensionSpamBlacklist';
				} else {
					// Catch everything else... We don't seem to get userBadToken or
					// userNewUser through this hook.
					$data['save_failure_type'] = 'responseUnknown';
				}
			}
			self::doEventLogging( $action, $article, $data );
		}
	}
}
