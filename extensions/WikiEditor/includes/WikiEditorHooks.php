<?php
/**
 * Hooks for WikiEditor extension
 *
 * @file
 * @ingroup Extensions
 */

class WikiEditorHooks {
	// ID used for grouping entries all of a session's entries together in
	// EventLogging.
	private static $statsId = false;

	/* Static Methods */

	/**
	 * Log stuff to EventLogging's Schema:Edit - see https://meta.wikimedia.org/wiki/Schema:Edit
	 * If you don't have EventLogging installed, does nothing.
	 *
	 * @param string $action
	 * @param Article $article Which article (with full context, page, title, etc.)
	 * @param array $data Data to log for this action
	 * @return bool Whether the event was logged or not.
	 */
	public static function doEventLogging( $action, $article, $data = [] ) {
		global $wgVersion;
		if ( !class_exists( 'EventLogging' ) ) {
			return false;
		}
		// Sample 6.25% (via hex digit)
		if ( $data['editingSessionId'][0] > '0' ) {
			return false;
		}

		$user = $article->getContext()->getUser();
		$page = $article->getPage();
		$title = $article->getTitle();

		$data = [
			'action' => $action,
			'version' => 1,
			'editor' => 'wikitext',
			'platform' => 'desktop', // FIXME
			'integration' => 'page',
			'page.id' => $page->getId(),
			'page.title' => $title->getPrefixedText(),
			'page.ns' => $title->getNamespace(),
			'page.revid' => $page->getRevision() ? $page->getRevision()->getId() : 0,
			'user.id' => $user->getId(),
			'user.editCount' => $user->getEditCount() ?: 0,
			'mediawiki.version' => $wgVersion
		] + $data;

		if ( $user->isAnon() ) {
			$data['user.class'] = 'IP';
		}

		return EventLogging::logEvent( 'Edit', 13457736, $data );
	}

	/**
	 * EditPage::showEditForm:initial hook
	 *
	 * Adds the modules to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 * @return bool
	 */
	public static function editPageShowEditFormInitial( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return true;
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
		if ( class_exists( 'EventLogging' ) && !$request->wasPosted() ) {
			$data = [];
			$data['editingSessionId'] = self::getEditingStatsId();
			if ( $request->getVal( 'section' ) ) {
				$data['action.init.type'] = 'section';
			} else {
				$data['action.init.type'] = 'page';
			}
			if ( $request->getHeader( 'Referer' ) ) {
				if ( $request->getVal( 'section' ) === 'new' || !$article->exists() ) {
					$data['action.init.mechanism'] = 'new';
				} else {
					$data['action.init.mechanism'] = 'click';
				}
			} else {
				$data['action.init.mechanism'] = 'url';
			}

			self::doEventLogging( 'init', $article, $data );
		}

		return true;
	}

	/**
	 * EditPage::showEditForm:fields hook
	 *
	 * Adds the event fields to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 * @return bool
	 */
	public static function editPageShowEditFormFields( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return true;
		}

		$req = $outputPage->getRequest();
		$editingStatsId = $req->getVal( 'editingStatsId' );
		if ( !$editingStatsId || !$req->wasPosted() ) {
			$editingStatsId = self::getEditingStatsId();
		}

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
		return true;
	}

	/**
	 * EditPageBeforeEditToolbar hook
	 *
	 * Disable the old toolbar if the new one is enabled
	 *
	 * @param string &$toolbar
	 * @return bool
	 */
	public static function EditPageBeforeEditToolbar( &$toolbar ) {
		global $wgUser;
		if ( $wgUser->getOption( 'usebetatoolbar' ) ) {
			$toolbar = '';
			// Return false to signify that the toolbar has been over-written, so
			// the old toolbar code shouldn't be added to the page.
			return false;
		}
		return true;
	}

	/**
	 * GetPreferences hook
	 *
	 * Adds WikiEditor-related items to the preferences
	 *
	 * @param User $user current user
	 * @param array &$defaultPreferences list of default user preference controls
	 * @return bool
	 */
	public static function getPreferences( $user, &$defaultPreferences ) {
		// Ideally this key would be 'wikieditor-toolbar'
		$defaultPreferences['usebetatoolbar'] = [
			'type' => 'toggle',
			'label-message' => 'wikieditor-toolbar-preference',
			'section' => 'editing/editor',
		];

		return true;
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function resourceLoaderGetConfigVars( &$vars ) {
		// expose magic words for use by the wikieditor toolbar
		self::getMagicWords( $vars );

		$vars['mw.msg.wikieditor'] = wfMessage( 'sig-text', '~~~~' )->inContentLanguage()->text();

		return true;
	}

	/**
	 * ResourceLoaderTestModules hook
	 *
	 * Registers JavaScript test modules
	 *
	 * @param array &$testModules array of javascript testing modules. 'qunit' is fed using
	 * tests/qunit/QUnitTestResources.php.
	 * @param ResourceLoader &$resourceLoader
	 * @return bool
	 */
	public static function resourceLoaderTestModules( &$testModules, &$resourceLoader ) {
		$testModules['qunit']['ext.wikiEditor.toolbar.test'] = [
			'scripts' => [ 'tests/qunit/ext.wikiEditor.toolbar.test.js' ],
			'dependencies' => [ 'ext.wikiEditor' ],
			'localBasePath' => __DIR__ . '/..',
			'remoteExtPath' => 'WikiEditor',
		];
		return true;
	}

	/**
	 * MakeGlobalVariablesScript hook
	 *
	 * Adds enabled/disabled switches for WikiEditor modules
	 * @param array &$vars
	 * @return bool
	 */
	public static function makeGlobalVariablesScript( &$vars ) {
		// Build and export old-style wgWikiEditorEnabledModules object for back compat
		$vars['wgWikiEditorEnabledModules'] = [];
		return true;
	}

	/**
	 * Expose useful magic words which are used by the wikieditor toolbar
	 * @param array &$vars
	 * @return bool
	 */
	private static function getMagicWords( &$vars ) {
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
		foreach ( $requiredMagicWords as $name ) {
			$magicWords[$name] = MagicWord::get( $name )->getSynonym( 0 );
		}
		$vars['wgWikiEditorMagicWords'] = $magicWords;
		return true;
	}

	/**
	 * Gets a 32 character alphanumeric random string to be used for stats.
	 * @return string
	 */
	private static function getEditingStatsId() {
		if ( !self::$statsId ) {
			self::$statsId = MWCryptRand::generateHex( 32 );
		}
		return self::$statsId;
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave' hook.
	 *
	 * @param EditPage $editPage
	 * @return bool
	 */
	public static function editPageAttemptSave( EditPage $editPage ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			self::doEventLogging(
				'saveAttempt',
				$article,
				[ 'editingSessionId' => $request->getVal( 'editingStatsId' ) ]
			);
		}

		return true;
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave:after' hook.
	 *
	 * @param EditPage $editPage
	 * @param Status $status
	 * @return bool
	 */
	public static function editPageAttemptSaveAfter( EditPage $editPage, Status $status ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			$data = [];
			$data['editingSessionId'] = $request->getVal( 'editingStatsId' );

			if ( $status->isOK() ) {
				$action = 'saveSuccess';
			} else {
				$action = 'saveFailure';
				$errors = $status->getErrorsArray();

				if ( isset( $errors[0][0] ) ) {
					$data['action.saveFailure.message'] = $errors[0][0];
				}

				if ( $status->value === EditPage::AS_CONFLICT_DETECTED ) {
					$data['action.saveFailure.type'] = 'editConflict';
				} elseif ( $status->value === EditPage::AS_ARTICLE_WAS_DELETED ) {
					$data['action.saveFailure.type'] = 'editPageDeleted';
				} elseif ( isset( $errors[0][0] ) && $errors[0][0] === 'abusefilter-disallowed' ) {
					$data['action.saveFailure.type'] = 'extensionAbuseFilter';
				} elseif ( isset( $editPage->getArticle()->getPage()->ConfirmEdit_ActivateCaptcha ) ) {
					// TODO: :(
					$data['action.saveFailure.type'] = 'extensionCaptcha';
				} elseif ( isset( $errors[0][0] ) && $errors[0][0] === 'spamprotectiontext' ) {
					$data['action.saveFailure.type'] = 'extensionSpamBlacklist';
				} else {
					// Catch everything else... We don't seem to get userBadToken or
					// userNewUser through this hook.
					$data['action.saveFailure.type'] = 'responseUnknown';
				}
			}
			self::doEventLogging( $action, $article, $data );
		}

		return true;
	}
}
