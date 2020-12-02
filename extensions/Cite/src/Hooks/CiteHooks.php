<?php
/**
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

namespace Cite\Hooks;

use ApiQuerySiteinfo;
use ExtensionRegistry;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use ResourceLoader;
use Title;

class CiteHooks {

	/**
	 * Convert the content model of a message that is actually JSON to JSON. This
	 * only affects validation and UI when saving and editing, not loading the
	 * content.
	 *
	 * @param Title $title
	 * @param string &$model
	 */
	public static function onContentHandlerDefaultModelFor( LinkTarget $title, &$model ) {
		if (
			$title->inNamespace( NS_MEDIAWIKI ) &&
			(
				$title->getText() == 'Visualeditor-cite-tool-definition.json' ||
				$title->getText() == 'Cite-tool-definition.json'
			)
		) {
			$model = CONTENT_MODEL_JSON;
		}
	}

	/**
	 * Conditionally register resource loader modules that depend on
	 * other MediaWiki extensions.
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		$uxEnhancementsModule = [
			'localBasePath' => __DIR__ . '/../../modules',
			'remoteExtPath' => 'Cite/modules',
			'scripts' => [
				'ext.cite.a11y.js',
				'ext.cite.highlighting.js',
			],
			'styles' => [
				'ext.cite.a11y.css',
				'ext.cite.highlighting.css',
			],
			'messages' => [
				'cite_reference_link_prefix',
				'cite_references_link_accessibility_label',
				'cite_references_link_many_accessibility_label',
				'cite_references_link_accessibility_back_label',
			],
		];
		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			// Temporary tracking for T231529
			$uxEnhancementsModule['scripts'][] = 'ext.cite.tracking.js';
			$uxEnhancementsModule['dependencies'][] = 'ext.eventLogging';
		}
		$resourceLoader->register( 'ext.cite.ux-enhancements', $uxEnhancementsModule );

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'VisualEditor' ) ) {
			return;
		}

		$resourceLoader->register( "ext.cite.visualEditor.core", [
			'localBasePath' => __DIR__ . '/../../modules/ve-cite',
			'remoteExtPath' => 'Cite/modules/ve-cite',
			"scripts" => [
				've.dm.MWReferenceModel.js',
				've.dm.MWReferencesListNode.js',
				've.dm.MWReferenceNode.js',
				've.ce.MWReferencesListNode.js',
				've.ce.MWReferenceNode.js',
				've.ui.MWReferencesListCommand.js',
			],
			"styles" => [
				've.ce.MWReferencesListNode.css',
				've.ce.MWReferenceNode.css',
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
			[ 'class' => 'Cite\\ResourceLoader\\CiteDataModule' ] );

		$resourceLoader->register( "ext.cite.visualEditor", [
			'localBasePath' => __DIR__ . '/../../modules/ve-cite',
			'remoteExtPath' => 'Cite/modules/ve-cite',
			"scripts" => [
				've.ui.MWReferenceGroupInputWidget.js',
				've.ui.MWReferenceSearchWidget.js',
				've.ui.MWReferenceResultWidget.js',
				've.ui.MWUseExistingReferenceCommand.js',
				've.ui.MWCitationDialog.js',
				've.ui.MWReferencesListDialog.js',
				've.ui.MWReferenceDialog.js',
				've.ui.MWReferenceDialogTool.js',
				've.ui.MWCitationDialogTool.js',
				've.ui.MWReferenceContextItem.js',
				've.ui.MWReferencesListContextItem.js',
				've.ui.MWCitationContextItem.js',
				've.ui.MWCitationAction.js',
				've.ui.MWReference.init.js',
				've.ui.MWCitationNeededContextItem.js',
			],
			"styles" => [
				've.ui.MWReferenceDialog.css',
				've.ui.MWReferenceContextItem.css',
				've.ui.MWReferenceGroupInputWidget.css',
				've.ui.MWReferenceResultWidget.css',
				've.ui.MWReferenceSearchWidget.css',
			],
			"dependencies" => [
				"oojs-ui.styles.icons-alerts",
				"oojs-ui.styles.icons-editing-citation",
				"oojs-ui.styles.icons-interactions",
				"ext.cite.visualEditor.core",
				"ext.cite.visualEditor.data",
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
				"cite-ve-citationneeded-button",
				"cite-ve-citationneeded-description",
				"cite-ve-citationneeded-title",
				"cite-ve-dialog-reference-editing-reused",
				"cite-ve-dialog-reference-editing-reused-long",
				"cite-ve-dialog-reference-options-group-label",
				"cite-ve-dialog-reference-options-group-placeholder",
				"cite-ve-dialog-reference-options-name-label",
				"cite-ve-dialog-reference-options-responsive-label",
				"cite-ve-dialog-reference-options-section",
				"cite-ve-dialog-reference-placeholder",
				"cite-ve-dialog-reference-title",
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
	}

	/**
	 * Adds extra variables to the global config
	 * @param array &$vars
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'cite' );
		$vars['wgCiteVisualEditorOtherGroup'] = $config->get( 'CiteVisualEditorOtherGroup' );
		$vars['wgCiteResponsiveReferences'] = $config->get( 'CiteResponsiveReferences' );
	}

	/**
	 * Hook: APIQuerySiteInfoGeneralInfo
	 *
	 * Expose configs via action=query&meta=siteinfo
	 *
	 * @param ApiQuerySiteinfo $api
	 * @param array &$data
	 */
	public static function onAPIQuerySiteInfoGeneralInfo( $api, array &$data ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'cite' );
		$data['citeresponsivereferences'] = $config->get( 'CiteResponsiveReferences' );
	}

}
