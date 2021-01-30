<?php

namespace Flow\Specials;

use ExtensionRegistry;
use Flow\Container;
use Flow\Import\Converter;
use Flow\Import\EnableFlow\EnableFlowWikitextConversionStrategy;
use Flow\Import\SourceStore\NullImportSourceStore;
use FormSpecialPage;
use MediaWiki\MediaWikiServices;
use Status;
use Title;

/**
 * A special page that allows users with the flow-create-board right to create
 * boards where there no page exists
 */
class SpecialEnableStructuredDiscussions extends FormSpecialPage {
	/**
	 * @var \Flow\WorkflowLoaderFactory
	 */
	protected $loaderFactory;

	/**
	 * @var \Flow\TalkpageManager
	 */
	protected $occupationController;

	/**
	 * @var string $page Full page name that was converted to a board
	 */
	protected $page = '';

	public function __construct() {
		parent::__construct( 'EnableStructuredDiscussions', 'flow-create-board' );

		$this->loaderFactory = Container::get( 'factory.loader.workflow' );
		$this->occupationController = Container::get( 'occupation_controller' );
	}

	public function execute( $par ) {
		$this->addHelpLink( 'Help:Structured Discussions/Activation' );

		if ( $par !== null ) {
			$title = Title::newFromText( $par );
			if ( $title ) {
				$this->page = $title->getPrefixedText();
			}
		}

		parent::execute( $par );
	}

	protected function getFormFields() {
		return [
			'page' => [
				'type' => 'text',
				'label-message' => 'flow-special-enableflow-page',
				'default' => $this->page,
			],
			'header' => [
				'type' => 'textarea',
				'label-message' => 'flow-special-enableflow-header'
			],
		];
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}

	protected function getMessagePrefix() {
		return 'flow-special-enableflow';
	}

	/**
	 * Creates a flow board.
	 * Archives any pre-existing wikitext talk page.
	 *
	 * @param array $data Form data
	 * @return Status Status indicating result
	 */
	public function onSubmit( array $data ) {
		$page = $data['page'];
		$title = Title::newFromText( $page );
		if ( !$title ) {
			return Status::newFatal( 'flow-special-enableflow-invalid-title', $page );
		}

		// Canonicalize so the error or confirmation message looks nicer (no underscores).
		$page = $title->getPrefixedText();

		if ( $title->getContentModel() === CONTENT_MODEL_FLOW_BOARD ) {
			return Status::newFatal( 'flow-special-enableflow-board-already-exists', $page );
		}

		$status = Status::newGood();

		if ( $title->exists( Title::GAID_FOR_UPDATE ) ) {
			if ( ExtensionRegistry::getInstance()->isLoaded( 'Liquid Threads' ) && \LqtDispatch::isLqtPage( $title ) ) {
				return Status::newFatal( 'flow-special-enableflow-page-is-liquidthreads', $page );
			}

			$logger = Container::get( 'default_logger' );

			$converter = new Converter(
				wfGetDB( DB_MASTER ),
				Container::get( 'importer' ),
				$logger,
				$this->getUser(),
				new EnableFlowWikitextConversionStrategy(
					MediaWikiServices::getInstance()->getParser(),
					new NullImportSourceStore(),
					$logger,
					$this->getUser(),
					[],
					$data['header']
				)
			);

			try {
				$converter->convert( $title );
			} catch ( \Exception $e ) {
				\MWExceptionHandler::logException( $e );
				$status->fatal( 'flow-error-external', $e->getMessage() );
			}

		} else {
			$creationStatus = $this->occupationController->safeAllowCreation( $title, $this->getUser(), false );
			if ( !$creationStatus->isGood() ) {
				return Status::newFatal( 'flow-special-enableflow-board-creation-not-allowed', $page );
			}

			$loader = $this->loaderFactory->createWorkflowLoader( $title );
			$blocks = $loader->getBlocks();

			$action = 'edit-header';
			$params = [
				'header' => [
					'content' => $data['header'],
					'format' => 'wikitext',
				],
			];

			$blocksToCommit = $loader->handleSubmit(
				$this->getContext(),
				$action,
				$params
			);

			foreach ( $blocks as $block ) {
				if ( $block->hasErrors() ) {
					$errors = $block->getErrors();

					foreach ( $errors as $errorKey ) {
						$status->fatal( $block->getErrorMessage( $errorKey ) );
					}
				}
			}

			if ( $status->isOK() ) {
				$loader->commit( $blocksToCommit );
			}
		}

		$this->page = $data['page'];
		return $status;
	}

	public function onSuccess() {
		$confirmationMessage = $this->msg( 'flow-special-enableflow-confirmation', wfEscapeWikiText( $this->page ) )->parse();
		$this->getOutput()->addHTML( $confirmationMessage );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
