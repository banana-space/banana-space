<?php

namespace Flow;

use Flow\Content\BoardContent;
use Flow\Data\ManagerGroup;
use Flow\Exception\CrossWikiException;
use Flow\Exception\InvalidDataException;
use Flow\Exception\InvalidInputException;
use Flow\Exception\InvalidParameterException;
use Flow\Exception\InvalidTopicUuidException;
use Flow\Exception\UnknownWorkflowIdException;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Title;

class WorkflowLoaderFactory {
	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var BlockFactory
	 */
	protected $blockFactory;

	/**
	 * @var SubmissionHandler
	 */
	protected $submissionHandler;

	/**
	 * @var bool
	 */
	protected $pageMoveInProgress = false;

	/**
	 * @param ManagerGroup $storage
	 * @param BlockFactory $blockFactory
	 * @param SubmissionHandler $submissionHandler
	 */
	public function __construct(
		ManagerGroup $storage,
		BlockFactory $blockFactory,
		SubmissionHandler $submissionHandler
	) {
		$this->storage = $storage;
		$this->blockFactory = $blockFactory;
		$this->submissionHandler = $submissionHandler;
	}

	public function pageMoveInProgress() {
		$this->pageMoveInProgress = true;
	}

	/**
	 * @param Title $pageTitle
	 * @param UUID|null $workflowId
	 * @return WorkflowLoader
	 * @throws InvalidInputException
	 * @throws CrossWikiException
	 */
	public function createWorkflowLoader( Title $pageTitle, $workflowId = null ) {
		if ( $pageTitle->isExternal() ) {
			throw new CrossWikiException( 'Interwiki to ' . $pageTitle->getInterwiki() . ' not implemented ', 'default' );
		}

		if ( $pageTitle->getNamespace() < 0 ) {
			throw new InvalidDataException( 'Can not load workflow for special (< 0) namespace', 'invalid-title' );
		}

		// @todo: ideally, workflowId is always set and this stuff is done in the places that call this
		if ( $workflowId === null ) {
			if ( $pageTitle->getNamespace() === NS_TOPIC ) {
				// topic page: workflow UUID is page title
				$workflowId = self::uuidFromTitle( $pageTitle );
			} else {
				// board page: workflow UUID is inside content model
				$page = \WikiPage::factory( $pageTitle );
				$content = $page->getContent();
				if ( $content instanceof BoardContent ) {
					$workflowId = $content->getWorkflowId();
				}
			}
		}

		if ( $workflowId === null ) {
			// We failed to get a Flow content model for this title,
			// so we'll want to clear LinkCache (we're likely be in
			// the process of creating a new workflow, so we don't
			// want lingering cache data)
			// Workflow::create doesn't GAID_FOR_UPDATE to fetch the
			// article id. Let's forcibly make it look like the title
			// does not yet exist (in which case it will be
			// GAID_FOR_UPDATE when we actually want to store it)
			$title = clone $pageTitle;
			$title->resetArticleID( 0 );

			// no existing workflow found, create new one
			$workflow = Workflow::create( 'discussion', $title );
		} else {
			$workflow = $this->loadWorkflowById( $pageTitle, $workflowId );
		}

		return new WorkflowLoader(
			$workflow,
			$this->blockFactory->createBlocks( $workflow ),
			$this->submissionHandler
		);
	}

	/**
	 * @param Title|false $title
	 * @param UUID $workflowId
	 * @return Workflow
	 * @throws InvalidDataException
	 * @throws UnknownWorkflowIdException
	 */
	public function loadWorkflowById( /* Title or false */ $title, $workflowId ) {
		/** @var Workflow $workflow */
		$workflow = $this->storage->getStorage( 'Workflow' )->get( $workflowId );
		if ( !$workflow ) {
			throw new UnknownWorkflowIdException( 'Invalid workflow requested by id', 'invalid-input' );
		}
		if ( $workflow->getWiki() !== wfWikiID() ) {
			throw new UnknownWorkflowIdException( 'The requested workflow does not exist on this wiki.' );
		}
		if ( $title !== false && $this->pageMoveInProgress === false && !$workflow->matchesTitle( $title ) ) {
			throw new InvalidDataException( 'Flow workflow is for different page', 'different-page' );
		}

		return $workflow;
	}

	/**
	 * Create a UUID for a Title object
	 *
	 * @param Title $title
	 * @return UUID
	 * @throws InvalidInputException When the Title does not represent a valid uuid
	 */
	public static function uuidFromTitle( Title $title ) {
		return self::uuidFromTitlePair( $title->getNamespace(), $title->getDBkey() );
	}

	/**
	 * Create a UUID for a ns/dbkey title pair
	 *
	 * @param int $ns
	 * @param string $dbKey
	 * @return UUID
	 * @throws InvalidInputException When the pair does not represent a valid uuid
	 */
	public static function uuidFromTitlePair( $ns, $dbKey ) {
		if ( $ns !== NS_TOPIC ) {
			throw new InvalidParameterException( "Title is not from NS_TOPIC: $ns" );
		}

		try {
			return UUID::create( strtolower( $dbKey ) );
		} catch ( InvalidInputException $e ) {
			throw new InvalidTopicUuidException( "$dbKey is not a valid UUID" );
		}
	}
}
