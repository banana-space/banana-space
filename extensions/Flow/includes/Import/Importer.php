<?php

namespace Flow\Import;

use Flow\Data\ManagerGroup;
use Flow\DbFactory;
use Flow\Import\Postprocessor\Postprocessor;
use Flow\Import\Postprocessor\ProcessorGroup;
use Flow\Import\SourceStore\SourceStoreInterface;
use Flow\OccupationController;
use Flow\WorkflowLoaderFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplQueue;
use Title;
use User;

/**
 * The import system uses a TalkpageImportOperation class.
 * This class is essentially a factory class that makes the
 * dependency injection less inconvenient for callers.
 */
class Importer {
	/** @var ManagerGroup */
	protected $storage;
	/** @var WorkflowLoaderFactory */
	protected $workflowLoaderFactory;
	/** @var LoggerInterface|null */
	protected $logger;
	/** @var DbFactory */
	protected $dbFactory;
	/** @var bool */
	protected $allowUnknownUsernames;
	/** @var ProcessorGroup */
	protected $postprocessors;
	/** @var SplQueue Callbacks for DeferredUpdate that are queue'd up by the commit process */
	protected $deferredQueue;
	/** @var OccupationController */
	protected $occupationController;

	public function __construct(
		ManagerGroup $storage,
		WorkflowLoaderFactory $workflowLoaderFactory,
		DbFactory $dbFactory,
		SplQueue $deferredQueue,
		OccupationController $occupationController
	) {
		$this->storage = $storage;
		$this->workflowLoaderFactory = $workflowLoaderFactory;
		$this->dbFactory = $dbFactory;
		$this->postprocessors = new ProcessorGroup;
		$this->deferredQueue = $deferredQueue;
		$this->occupationController = $occupationController;
	}

	public function addPostprocessor( Postprocessor $proc ) {
		$this->postprocessors->add( $proc );
	}

	/**
	 * Returns the ProcessorGroup (calling this triggers all the postprocessors
	 *
	 * @return Postprocessor
	 */
	public function getPostprocessor() {
		return $this->postprocessors;
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param bool $allowed When true allow usernames that do not exist on the wiki to be
	 *  stored in the _ip field. *DO*NOT*USE* in any production setting, this is
	 *  to allow for imports from production wiki api's to test machines for
	 *  development purposes.
	 */
	public function setAllowUnknownUsernames( $allowed ) {
		$this->allowUnknownUsernames = (bool)$allowed;
	}

	/**
	 * Imports topics from a data source to a given page.
	 *
	 * @param IImportSource $source
	 * @param Title $targetPage
	 * @param User $user User doing the conversion actions (e.g. initial description,
	 *    wikitext archive edit).  However, actions will be attributed to the original
	 *    user when possible (e.g. the user who did the original LQT reply)
	 * @param SourceStoreInterface $sourceStore
	 * @return bool True When the import completes with no failures
	 */
	public function import(
		IImportSource $source,
		Title $targetPage,
		User $user,
		SourceStoreInterface $sourceStore
	) {
		$operation = new TalkpageImportOperation( $source, $user, $this->occupationController );
		$pageImportState = new PageImportState(
			$this->workflowLoaderFactory
				->createWorkflowLoader( $targetPage )
				->getWorkflow(),
			$this->storage,
			$sourceStore,
			$this->logger ?: new NullLogger,
			$this->dbFactory,
			$this->postprocessors,
			$this->deferredQueue,
			$this->allowUnknownUsernames
		);
		return $operation->import( $pageImportState );
	}
}
