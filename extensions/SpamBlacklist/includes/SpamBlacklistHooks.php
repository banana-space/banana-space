<?php

/**
 * Hooks for the spam blacklist extension
 */
class SpamBlacklistHooks {

	/**
	 * Hook function for EditFilterMergedContent
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 *
	 * @return bool
	 */
	static function filterMergedContent(
		IContextSource $context,
		Content $content,
		Status $status,
		$summary,
		User $user,
		$minoredit
	) {
		$title = $context->getTitle();

		// get the link from the not-yet-saved page content.
		$editInfo = $context->getWikiPage()->prepareContentForEdit( $content );
		$pout = $editInfo->output;
		$links = array_keys( $pout->getExternalLinks() );

		// HACK: treat the edit summary as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $summary ) ) {
			$links[] = $summary;
		}

		$spamObj = BaseBlacklist::getSpamBlacklist();
		$matches = $spamObj->filter( $links, $title );

		if ( $matches !== false ) {
			$status->fatal( 'spamprotectiontext' );

			foreach ( $matches as $match ) {
				$status->fatal( 'spamprotectionmatch', $match );
			}

			$status->apiHookResult = [
				'spamblacklist' => implode( '|', $matches ),
			];
		}

		// Always return true, EditPage will look at $status->isOk().
		return true;
	}

	public static function onParserOutputStashForEdit(
		WikiPage $page,
		Content $content,
		ParserOutput $output
	) {
		$links = array_keys( $output->getExternalLinks() );
		$spamObj = BaseBlacklist::getSpamBlacklist();
		$spamObj->warmCachesForFilter( $page->getTitle(), $links );
	}

	/**
	 * Verify that the user can send emails
	 *
	 * @param User &$user
	 * @param array &$hookErr
	 * @return bool
	 */
	public static function userCanSendEmail( &$user, &$hookErr ) {
		$blacklist = BaseBlacklist::getEmailBlacklist();
		if ( $blacklist->checkUser( $user ) ) {
			return true;
		}

		$hookErr = [ 'spam-blacklisted-email', 'spam-blacklisted-email-text', null ];

		return false;
	}

	/**
	 * Hook function for EditFilter
	 * Confirm that a local blacklist page being saved is valid,
	 * and toss back a warning to the user if it isn't.
	 *
	 * @param EditPage $editPage
	 * @param string $text
	 * @param string $section
	 * @param string &$hookError
	 * @return bool
	 */
	static function validate( EditPage $editPage, $text, $section, &$hookError ) {
		$title = $editPage->getTitle();
		$thisPageName = $title->getPrefixedDBkey();

		if ( !BaseBlacklist::isLocalSource( $title ) ) {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] not a local blacklist\n"
			);
			return true;
		}

		$type = BaseBlacklist::getTypeFromTitle( $title );
		if ( $type === false ) {
			return true;
		}

		$lines = explode( "\n", $text );

		$badLines = SpamRegexBatch::getBadLines( $lines, BaseBlacklist::getInstance( $type ) );
		if ( $badLines ) {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] given invalid input lines: " .
					implode( ', ', $badLines ) . "\n"
			);

			$badList = "*<code>" .
				implode( "</code>\n*<code>",
					array_map( 'wfEscapeWikiText', $badLines ) ) .
				"</code>\n";
			$hookError =
				"<div class='errorbox'>" .
					wfMessage( 'spam-invalid-lines' )->numParams( $badLines )->text() . "<br />" .
					$badList .
					"</div>\n" .
					"<br clear='all' />\n";
		} else {
			wfDebugLog( 'SpamBlacklist',
				"Spam blacklist validator: [[$thisPageName]] ok or empty blacklist\n"
			);
		}

		return true;
	}

	/**
	 * Hook function for PageContentSaveComplete
	 * Clear local spam blacklist caches on page save.
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $isMinor
	 * @param bool $isWatch
	 * @param string $section
	 * @param int $flags
	 * @param Revision|null $revision
	 * @param Status $status
	 * @param int $baseRevId
	 *
	 * @return bool
	 */
	static function pageSaveContent(
		WikiPage $wikiPage,
		User $user,
		Content $content,
		$summary,
		$isMinor,
		$isWatch,
		$section,
		$flags,
		$revision,
		Status $status,
		$baseRevId
	) {
		if ( $revision ) {
			BaseBlacklist::getSpamBlacklist()
				->doLogging( $user, $wikiPage->getTitle(), $revision->getId() );
		}

		if ( !BaseBlacklist::isLocalSource( $wikiPage->getTitle() ) ) {
			return true;
		}

		// This sucks because every Blacklist needs to be cleared
		foreach ( BaseBlacklist::getBlacklistTypes() as $type => $class ) {
			$blacklist = BaseBlacklist::getInstance( $type );
			$blacklist->clearCache();
		}

		return true;
	}

	/**
	 * @param UploadBase $upload
	 * @param User $user
	 * @param array $props
	 * @param string $comment
	 * @param string $pageText
	 * @param array|ApiMessage &$error
	 * @return bool
	 */
	public static function onUploadVerifyUpload(
		UploadBase $upload,
		User $user,
		array $props,
		$comment,
		$pageText,
		&$error
	) {
		$title = $upload->getTitle();

		// get the link from the not-yet-saved page content.
		$content = ContentHandler::makeContent( $pageText, $title );
		$parserOptions = $content->getContentHandler()->makeParserOptions( 'canonical' );
		$output = $content->getParserOutput( $title, null, $parserOptions );
		$links = array_keys( $output->getExternalLinks() );

		// HACK: treat comment as a link if it contains anything
		// that looks like it could be a URL or e-mail address.
		if ( preg_match( '/\S(\.[^\s\d]{2,}|[\/@]\S)/', $comment ) ) {
			$links[] = $comment;
		}
		if ( !$links ) {
			return true;
		}

		$spamObj = BaseBlacklist::getSpamBlacklist();
		$matches = $spamObj->filter( $links, $title );

		if ( $matches !== false ) {
			$error = new ApiMessage(
				wfMessage( 'spamprotectiontext' ),
				'spamblacklist',
				[
					'spamblacklist' => [ 'matches' => $matches ],
					'message' => [
						'key' => 'spamprotectionmatch',
						'params' => $matches[0],
					],
				]
			);
		}

		return true;
	}

	/**
	 * @param WikiPage &$article
	 * @param User &$user
	 * @param string &$reason
	 * @param string &$error
	 */
	public static function onArticleDelete( WikiPage &$article, User &$user, &$reason, &$error ) {
		$spam = BaseBlacklist::getSpamBlacklist();
		if ( !$spam->isLoggingEnabled() ) {
			return;
		}

		// Log the changes, but we only commit them once the deletion has happened.
		// We do that since the external links table could get cleared before the
		// ArticleDeleteComplete hook runs
		$spam->logUrlChanges( $spam->getCurrentLinks( $article->getTitle() ), [], [] );
	}

	/**
	 * @param WikiPage &$page
	 * @param User &$user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param LogEntry $logEntry
	 */
	public static function onArticleDeleteComplete(
		&$page,
		User &$user,
		$reason,
		$id,
		Content $content = null,
		LogEntry $logEntry
	) {
		$spam = BaseBlacklist::getSpamBlacklist();
		$spam->doLogging( $user, $page->getTitle(), $page->getLatest() );
	}
}
