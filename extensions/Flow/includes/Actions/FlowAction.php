<?php

namespace Flow\Actions;

use Action;
use Article;
use ErrorPageError;
use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\View;
use Flow\WorkflowLoaderFactory;
use IContextSource;
use OutputPage;
use Page;
use Title;
use WebRequest;

class FlowAction extends Action {
	/**
	 * @var string
	 */
	protected $actionName;

	/**
	 * @param Article|Page $page
	 * @param IContextSource $source
	 * @param string $actionName
	 */
	public function __construct( Page $page, IContextSource $source, $actionName ) {
		parent::__construct( $page, $source );
		$this->actionName = $actionName;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->actionName;
	}

	public function show() {
		$this->showForAction( $this->getName() );
	}

	/**
	 * @param string $action
	 * @param OutputPage|null $output
	 * @throws ErrorPageError
	 * @throws FlowException
	 */
	public function showForAction( $action, OutputPage $output = null ) {
		$container = Container::getContainer();

		if ( $output === null ) {
			$output = $this->context->getOutput();
		}

		$title = $this->getTitle();

		$titleContentModel = $title->getContentModel();
		if ( $titleContentModel !== CONTENT_MODEL_FLOW_BOARD ) {
			// If we make it to this method, something thinks it's Flow.
			// However, if we get here the Title class thinks otherwise.

			// This may mean it is a non-Flow page in a Flow namespace, if
			// page_content_model is populated but rev_content_model is not.

			throw new ErrorPageError( 'nosuchaction', 'flow-action-wrong-title-content-model', [ $titleContentModel ] );
		}

		// @todo much of this seems to duplicate BoardContent::getParserOutput
		$view = new View(
			$container['url_generator'],
			$container['lightncandy'],
			$output,
			$container['flow_actions']
		);

		$request = $this->context->getRequest();

		// BC for urls pre july 2014 with workflow query parameter
		$redirect = $this->getRedirectUrl( $request, $title );
		if ( $redirect ) {
			$output->redirect( $redirect );
			return;
		}

		$action = $request->getVal( 'action', 'view' );
		try {
			/** @var WorkflowLoaderFactory $factory */
			$factory = $container['factory.loader.workflow'];
			$loader = $factory->createWorkflowLoader( $title );

			if ( $title->getNamespace() === NS_TOPIC && $loader->getWorkflow()->getType() !== 'topic' ) {
				// @todo better error handling
				throw new FlowException( 'Invalid title: uuid is not a topic' );
			}

			$view->show( $loader, $action );
		} catch ( FlowException $e ) {
			$e->setOutput( $output );
			throw $e;
		}
	}

	/**
	 * Flow used to output some permalink urls with workflow ids in them. Each
	 * workflow now has its own page, so those have been deprecated. This checks
	 * a web request for the old workflow parameter and returns a url to redirect
	 * to if necessary.
	 *
	 * @param WebRequest $request
	 * @param Title $title
	 * @return string URL to redirect to or blank string for no redirect
	 */
	protected function getRedirectUrl( WebRequest $request, Title $title ) {
		$workflowId = UUID::create( strtolower( $request->getVal( 'workflow' ) ) ?: null );
		if ( !$workflowId ) {
			return '';
		}

		/** @var ManagerGroup $storage */
		$storage = Container::get( 'storage' );
		/** @var Workflow $workflow */
		$workflow = $storage->get( 'Workflow', $workflowId );

		// The uuid points to a non-existant workflow
		if ( !$workflow ) {
			return '';
		}

		// The uuid points to the current page
		$redirTitle = $workflow->getArticleTitle();
		if ( $redirTitle->equals( $title ) ) {
			return '';
		}

		// We need to redirect
		return $redirTitle->getLinkURL(
			array_diff_key( $request->getValues(), [ 'title' => '', 'workflow' => '' ] )
		);
	}
}
