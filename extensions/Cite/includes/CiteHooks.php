<?php
/**
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\MediaWikiServices;

class CiteHooks {

	/**
	 * Convert the content model of a message that is actually JSON to JSON. This
	 * only affects validation and UI when saving and editing, not loading the
	 * content.
	 *
	 * @param Title $title
	 * @param string &$model
	 * @return bool
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, &$model ) {
		if (
			$title->inNamespace( NS_MEDIAWIKI ) &&
			(
				$title->getText() == 'Visualeditor-cite-tool-definition.json' ||
				$title->getText() == 'Cite-tool-definition.json'
			)
		) {
			$model = CONTENT_MODEL_JSON;
		}

		return true;
	}

	/**
	 * Conditionally register the unit testing module for the ext.cite.visualEditor module
	 * only if that module is loaded
	 *
	 * @param array &$testModules The array of registered test modules
	 * @param ResourceLoader &$resourceLoader The reference to the resource loader
	 * @return true
	 */
	public static function onResourceLoaderTestModules(
		array &$testModules,
		ResourceLoader &$resourceLoader
	) {
		$resourceModules = $resourceLoader->getConfig()->get( 'ResourceModules' );

		if (
			isset( $resourceModules[ 'ext.visualEditor.mediawiki' ] ) ||
			$resourceLoader->isModuleRegistered( 'ext.visualEditor.mediawiki' )
		) {
			$testModules['qunit']['ext.cite.visualEditor.test'] = [
				'scripts' => [
					'modules/ve-cite/tests/ve.dm.citeExample.js',
					'modules/ve-cite/tests/ve.dm.Converter.test.js',
					'modules/ve-cite/tests/ve.dm.InternalList.test.js',
					'modules/ve-cite/tests/ve.dm.Transaction.test.js',
					'modules/ve-cite/tests/ve.ui.DiffElement.test.js',
					'modules/ve-cite/tests/ve.ui.MWWikitextStringTransferHandler.test.js',
				],
				'dependencies' => [
					'ext.cite.visualEditor',
					'ext.visualEditor.test'
				],
				'localBasePath' => dirname( __DIR__ ),
				'remoteExtPath' => 'Cite',
			];
		}

		return true;
	}

	/**
	 * Conditionally register resource loader modules that depends on the
	 * VisualEditor MediaWiki extension.
	 *
	 * @param ResourceLoader &$resourceLoader
	 * @return true
	 */
	public static function onResourceLoaderRegisterModules( &$resourceLoader ) {
		if ( ! class_exists( 'VisualEditorHooks' ) ) {
			return true;
		}

		$dir = dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

		$resourceLoader->register( "ext.cite.visualEditor.core", [
			'localBasePath' => $dir . 'modules',
			'remoteExtPath' => 'Cite/modules',
			"scripts" => [
				"ve-cite/ve.dm.MWReferenceModel.js",
				"ve-cite/ve.dm.MWReferencesListNode.js",
				"ve-cite/ve.dm.MWReferenceNode.js",
				"ve-cite/ve.ce.MWReferencesListNode.js",
				"ve-cite/ve.ce.MWReferenceNode.js",
				"ve-cite/ve.ui.MWReferencesListCommand.js"
			],
			"styles" => [
				"ve-cite/ve.ce.MWReferencesListNode.css",
				"ve-cite/ve.ce.MWReferenceNode.css"
			],
			"dependencies" => [
				"ext.visualEditor.mwcore"
			],
			"messages" => [
				"cite-ve-referenceslist-isempty",
				"cite-ve-referenceslist-isempty-default",
				"cite-ve-referenceslist-missingref",
				"cite-ve-referenceslist-missingref-in-list",
				"cite-ve-referenceslist-missingreflist",
				"visualeditor-internal-list-diff-default-group-name-mwreference",
				"visualeditor-internal-list-diff-group-name-mwreference"
			],
			"targets" => [
				"desktop",
				"mobile"
			]
		] );

		$resourceLoader->register( "ext.cite.visualEditor.data",
			[ "class" => "CiteDataModule" ] );

		$resourceLoader->register( "ext.cite.visualEditor.icons", [
			'localBasePath' => $dir . 'modules',
			'remoteExtPath' => 'Cite/modules',
			"class" => "ResourceLoaderImageModule",
			"selectorWithoutVariant" => ".oo-ui-icon-{name}",
			"selectorWithVariant" => ".oo-ui-image-{variant}.oo-ui-icon-{name}",
			"variants" => [
				"progressive" => [
					"color" => "#36c"
				]
			],
			"images" => [
				"ref-cite-book" => [
					"file" => "ve-cite/icons/ref-cite-book.svg"
				],
				"ref-cite-journal" => [
					"file" => [
						"ltr" => "ve-cite/icons/ref-cite-journal-ltr.svg",
						"rtl" => "ve-cite/icons/ref-cite-journal-rtl.svg"
					]
				],
				"ref-cite-news" => [
					"file" => [
						"ltr" => "ve-cite/icons/ref-cite-news-ltr.svg",
						"rtl" => "ve-cite/icons/ref-cite-news-rtl.svg"
					]
				],
				"ref-cite-web" => [
					"file" => "ve-cite/icons/ref-cite-web.svg"
				],
				"reference" => [
					"file" => [
						"ltr" => "ve-cite/icons/reference-ltr.svg",
						"rtl" => "ve-cite/icons/reference-rtl.svg"
					]
				],
				"reference-existing" => [
					"file" => [
						"ltr" => "ve-cite/icons/reference-existing-ltr.svg",
						"rtl" => "ve-cite/icons/reference-existing-rtl.svg"
					]
				],
				"references" => [
					"file" => [
						"ltr" => "ve-cite/icons/references-ltr.svg",
						"rtl" => "ve-cite/icons/references-rtl.svg"
					]
				],
			]
		] );

		$resourceLoader->register( "ext.cite.visualEditor", [
			'localBasePath' => $dir . 'modules',
			'remoteExtPath' => 'Cite/modules',
			"scripts" => [
				"ve-cite/ve.ui.MWReferenceGroupInputWidget.js",
				"ve-cite/ve.ui.MWReferenceSearchWidget.js",
				"ve-cite/ve.ui.MWReferenceResultWidget.js",
				"ve-cite/ve.ui.MWUseExistingReferenceCommand.js",
				"ve-cite/ve.ui.MWCitationDialog.js",
				"ve-cite/ve.ui.MWReferencesListDialog.js",
				"ve-cite/ve.ui.MWReferenceDialog.js",
				"ve-cite/ve.ui.MWReferenceDialogTool.js",
				"ve-cite/ve.ui.MWCitationDialogTool.js",
				"ve-cite/ve.ui.MWReferenceContextItem.js",
				"ve-cite/ve.ui.MWReferencesListContextItem.js",
				"ve-cite/ve.ui.MWCitationContextItem.js",
				"ve-cite/ve.ui.MWCitationAction.js",
				"ve-cite/ve.ui.MWReference.init.js"
			],
			"styles" => [
				"ve-cite/ve.ui.MWReferenceContextItem.css",
				"ve-cite/ve.ui.MWReferenceGroupInputWidget.css",
				"ve-cite/ve.ui.MWReferenceResultWidget.css",
				"ve-cite/ve.ui.MWReferenceSearchWidget.css"
			],
			"dependencies" => [
				"oojs-ui.styles.icons-alerts",
				"oojs-ui.styles.icons-interactions",
				"ext.cite.visualEditor.core",
				"ext.cite.visualEditor.data",
				"ext.cite.visualEditor.icons",
				"ext.cite.style",
				"ext.cite.styles",
				"ext.visualEditor.mwtransclusion",
				"ext.visualEditor.mediawiki"
			],
			"messages" => [
				"cite-ve-changedesc-ref-group-both",
				"cite-ve-changedesc-ref-group-from",
				"cite-ve-changedesc-ref-group-to",
				"cite-ve-changedesc-reflist-group-both",
				"cite-ve-changedesc-reflist-group-from",
				"cite-ve-changedesc-reflist-group-to",
				"cite-ve-changedesc-reflist-item-id",
				"cite-ve-changedesc-reflist-responsive-set",
				"cite-ve-changedesc-reflist-responsive-unset",
				"cite-ve-dialog-reference-editing-reused",
				"cite-ve-dialog-reference-options-group-label",
				"cite-ve-dialog-reference-options-group-placeholder",
				"cite-ve-dialog-reference-options-name-label",
				"cite-ve-dialog-reference-options-responsive-label",
				"cite-ve-dialog-reference-options-section",
				"cite-ve-dialog-reference-placeholder",
				"cite-ve-dialog-reference-title",
				"cite-ve-dialog-reference-useexisting-full-label",
				"cite-ve-dialog-reference-useexisting-label",
				"cite-ve-dialog-reference-useexisting-tool",
				"cite-ve-dialog-referenceslist-contextitem-description-general",
				"cite-ve-dialog-referenceslist-contextitem-description-named",
				"cite-ve-dialog-referenceslist-title",
				"cite-ve-dialogbutton-citation-educationpopup-title",
				"cite-ve-dialogbutton-citation-educationpopup-text",
				"cite-ve-dialogbutton-reference-full-label",
				"cite-ve-dialogbutton-reference-tooltip",
				"cite-ve-dialogbutton-reference-title",
				"cite-ve-dialogbutton-referenceslist-tooltip",
				"cite-ve-reference-input-placeholder",
				"cite-ve-toolbar-group-label",
				"cite-ve-othergroup-item"
			],
			"targets" => [
				"desktop",
				"mobile"
			]
		] );
		return true;
	}

	/**
	 * Callback for LinksUpdate hook
	 * Post-output processing of references property, for proper db storage
	 * Deferred to avoid performance overhead when outputting the page
	 *
	 * @param LinksUpdate &$linksUpdate
	 */
	public static function onLinksUpdate( LinksUpdate &$linksUpdate ) {
		global $wgCiteStoreReferencesData, $wgCiteCacheRawReferencesOnParse;
		if ( !$wgCiteStoreReferencesData ) {
			return;
		}
		$refData = $linksUpdate->getParserOutput()->getExtensionData( Cite::EXT_DATA_KEY );
		if ( $refData === null ) {
			return;
		}
		if ( $wgCiteCacheRawReferencesOnParse ) {
			// caching
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$articleID = $linksUpdate->getTitle()->getArticleID();
			$key = $cache->makeKey( Cite::EXT_DATA_KEY, $articleID );
			$cache->set( $key, $refData, Cite::CACHE_DURATION_ONPARSE );
		}
		// JSON encode
		$ppValue = FormatJson::encode( $refData, false, FormatJson::ALL_OK );
		// GZIP encode references data at maximum compression
		$ppValue = gzencode( $ppValue, 9 );
		// split the string in smaller parts that can fit into a db blob
		$ppValues = str_split( $ppValue, Cite::MAX_STORAGE_LENGTH );
		foreach ( $ppValues as $num => $ppValue ) {
			$key = 'references-' . intval( $num + 1 );
			$linksUpdate->mProperties[$key] = $ppValue;
		}
		$linksUpdate->getParserOutput()->setExtensionData( Cite::EXT_DATA_KEY, null );
	}

	/**
	 * Callback for LinksUpdateComplete hook
	 * If $wgCiteCacheRawReferencesOnParse is set to false, purges the cache
	 * when references are modified
	 *
	 * @param LinksUpdate &$linksUpdate
	 */
	public static function onLinksUpdateComplete( LinksUpdate &$linksUpdate ) {
		global $wgCiteStoreReferencesData, $wgCiteCacheRawReferencesOnParse;
		if ( !$wgCiteStoreReferencesData || $wgCiteCacheRawReferencesOnParse ) {
			return;
		}
		// if we can, avoid clearing the cache when references were not changed
		if ( method_exists( $linksUpdate, 'getAddedProperties' )
			&& method_exists( $linksUpdate, 'getRemovedProperties' )
		) {
			$addedProps = $linksUpdate->getAddedProperties();
			$removedProps = $linksUpdate->getRemovedProperties();
			if ( !isset( $addedProps['references-1'] )
				&& !isset( $removedProps['references-1'] )
			) {
				return;
			}
		}
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$articleID = $linksUpdate->getTitle()->getArticleID();
		$key = $cache->makeKey( Cite::EXT_DATA_KEY, $articleID );
		// delete with reduced hold off period (LinksUpdate uses a master connection)
		$cache->delete( $key, WANObjectCache::MAX_COMMIT_DELAY );
	}

	/**
	 * Adds extra variables to the global config
	 * @param array &$vars
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'cite' );
		$vars['wgCiteVisualEditorOtherGroup'] = $config->get( 'CiteVisualEditorOtherGroup' );
		$vars['wgCiteResponsiveReferences'] = $config->get( 'CiteResponsiveReferences' );
		return true;
	}

	/**
	 * Hook: APIQuerySiteInfoGeneralInfo
	 *
	 * Expose configs via action=query&meta=siteinfo
	 *
	 * @param ApiQuerySiteInfo $api
	 * @param array &$data
	 */
	public static function onAPIQuerySiteInfoGeneralInfo( ApiQuerySiteInfo $api, array &$data ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'cite' );
		$data['citeresponsivereferences'] = $config->get( 'CiteResponsiveReferences' );
	}

}
