<?php

use MediaWiki\MediaWikiServices;

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . '/maintenance/Maintenance.php'
	: __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * The templates will be created with a default content, but can be customized.
 * If the templates already exists, they will be left untouched.
 *
 * @ingroup Maintenance
 */
class FlowCreateTemplates extends LoggedUpdateMaintenance {
	/**
	 * Returns an array of templates to be created (= pages in NS_TEMPLATE)
	 *
	 * The key in the array is an i18n message so the template titles can be
	 * internationalized and/or edited per wiki.
	 * The value is a callback function that will only receive $title and is
	 * expected to return the page content in wikitext.
	 *
	 * @return array [title i18n key => content callback]
	 */
	protected function getTemplates() {
		return [
			// Template:FlowMention, used to render mentions in Flow's Visual Editor
			'flow-ve-mention-template-title' => function ( Title $title ) {
				// get "User:" namespace prefix in wiki language
				$namespaces = MediaWikiServices::getInstance()->getContentLanguage()
					->getFormattedNamespaces();

				return '@[[' . $namespaces[NS_USER] . ':{{{1|Example}}}|{{{2|{{{1|Example}}}}}}]]';
			},
			// LiquidThread import templates
			'flow-importer-lqt-moved-thread-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-lqt-moved-thread-template-content' )->inContentLanguage()->plain();
			},
			'flow-importer-lqt-converted-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-lqt-converted-template-content' )->inContentLanguage()->plain();
			},
			'flow-importer-lqt-converted-archive-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-lqt-converted-archive-template-content' )->inContentLanguage()->plain();
			},
			'flow-importer-lqt-suppressed-user-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-lqt-suppressed-user-template-content' )->inContentLanguage()->plain();
			},
			'flow-importer-lqt-different-author-signature-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-lqt-different-author-signature-template-content' )->inContentLanguage()->plain();
			},
			// Wikitext import templates
			'flow-importer-wt-converted-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-wt-converted-template-content' )->inContentLanguage()->plain();
			},
			'flow-importer-wt-converted-archive-template' => function ( Title $title ) {
				return wfMessage( 'flow-importer-wt-converted-archive-template-content' )->inContentLanguage()->plain();
			},
		];
	}

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Creates templates required by Flow" );

		$this->requireExtension( 'Flow' );
	}

	protected function getUpdateKey() {
		$templates = $this->getTemplates();
		$keys = array_keys( $templates );
		sort( $keys );

		// make the updatekey unique for the i18n keys of the pages to be created
		// so we can easily skip this update if there are no changes
		return __CLASS__ . ':' . md5( implode( ',', $keys ) );
	}

	protected function doDBUpdates() {
		$status = Status::newGood();

		$templates = $this->getTemplates();
		foreach ( $templates as $key => $callback ) {
			$title = Title::newFromText( wfMessage( $key )->inContentLanguage()->plain(), NS_TEMPLATE );
			$content = new WikitextContent( $callback( $title ) );

			$status->merge( $this->create( $title, $content ) );
		}

		return $status->isOK();
	}

	/**
	 * Creates a page with the given content (unless it already exists)
	 *
	 * @param Title $title
	 * @param WikitextContent $content
	 * @return Status
	 * @throws MWException
	 */
	protected function create( Title $title, WikitextContent $content ) {
		$page = WikiPage::factory( $title );

		if ( $page->getRevisionRecord() !== null ) {
			// template already exists, don't overwrite it
			return Status::newGood();
		}

		return $page->doEditContent(
			$content,
			'/* Automatically created by Flow */',
			EDIT_FORCE_BOT | EDIT_SUPPRESS_RC,
			false,
			Flow\Hooks::getOccupationController()->getTalkpageManager()
		);
	}
}

$maintClass = FlowCreateTemplates::class;
require_once RUN_MAINTENANCE_IF_MAIN;
