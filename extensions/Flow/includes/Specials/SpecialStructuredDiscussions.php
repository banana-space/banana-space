<?php

/**
 * A special page that redirects to a workflow or PostRevision given a UUID
 */

namespace Flow\Specials;

use Flow\Container;
use Flow\Data\ObjectManager;
use Flow\Exception\FlowException;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Repository\TreeRepository;
use FormSpecialPage;
use HTMLForm;
use Status;

class SpecialStructuredDiscussions extends FormSpecialPage {

	/**
	 * The type of content, e.g. 'post', 'workflow'
	 * @var string
	 */
	protected $type;

	/**
	 * Flow UUID
	 * @var string
	 */
	protected $uuid;

	public function __construct() {
		parent::__construct( 'StructuredDiscussions' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		parent::execute( $par );
		$this->addHelplink( 'Help:Structured_Discussions' );
	}

	/**
	 * Initialize $this->type and $this-uuid using the subpage string.
	 * @param string $par
	 */
	protected function setParameter( $par ) {
		$tokens = explode( '/', $par, 2 );
		$this->type = $tokens[0];
		if ( count( $tokens ) > 1 ) {
			$this->uuid = $tokens[1];
		}
	}

	/**
	 * Get the mapping between display text and value for the type dropdown.
	 * @return array
	 */
	protected function getTypes() {
		$mapping = [
			'flow-special-type-post' => 'post',
			'flow-special-type-workflow' => 'workflow',
		];

		$types = [];
		foreach ( $mapping as $msgKey => $option ) {
			$types[$this->msg( $msgKey )->escaped()] = $option;
		}
		return $types;
	}

	protected function getFormFields() {
		return [
			'type' => [
				'id' => 'mw-flow-special-type',
				'name' => 'type',
				'type' => 'select',
				'label-message' => 'flow-special-type',
				'options' => $this->getTypes(),
				'default' => empty( $this->type ) ? 'post' : $this->type,
			],
			'uuid' => [
				'id' => 'mw-flow-special-uuid',
				'name' => 'uuid',
				'type' => 'text',
				'label-message' => 'flow-special-uuid',
				'default' => $this->uuid,
			],
		];
	}

	/**
	 * Description shown at the top of the page
	 * @return string
	 */
	protected function preText() {
		return '<p>' . $this->msg( 'flow-special-desc' )->escaped() . '</p>';
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setMethod( 'get' ); // This also submits the form every time the page loads.
	}

	/**
	 * @return string
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * Get the URL of a UUID for a PostRevision.
	 * @return string|null
	 */
	protected function getPostUrl() {
		try {
			$postId = UUID::create( $this->uuid );
			/** @var TreeRepository $treeRepo */
			$treeRepo = Container::get( 'repository.tree' );
			$rootId = $treeRepo->findRoot( $postId );
			/** @var ObjectManager $om */
			$om = Container::get( 'storage.workflow' );
			$workflow = $om->get( $rootId );
			if ( $workflow instanceof Workflow ) {
				/** @var UrlGenerator $urlGenerator */
				$urlGenerator = Container::get( 'url_generator' );
				return $urlGenerator->postLink(
					null,
					$rootId,
					$postId
				)->getFullUrl();
			} else {
				return null;
			}
		} catch ( FlowException $e ) {
			return null; // The UUID is invalid or has no root post.
		}
	}

	/**
	 * Get the URL of a UUID for a workflow.
	 * @return string|null
	 */
	protected function getWorkflowUrl() {
		try {
			$rootId = UUID::create( $this->uuid );
			/** @var ObjectManager $om */
			$om = Container::get( 'storage.workflow' );
			$workflow = $om->get( $rootId );
			if ( $workflow instanceof Workflow ) {
				/** @var UrlGenerator $urlGenerator */
				$urlGenerator = Container::get( 'url_generator' );
				return $urlGenerator->workflowLink(
					null,
					$rootId
				)->getFullUrl();
			} else {
				return null;
			}
		} catch ( FlowException $e ) {
			return null; // The UUID is invalid or has no root post.
		}
	}

	/**
	 * Set redirect and return true if $data['uuid'] or $this->par exists and is
	 * a valid UUID; otherwise return false or a Status object encapsulating any
	 * error, which causes the form to be shown.
	 * @param array $data
	 * @return bool|Status
	 */
	public function onSubmit( array $data ) {
		if ( !empty( $data['type'] ) && !empty( $data['uuid'] ) ) {
			$this->setParameter( $data['type'] . '/' . $data['uuid'] );
		}

		// Assume no data has been passed in if there is no UUID.
		if ( empty( $this->uuid ) ) {
			return false; // Display the form.
		}

		switch ( $this->type ) {
			case 'post':
				$url = $this->getPostUrl();
				break;
			case 'workflow':
				$url = $this->getWorkflowUrl();
				break;
			default:
				$url = null;
				break;
		}

		if ( $url ) {
			$this->getOutput()->redirect( $url );
			return true;
		} else {
			$this->getOutput()->setStatusCode( 404 );
			return Status::newFatal( 'flow-special-invalid-uuid' );
		}
	}

	protected function getGroupName() {
		return 'redirects';
	}
}
