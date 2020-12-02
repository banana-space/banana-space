<?php

class RenameuserHooks {
	/**
	 * Show a log if the user has been renamed and point to the new username.
	 * Don't show the log if the $oldUserName exists as a user.
	 *
	 * @param Article $article
	 */
	public static function onShowMissingArticle( Article $article ) {
		$title = $article->getTitle();
		$oldUser = User::newFromName( $title->getBaseText() );
		if ( ( $title->getNamespace() === NS_USER || $title->getNamespace() === NS_USER_TALK ) &&
			( $oldUser && $oldUser->isAnon() )
		) {
			// Get the title for the base userpage
			$page = Title::makeTitle( NS_USER, str_replace( ' ', '_', $title->getBaseText() ) )
				->getPrefixedDBkey();
			$out = $article->getContext()->getOutput();
			LogEventsList::showLogExtract(
				$out,
				'renameuser',
				$page,
				'',
				[
					'lim' => 10,
					'showIfEmpty' => false,
					'msgKey' => [ 'renameuser-renamed-notice', $title->getBaseText() ]
				]
			);
		}
	}

	/**
	 * Shows link to Special:Renameuser on Special:Contributions/foo
	 *
	 * @param int $id
	 * @param Title $nt
	 * @param array &$tools
	 * @param SpecialPage $sp
	 */
	public static function onContributionsToolLinks(
		$id, Title $nt, array &$tools, SpecialPage $sp
	) {
		if ( $id && $sp->getUser()->isAllowed( 'renameuser' ) ) {
			$tools['renameuser'] = $sp->getLinkRenderer()->makeKnownLink(
				SpecialPage::getTitleFor( 'Renameuser' ),
				$sp->msg( 'renameuser-linkoncontribs', $nt->getText() )->text(),
				[ 'title' => $sp->msg( 'renameuser-linkoncontribs-text', $nt->getText() )->parse() ],
				[ 'oldusername' => $nt->getText() ]
			);
		}
	}

	/**
	 * So users can just type in a username for target and it'll work
	 * @param array &$types
	 */
	public static function onGetLogTypesOnUser( array &$types ) {
		$types[] = 'renameuser';
	}
}
