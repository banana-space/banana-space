<?php

class CiteThisPageHooks {

	/**
	 * @param SkinTemplate &$skintemplate
	 * @param array &$nav_urls
	 * @param int &$oldid
	 * @param int &$revid
	 * @return bool
	 */
	public static function onSkinTemplateBuildNavUrlsNav_urlsAfterPermalink(
		&$skintemplate, &$nav_urls, &$oldid, &$revid
	) {
		// check whether we’re in the right namespace, the $revid has the correct type and is not empty
		// (which would mean that the current page doesn’t exist)
		$title = $skintemplate->getTitle();
		if ( self::shouldAddLink( $title ) && $revid !== 0 && !empty( $revid ) ) {
			$nav_urls['citethispage'] = [
				'text' => $skintemplate->msg( 'citethispage-link' )->text(),
				'href' => SpecialPage::getTitleFor( 'CiteThisPage' )
					->getLocalURL( [ 'page' => $title->getPrefixedDBkey(), 'id' => $revid ] ),
				'id' => 't-cite',
				# Used message keys: 'tooltip-citethispage', 'accesskey-citethispage'
				'single-id' => 'citethispage',
			];
		}

		return true;
	}

	/**
	 * Checks, if the "cite this page" link should be added. By default the link is added to all
	 * pages in the main namespace, and additionally to pages, which are in one of the namespaces
	 * named in $wgCiteThisPageAddiotionalNamespaces.
	 *
	 * @param Title $title
	 * @return bool
	 */
	private static function shouldAddLink( Title $title ) {
		global $wgCiteThisPageAdditionalNamespaces;

		return $title->isContentPage() ||
			(
				isset( $wgCiteThisPageAdditionalNamespaces[$title->getNamespace()] ) &&
				$wgCiteThisPageAdditionalNamespaces[$title->getNamespace()]
			);
	}

	/**
	 * @param BaseTemplate $baseTemplate
	 * @param array &$toolbox
	 * @return bool
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		if ( isset( $baseTemplate->data['nav_urls']['citethispage'] ) ) {
			$toolbox['citethispage'] = $baseTemplate->data['nav_urls']['citethispage'];
		}

		return true;
	}
}
