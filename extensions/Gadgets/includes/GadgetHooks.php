<?php

/**
 * Copyright © 2007 Daniel Kinzler
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\WrappedString;

class GadgetHooks {
	/**
	 * Callback on extension registration
	 *
	 * Register hooks based on version to keep support for mediawiki versions before 1.35
	 */
	public static function onRegistration() {
		global $wgHooks;

		if ( version_compare( MW_VERSION, '1.35', '>=' ) ) {
			// Use PageSaveComplete
			$wgHooks['PageSaveComplete'][] = 'GadgetHooks::onPageSaveComplete';
		} else {
			// Use both PageContentInsertComplete and PageContentSaveComplete
			$wgHooks['PageContentSaveComplete'][] = 'GadgetHooks::onPageContentSaveComplete';
			$wgHooks['PageContentInsertComplete'][] = 'GadgetHooks::onPageContentInsertComplete';
		}
	}

	/**
	 * PageContentSaveComplete hook handler.
	 *
	 * Only run in versions of mediawiki before 1.35; in 1.35+, ::onPageSaveComplete is used
	 *
	 * @note Hook provides other parameters, but only the wikipage is needed
	 * @param WikiPage $wikiPage
	 */
	public static function onPageContentSaveComplete( WikiPage $wikiPage ) {
		// update cache if MediaWiki:Gadgets-definition was edited
		GadgetRepo::singleton()->handlePageUpdate( $wikiPage->getTitle() );
	}

	/**
	 * After a new page is created in the Gadget definition namespace,
	 * invalidate the list of gadget ids
	 *
	 * Only run in versions of mediawiki before 1.35; in 1.35+, ::onPageSaveComplete is used
	 *
	 * @note Hook provides other parameters, but only the wikipage is needed
	 * @param WikiPage $page
	 */
	public static function onPageContentInsertComplete( WikiPage $page ) {
		if ( $page->getTitle()->inNamespace( NS_GADGET_DEFINITION ) ) {
			GadgetRepo::singleton()->handlePageCreation( $page->getTitle() );
		}
	}

	/**
	 * PageSaveComplete hook handler
	 *
	 * Only run in versions of mediawiki begining 1.35; before 1.35, ::onPageContentSaveComplete
	 * and ::onPageContentInsertComplete are used
	 *
	 * @note paramaters include classes not available before 1.35, so for those typehints
	 * are not used. The variable name reflects the class
	 *
	 * @param WikiPage $wikiPage
	 * @param mixed $userIdentity unused
	 * @param string $summary
	 * @param int $flags
	 * @param mixed $revisionRecord unused
	 * @param mixed $editResult unused
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		$userIdentity,
		string $summary,
		int $flags,
		$revisionRecord,
		$editResult
	) {
		$title = $wikiPage->getTitle();
		$repo = GadgetRepo::singleton();

		if ( $flags & EDIT_NEW ) {
			if ( $title->inNamespace( NS_GADGET_DEFINITION ) ) {
				$repo->handlePageCreation( $title );
			}
		}

		$repo->handlePageUpdate( $title );
	}

	/**
	 * UserGetDefaultOptions hook handler
	 * @param array &$defaultOptions Array of default preference keys and values
	 */
	public static function userGetDefaultOptions( array &$defaultOptions ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		/**
		 * @var $gadget Gadget
		 */
		foreach ( $gadgets as $thisSection ) {
			foreach ( $thisSection as $gadgetId => $gadget ) {
				if ( $gadget->isOnByDefault() ) {
					$defaultOptions['gadget-' . $gadgetId] = 1;
				}
			}
		}
	}

	/**
	 * GetPreferences hook handler.
	 * @param User $user
	 * @param array &$preferences Preference descriptions
	 */
	public static function getPreferences( User $user, array &$preferences ) {
		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$options = [];
		$default = [];
		$skin = RequestContext::getMain()->getSkin();
		foreach ( $gadgets as $section => $thisSection ) {
			$available = [];

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $thisSection as $gadget ) {
				if (
					!$gadget->isHidden()
					&& $gadget->isAllowed( $user )
					&& $gadget->isSkinSupported( $skin )
				) {
					$gname = $gadget->getName();
					# bug 30182: dir="auto" because it's often not translated
					$desc = '<span dir="auto">' . $gadget->getDescription() . '</span>';
					$available[$desc] = $gname;
					if ( $gadget->isEnabled( $user ) ) {
						$default[] = $gname;
					}
				}
			}

			if ( $section !== '' ) {
				$section = wfMessage( "gadget-section-$section" )->parse();

				if ( count( $available ) ) {
					$options[$section] = $available;
				}
			} else {
				$options = array_merge( $options, $available );
			}
		}

		$preferences['gadgets-intro'] =
			[
				'type' => 'info',
				'default' => wfMessage( 'gadgets-prefstext' )->parseAsBlock(),
				'section' => 'gadgets',
				'raw' => true,
			];

		$preferences['gadgets'] =
			[
				'type' => 'multiselect',
				'options' => $options,
				'section' => 'gadgets',
				'label' => '&#160;',
				'prefix' => 'gadget-',
				'default' => $default,
				'noglobal' => true,
			];
	}

	/**
	 * ResourceLoaderRegisterModules hook handler.
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function registerModules( ResourceLoader &$resourceLoader ) {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();

		foreach ( $ids as $id ) {
			$resourceLoader->register( Gadget::getModuleName( $id ), [
				'class' => 'GadgetResourceLoaderModule',
				'id' => $id,
			] );
		}
	}

	/**
	 * BeforePageDisplay hook handler.
	 * @param OutputPage $out
	 */
	public static function beforePageDisplay( OutputPage $out ) {
		$repo = GadgetRepo::singleton();
		$ids = $repo->getGadgetIds();
		if ( !$ids ) {
			return;
		}

		$lb = new LinkBatch();
		$lb->setCaller( __METHOD__ );
		$enabledLegacyGadgets = [];

		/**
		 * @var $gadget Gadget
		 */
		$user = $out->getUser();
		foreach ( $ids as $id ) {
			try {
				$gadget = $repo->getGadget( $id );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
			$peers = [];
			foreach ( $gadget->getPeers() as $peerName ) {
				try {
					$peers[] = $repo->getGadget( $peerName );
				} catch ( InvalidArgumentException $e ) {
					// Ignore
					// @todo: Emit warning for invalid peer?
				}
			}
			if ( $gadget->isEnabled( $user )
				&& $gadget->isAllowed( $user )
				&& $gadget->isSkinSupported( $out->getSkin() )
				&& ( in_array( $out->getTarget() ?? 'desktop', $gadget->getTargets() ) )
			) {
				if ( $gadget->hasModule() ) {
					if ( $gadget->getType() === 'styles' ) {
						$out->addModuleStyles( Gadget::getModuleName( $gadget->getName() ) );
					} else {
						$out->addModules( Gadget::getModuleName( $gadget->getName() ) );
						// Load peer modules
						foreach ( $peers as $peer ) {
							if ( $peer->getType() === 'styles' ) {
								$out->addModuleStyles( Gadget::getModuleName( $peer->getName() ) );
							}
							// Else, if not type=styles: Use dependencies instead.
							// Note: No need for recursion as styles modules don't support
							// either of 'dependencies' and 'peers'.
						}
					}
				}

				if ( $gadget->getLegacyScripts() ) {
					$enabledLegacyGadgets[] = $id;
				}
			}
		}

		$strings = [];
		foreach ( $enabledLegacyGadgets as $id ) {
			$strings[] = self::makeLegacyWarning( $id );
		}
		$out->addHTML( WrappedString::join( "\n", $strings ) );
	}

	private static function makeLegacyWarning( $id ) {
		$special = SpecialPage::getTitleFor( 'Gadgets' );

		return ResourceLoader::makeInlineScript(
			Xml::encodeJsCall( 'mw.log.warn', [
				"Gadget \"$id\" was not loaded. Please migrate it to use ResourceLoader. " .
				'See <' . $special->getCanonicalURL() . '>.'
			] )
		);
	}

	/**
	 * Valid gadget definition page after content is modified
	 *
	 * @param IContextSource $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @throws Exception
	 * @return bool
	 */
	public static function onEditFilterMergedContent( IContextSource $context,
		Content $content,
		Status $status,
		$summary
	) {
		$title = $context->getTitle();

		if ( !$title->inNamespace( NS_GADGET_DEFINITION ) ) {
			return true;
		}

		if ( !$content instanceof GadgetDefinitionContent ) {
			// This should not be possible?
			throw new Exception(
				"Tried to save non-GadgetDefinitionContent to {$title->getPrefixedText()}"
			);
		}

		$validateStatus = $content->validate();
		if ( !$validateStatus->isGood() ) {
			$status->merge( $validateStatus );
			return false;
		}

		return true;
	}

	/**
	 * Mark the Title as having a content model of javascript or css for pages
	 * in the Gadget namespace based on their file extension
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
		if ( $title->inNamespace( NS_GADGET ) ) {
			preg_match( '!\.(css|js)$!u', $title->getText(), $ext );
			$ext = $ext[1] ?? '';
			switch ( $ext ) {
				case 'js':
					$model = 'javascript';
					return false;
				case 'css':
					$model = 'css';
					return false;
			}
		}

		return true;
	}

	/**
	 * Set the CodeEditor language for Gadget definition pages. It already
	 * knows the language for Gadget: namespace pages.
	 *
	 * @param Title $title
	 * @param string &$lang
	 * @return bool
	 */
	public static function onCodeEditorGetPageLanguage( Title $title, &$lang ) {
		if ( $title->hasContentModel( 'GadgetDefinition' ) ) {
			$lang = 'json';
			return false;
		}

		return true;
	}

	/**
	 * Add the GadgetUsage special page to the list of QueryPages.
	 * @param array &$queryPages
	 */
	public static function onwgQueryPages( array &$queryPages ) {
		$queryPages[] = [ 'SpecialGadgetUsage', 'GadgetUsage' ];
	}

	/**
	 * Prevent gadget preferences from being deleted.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/DeleteUnknownPreferences
	 * @param string[] &$where Array of where clause conditions to add to.
	 * @param IDatabase $db
	 */
	public static function onDeleteUnknownPreferences( array &$where, IDatabase $db ) {
		$where[] = 'up_property NOT' . $db->buildLike( 'gadget-', $db->anyString() );
	}
}
