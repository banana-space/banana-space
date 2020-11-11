<?php
/**
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author Mark Holmquist <mtraceur@member.fsf.org>
 * @copyright Copyright © 2013, Mark Holmquist
 */

class MultimediaViewerHooks {
	/** Link to more information about this module */
	protected static $infoLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Extension:Media_Viewer/About';

	/** Link to a page where this module can be discussed */
	protected static $discussionLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Extension_talk:Media_Viewer/About';

	/** Link to help about this module */
	protected static $helpLink =
		'https://mediawiki.org/wiki/Special:MyLanguage/Help:Extension:Media_Viewer';

	public static function onUserGetDefaultOptions( &$defaultOptions ) {
		global $wgMediaViewerEnableByDefault;

		if ( $wgMediaViewerEnableByDefault ) {
			$defaultOptions['multimediaviewer-enable'] = 1;
		}

		return true;
	}

	public static function onExtensionFunctions() {
		global $wgResourceModules;

		if ( isset( $wgResourceModules['ext.eventLogging'] ) ) {
			$wgResourceModules['mmv.lightboxinterface']['dependencies'][] = 'ext.eventLogging';
			$wgResourceModules['mmv']['dependencies'][] = 'ext.eventLogging';
			$wgResourceModules['mmv.bootstrap.autostart']['dependencies'][] = 'ext.eventLogging';
		}
	}

	public static function onEventLoggingRegisterSchemas( array &$schemas ) {
		 $schemas += [
			'MediaViewer' => 10867062,
			'MultimediaViewerNetworkPerformance' => 15573630,
			'MultimediaViewerDuration' => 10427980,
			'MultimediaViewerAttribution' => 9758179,
			'MultimediaViewerDimensions' => 10014238,
		];
	}

	/**
	 * Checks the context for whether to load the viewer.
	 * @param User $user
	 * @return bool
	 */
	protected static function shouldHandleClicks( $user ) {
		global $wgMediaViewerIsInBeta, $wgMediaViewerEnableByDefaultForAnonymous,
			$wgMediaViewerEnableByDefault;

		if ( $wgMediaViewerIsInBeta && class_exists( 'BetaFeatures' ) ) {
			return BetaFeatures::isFeatureEnabled( $user, 'multimedia-viewer' );
		}

		if ( $wgMediaViewerEnableByDefaultForAnonymous === null ) {
			$enableByDefaultForAnons = $wgMediaViewerEnableByDefault;
		} else {
			$enableByDefaultForAnons = $wgMediaViewerEnableByDefaultForAnonymous;
		}

		if ( !$user->isLoggedIn() ) {
			return (bool)$enableByDefaultForAnons;
		} else {
			return (bool)$user->getOption( 'multimediaviewer-enable' );
		}
	}

	/**
	 * Handler for all places where we add the modules
	 * Could be on article pages or on Category pages
	 * @param OutputPage &$out
	 * @return bool
	 */
	protected static function getModules( &$out ) {
		$out->addModules( [ 'mmv.head', 'mmv.bootstrap.autostart' ] );

		return true;
	}

	/**
	 * Handler for BeforePageDisplay hook
	 * Add JavaScript to the page when an image is on it
	 * and the user has enabled the feature if BetaFeatures is installed
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 * @return bool
	 */
	public static function getModulesForArticle( &$out, &$skin ) {
		$pageHasThumbnails = count( $out->getFileSearchOptions() ) > 0;
		$pageIsFilePage = $out->getTitle()->inNamespace( NS_FILE );
		$fileRelatedSpecialPages = [ 'NewFiles', 'ListFiles', 'MostLinkedFiles',
			'MostGloballyLinkedFiles', 'UncategorizedFiles', 'UnusedFiles', 'Search' ];
		$pageIsFileRelatedSpecialPage = $out->getTitle()->inNamespace( NS_SPECIAL )
			&& in_array( $out->getTitle()->getText(), $fileRelatedSpecialPages );

		if ( $pageHasThumbnails || $pageIsFilePage || $pageIsFileRelatedSpecialPage ) {
			return self::getModules( $out );
		}

		return true;
	}

	/**
	 * Handler for CategoryPageView hook
	 * Add JavaScript to the page if there are images in the category
	 * @param CategoryPage &$catPage
	 * @return bool
	 */
	public static function getModulesForCategory( &$catPage ) {
		$title = $catPage->getTitle();
		$cat = Category::newFromTitle( $title );
		if ( $cat->getFileCount() > 0 ) {
			$out = $catPage->getContext()->getOutput();
			return self::getModules( $out );
		}

		return true;
	}

	/**
	 * Add a beta preference to gate the feature
	 * @param User $user
	 * @param array &$prefs
	 * @return true
	 */
	public static function getBetaPreferences( $user, &$prefs ) {
		global $wgExtensionAssetsPath, $wgMediaViewerIsInBeta;

		if ( !$wgMediaViewerIsInBeta ) {
			return true;
		}

		$prefs['multimedia-viewer'] = [
			'label-message' => 'multimediaviewer-pref',
			'desc-message' => 'multimediaviewer-pref-desc',
			'info-link' => self::$infoLink,
			'discussion-link' => self::$discussionLink,
			'help-link' => self::$helpLink,
			'screenshot' => [
				'ltr' => "$wgExtensionAssetsPath/MultimediaViewer/viewer-ltr.svg",
				'rtl' => "$wgExtensionAssetsPath/MultimediaViewer/viewer-rtl.svg",
			],
		];

		return true;
	}

	/**
	 * Adds a default-enabled preference to gate the feature on non-beta sites
	 * @param User $user
	 * @param array &$prefs
	 * @return true
	 */
	public static function getPreferences( $user, &$prefs ) {
		global $wgMediaViewerIsInBeta;

		if ( !$wgMediaViewerIsInBeta ) {
			$prefs['multimediaviewer-enable'] = [
				'type' => 'toggle',
				'label-message' => 'multimediaviewer-optin-pref',
				'section' => 'rendering/files',
			];
		}

		return true;
	}

	/**
	 * Export variables used in both PHP and JS to keep DRY
	 * @param array &$vars
	 * @return bool
	 */
	public static function resourceLoaderGetConfigVars( &$vars ) {
		global $wgMediaViewerActionLoggingSamplingFactorMap,
			$wgMediaViewerNetworkPerformanceSamplingFactor,
			$wgMediaViewerDurationLoggingSamplingFactor,
			$wgMediaViewerDurationLoggingLoggedinSamplingFactor,
			$wgMediaViewerAttributionLoggingSamplingFactor,
			$wgMediaViewerDimensionLoggingSamplingFactor,
			$wgMediaViewerIsInBeta, $wgMediaViewerUseThumbnailGuessing, $wgMediaViewerExtensions,
			$wgMediaViewerImageQueryParameter, $wgMediaViewerRecordVirtualViewBeaconURI;

		$vars['wgMultimediaViewer'] = [
			'infoLink' => self::$infoLink,
			'discussionLink' => self::$discussionLink,
			'helpLink' => self::$helpLink,
			'useThumbnailGuessing' => (bool)$wgMediaViewerUseThumbnailGuessing,
			'durationSamplingFactor' => $wgMediaViewerDurationLoggingSamplingFactor,
			'durationSamplingFactorLoggedin' => $wgMediaViewerDurationLoggingLoggedinSamplingFactor,
			'networkPerformanceSamplingFactor' => $wgMediaViewerNetworkPerformanceSamplingFactor,
			'actionLoggingSamplingFactorMap' => $wgMediaViewerActionLoggingSamplingFactorMap,
			'attributionSamplingFactor' => $wgMediaViewerAttributionLoggingSamplingFactor,
			'dimensionSamplingFactor' => $wgMediaViewerDimensionLoggingSamplingFactor,
			'imageQueryParameter' => $wgMediaViewerImageQueryParameter,
			'recordVirtualViewBeaconURI' => $wgMediaViewerRecordVirtualViewBeaconURI,
			'tooltipDelay' => 1000,
			'extensions' => $wgMediaViewerExtensions,
		];
		$vars['wgMediaViewer'] = true;
		$vars['wgMediaViewerIsInBeta'] = $wgMediaViewerIsInBeta;

		return true;
	}

	/**
	 * Export variables which depend on the current user
	 * @param array &$vars
	 * @param OutputPage $out
	 */
	public static function makeGlobalVariablesScript( &$vars, OutputPage $out ) {
		$defaultUserOptions = User::getDefaultOptions();

		$user = $out->getUser();
		$vars['wgMediaViewerOnClick'] = self::shouldHandleClicks( $user );
		// needed because of bug 69942; could be different for anon and logged-in
		$vars['wgMediaViewerEnabledByDefault'] =
			!empty( $defaultUserOptions['multimediaviewer-enable'] );
	}

	/**
	 * Get modules for testing our JavaScript
	 * @param array &$testModules
	 * @param ResourceLoader &$resourceLoader
	 * @return bool
	 */
	public static function getTestModules( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules['qunit']['mmv.tests'] = [
			'scripts' => [
				'tests/qunit/mmv/mmv.bootstrap.test.js',
				'tests/qunit/mmv/mmv.test.js',
				'tests/qunit/mmv/mmv.lightboxinterface.test.js',
				'tests/qunit/mmv/mmv.lightboximage.test.js',
				'tests/qunit/mmv/mmv.ThumbnailWidthCalculator.test.js',
				'tests/qunit/mmv/mmv.EmbedFileFormatter.test.js',
				'tests/qunit/mmv/mmv.Config.test.js',
				'tests/qunit/mmv/mmv.HtmlUtils.test.js',
				'tests/qunit/mmv/logging/mmv.logging.DurationLogger.test.js',
				'tests/qunit/mmv/logging/mmv.logging.PerformanceLogger.test.js',
				'tests/qunit/mmv/logging/mmv.logging.ActionLogger.test.js',
				'tests/qunit/mmv/logging/mmv.logging.AttributionLogger.test.js',
				'tests/qunit/mmv/logging/mmv.logging.DimensionLogger.test.js',
				'tests/qunit/mmv/logging/mmv.logging.ViewLogger.test.js',
				'tests/qunit/mmv/model/mmv.model.test.js',
				'tests/qunit/mmv/model/mmv.model.IwTitle.test.js',
				'tests/qunit/mmv/model/mmv.model.TaskQueue.test.js',
				'tests/qunit/mmv/model/mmv.model.License.test.js',
				'tests/qunit/mmv/model/mmv.model.Image.test.js',
				'tests/qunit/mmv/model/mmv.model.Repo.test.js',
				'tests/qunit/mmv/model/mmv.model.EmbedFileInfo.test.js',
				'tests/qunit/mmv/provider/mmv.provider.Api.test.js',
				'tests/qunit/mmv/provider/mmv.provider.ImageInfo.test.js',
				'tests/qunit/mmv/provider/mmv.provider.FileRepoInfo.test.js',
				'tests/qunit/mmv/provider/mmv.provider.ThumbnailInfo.test.js',
				'tests/qunit/mmv/provider/mmv.provider.GuessedThumbnailInfo.test.js',
				'tests/qunit/mmv/provider/mmv.provider.Image.test.js',
				'tests/qunit/mmv/routing/mmv.routing.MainFileRoute.test.js',
				'tests/qunit/mmv/routing/mmv.routing.ThumbnailRoute.test.js',
				'tests/qunit/mmv/routing/mmv.routing.Router.test.js',
				'tests/qunit/mmv/ui/mmv.ui.test.js',
				'tests/qunit/mmv/ui/mmv.ui.canvas.test.js',
				'tests/qunit/mmv/ui/mmv.ui.canvasButtons.test.js',
				'tests/qunit/mmv/ui/mmv.ui.description.test.js',
				'tests/qunit/mmv/ui/mmv.ui.download.pane.test.js',
				'tests/qunit/mmv/ui/mmv.ui.metadataPanel.test.js',
				'tests/qunit/mmv/ui/mmv.ui.metadataPanelScroller.test.js',
				'tests/qunit/mmv/ui/mmv.ui.progressBar.test.js',
				'tests/qunit/mmv/ui/mmv.ui.permission.test.js',
				'tests/qunit/mmv/ui/mmv.ui.stripeButtons.test.js',
				'tests/qunit/mmv/ui/mmv.ui.reuse.dialog.test.js',
				'tests/qunit/mmv/ui/mmv.ui.reuse.embed.test.js',
				'tests/qunit/mmv/ui/mmv.ui.reuse.share.test.js',
				'tests/qunit/mmv/ui/mmv.ui.reuse.tab.test.js',
				'tests/qunit/mmv/ui/mmv.ui.reuse.utils.test.js',
				'tests/qunit/mmv/ui/mmv.ui.tipsyDialog.test.js',
				'tests/qunit/mmv/ui/mmv.ui.truncatableTextField.test.js',
				'tests/qunit/mmv/ui/mmv.ui.viewingOptions.test.js',
				'tests/qunit/mmv/mmv.testhelpers.js',
			],
			'dependencies' => [
				'mmv.head',
				'mmv.bootstrap',
				'mmv',
				'mmv.ui.ondemandshareddependencies',
				'mmv.ui.reuse.shareembed',
				'mmv.ui.download.pane',
				'mmv.ui.tipsyDialog',
				'moment',
			],
			'localBasePath' => dirname( __DIR__ ),
			'remoteExtPath' => 'MultimediaViewer',
		];

		return true;
	}

	/**
	 * Modify thumbnail DOM
	 * @param ThumbnailImage $thumbnail
	 * @param array &$attribs Attributes of the <img> element
	 * @param array|bool &$linkAttribs Attributes of the wrapping <a> element
	 * @return true
	 */
	public static function thumbnailBeforeProduceHTML( ThumbnailImage $thumbnail, array &$attribs,
		&$linkAttribs
	) {
		$file = $thumbnail->getFile();

		if ( $file ) {
			// At the moment all classes that extend File have getWidth() and getHeight()
			// but since the File class doesn't have these methods defined, this check
			// is more future-proof

			if ( method_exists( $file, 'getWidth' ) ) {
				$attribs['data-file-width'] = $file->getWidth();
			}

			if ( method_exists( $file, 'getHeight' ) ) {
				$attribs['data-file-height'] = $file->getHeight();
			}
		}

		return true;
	}
}
