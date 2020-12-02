<?php
/**
 * ResourceLoader module for the 'ext.visualEditor.desktopArticleTarget.init'
 * module. Necessary to incorporate the VisualEditorTabMessages
 * configuration setting.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\MediaWikiServices;

class VisualEditorDesktopArticleTargetInitModule extends ResourceLoaderFileModule {

	/**
	 * @inheritDoc
	 */
	public function getMessages() {
		$messages = parent::getMessages();
		$services = MediaWikiServices::getInstance();

		$veConfig = $services->getConfigFactory()->makeConfig( 'visualeditor' );
		$messages = array_merge(
			$messages,
			array_filter( $veConfig->get( 'VisualEditorTabMessages' ) )
		);

		// Some skins don't use the default 'edit' and 'create' message keys.
		// Check the localisation cache for which skins have a custom message for this.
		// We only need this for the current skin, but ResourceLoader's message cache
		// does not fragment by skin.
		foreach ( $services->getSkinFactory()->getSkinNames() as $skname => $unused ) {
			foreach ( [ 'edit', 'create' ] as $msgKey ) {
				// Messages: vector-view-edit, vector-view-create
				// Disable database lookups for site-level message overrides as they
				// are expensive and not needed here (T221294). We only care whether the
				// message key is known to localisation cache at all.
				$msg = wfMessage( "$skname-view-$msgKey" )->useDatabase( false )->inContentLanguage();
				if ( $msg->exists() ) {
					$messages[] = "$skname-view-$msgKey";
				}
			}
		}

		return $messages;
	}

}
