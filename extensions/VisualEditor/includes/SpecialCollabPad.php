<?php
/**
 * CollabPad special page
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

class SpecialCollabPad extends SpecialPage {
	private $prefixes = [];

	/**
	 * @var null|Title
	 */
	private $title = null;

	/**
	 * @var null|ParserOutput
	 */
	private $output = null;

	public function __construct() {
		parent::__construct( 'CollabPad' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		global $wgVisualEditorRebaserURL;
		return (bool)$wgVisualEditorRebaserURL && parent::userCanExecute( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function isListed() {
		global $wgVisualEditorRebaserURL;
		return (bool)$wgVisualEditorRebaserURL;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();

		$output = $this->getOutput();

		$output->addJsConfigVars( 'collabPadPageName', $subPage );
		$output->addModuleStyles( 'ext.visualEditor.collabTarget.init.styles' );
		$output->addModuleStyles( 'oojs-ui.styles.icons-editing-core' );
		$output->addModuleStyles( 'oojs-ui.styles.icons-content' );

		$output->addModules( 'ext.visualEditor.collabTarget.init' );

		$output->enableOOUI();

		$documentNameFieldset = new OOUI\FieldsetLayout( [
			'label' => $this->msg( 'visualeditor-rebase-client-document-create-edit' )->text(),
			'icon' => 'edit',
			'items' => [
				new OOUI\ActionFieldLayout(
					new OOUI\TextInputWidget( [
						'classes' => [ 've-init-mw-collabTarget-nameInput' ],
						'placeholder' => $this->msg( 'visualeditor-rebase-client-document-name' )->text(),
						'autofocus' => true,
						'infusable' => true
					] ),
					new OOUI\ButtonWidget( [
						'classes' => [ 've-init-mw-collabTarget-nameButton' ],
						'label' => $this->msg( 'visualeditor-rebase-client-document-create-edit' )->text(),
						'flags' => [ 'primary', 'progressive' ],
						// Only enable once JS has loaded
						'disabled' => true,
						'infusable' => true
					] ),
					[
						'align' => 'top',
						'classes' => [ 've-init-mw-collabTarget-nameField' ],
						'infusable' => true
					]
				)
			]
		] );
		$importFieldset = new OOUI\FieldsetLayout( [
			'label' => $this->msg( 'visualeditor-rebase-client-import' )->text(),
			'icon' => 'download',
			'items' => [
				new OOUI\ActionFieldLayout(
					new MediaWiki\Widget\TitleInputWidget( [
						'classes' => [ 've-init-mw-collabTarget-importInput' ],
						'placeholder' => $this->msg( 'visualeditor-rebase-client-import-name' )->text(),
						'infusable' => true,
					] ),
					new OOUI\ButtonWidget( [
						'classes' => [ 've-init-mw-collabTarget-importButton' ],
						'label' => $this->msg( 'visualeditor-rebase-client-import' )->text(),
						// Only enable once JS has loaded
						'disabled' => true,
						'infusable' => true
					] ),
					[
						'align' => 'top',
						'classes' => [ 've-init-mw-collabTarget-importField' ],
						'infusable' => true
					]
				)
			]
		] );

		$form = new OOUI\FormLayout( [
			'classes' => [ 've-init-mw-collabTarget-form' ],
			'items' => [
				$documentNameFieldset,
				$importFieldset
			],
			'infusable' => true
		] );

		$progressBar = new OOUI\ProgressBarWidget( [
			'classes' => [ 've-init-mw-collabTarget-loading' ],
			'infusable' => true
		] );

		if ( $subPage ) {
			$title = Title::newFromText( $subPage );
			$output->setPageTitle( $this->msg( 'collabpad-doctitle', $title->getPrefixedText() ) );
			$form->addClasses( [ 'oo-ui-element-hidden' ] );
		} else {
			// Scripts only, styles already added above
			$output->addModules( 'ext.visualEditor.collabTarget' );
			$progressBar->addClasses( [ 'oo-ui-element-hidden' ] );
		}
		$output->addHTML( $progressBar . $form );
	}

	/**
	 * Get the sub page from the current title
	 *
	 * @param Title $title Full title
	 * @return null|Title Sub page title
	 */
	public static function getSubPage( Title $title ) {
		preg_match( '`^[^/]+/(.*)`', $title->getPrefixedText(), $matches );
		return count( $matches ) ? Title::newFromText( $matches[ 1 ] ) : null;
	}

	/**
	 * @param SkinTemplate &$skin The skin template on which the UI is built.
	 * @param array &$links Navigation links.
	 * @return bool Always true.
	 */
	public static function onSkinTemplateNavigationSpecialPage( SkinTemplate &$skin, array &$links ) {
		$title = $skin->getTitle();
		if ( $title && $title->isSpecial( 'CollabPad' ) ) {
			$subPage = self::getSubPage( $title );
			$links['namespaces']['special']['text'] = $skin->msg( 'collabpad' )->text();
			if ( $subPage ) {
				$links['namespaces']['special']['href'] =
					Title::newFromText( 'Special:CollabPad' )->getLocalURL();
				$links['namespaces']['special']['class'] = '';

				$links['namespaces']['pad']['text'] = $subPage->getPrefixedText();
				$links['namespaces']['pad']['href'] = '';
				$links['namespaces']['pad']['class'] = 'selected';
			}
		}
		return true;
	}
}
