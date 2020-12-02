<?php
/**
 * MonoBook nouveau.
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
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
 * @ingroup Skins
 */

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @ingroup Skins
 */
class SkinMonoBook extends SkinTemplate {
	/** Using MonoBook. */
	public $skinname = 'monobook';
	public $stylename = 'MonoBook';
	public $template = 'MonoBookTemplate';

	/**
	 * @param OutputPage $out
	 */
	public function setupSkinUserCss( OutputPage $out ) {
		parent::setupSkinUserCss( $out );

		if ( $out->getUser()->getOption( 'monobook-responsive' ) ) {
			$out->addMeta( 'viewport',
				'width=device-width, initial-scale=1.0, ' .
				'user-scalable=yes, minimum-scale=0.25, maximum-scale=5.0'
			);
			$styleModule = 'skins.monobook.responsive';
			$out->addModules( [
				'skins.monobook.mobile'
			] );

			if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) && $out->getUser()->isLoggedIn() ) {
				$out->addModules( [ 'skins.monobook.mobile.echohack' ] );
			}
			if ( ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' ) ) {
				$out->addModules( [ 'skins.monobook.mobile.uls' ] );
			}
		} else {
			$styleModule = 'skins.monobook.styles';
		}

		$out->addModuleStyles( [
			'mediawiki.skinning.content.externallinks',
			$styleModule
		] );

		// Force desktop styles in IE 8-; no support for @media widths
		// FIXME: Remove conditional comment dependency.
		$out->addStyle( $this->stylename . '/resources/screen-desktop.css', 'screen', 'lt IE 9' );
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['monobook-responsive'] = [
			'type' => 'toggle',
			'label-message' => 'monobook-responsive-label',
			'section' => 'rendering/skin/skin-prefs',
			// Only show this section when the Monobook skin is checked. The JavaScript client also uses
			// this state to determine whether to show or hide the whole section.
			'hide-if' => [ '!==', 'wpskin', 'monobook' ],
		];
	}

	/**
	 * Handler for ResourceLoaderRegisterModules hook
	 * Check if extensions are loaded
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public static function registerMobileExtensionStyles( ResourceLoader $resourceLoader ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			$resourceLoader->register( 'skins.monobook.mobile.echohack', [
				'localBasePath' => __DIR__ . '/..',
				'remoteSkinPath' => 'MonoBook',

				'targets' => [ 'desktop', 'mobile' ],
				'scripts' => [ 'resources/mobile-echo.js' ],
				'styles' => [ 'resources/mobile-echo.less' => [
					'media' => 'screen and (max-width: 550px)'
				] ],
				'dependencies' => [ 'oojs-ui.styles.icons-alerts', 'mediawiki.util' ],
				'messages' => [ 'monobook-notifications-link', 'monobook-notifications-link-none' ]
			] );
		}

		if ( ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' ) ) {
			$resourceLoader->register( 'skins.monobook.mobile.uls', [
				'localBasePath' => __DIR__ . '/..',
				'remoteSkinPath' => 'MonoBook',

				'targets' => [ 'desktop' ],
				'scripts' => [ 'resources/mobile-uls.js' ],
				'dependencies' => [ 'ext.uls.interface' ],
			] );
		}
	}
}
