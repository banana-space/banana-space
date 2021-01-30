<?php

namespace Flow\Block;

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Exception\InvalidInputException;
use Flow\FlowActions;
use Flow\Model\AbstractRevision;
use Flow\Model\Workflow;
use Flow\RevisionActionPermissions;
use IContextSource;

abstract class AbstractBlock implements Block {

	/** @var Workflow */
	protected $workflow;
	/** @var ManagerGroup */
	protected $storage;

	/** @var IContextSource */
	protected $context;
	/** @var array|null */
	protected $submitted = null;
	/** @var array */
	protected $errors = [];

	/**
	 * @var string|null The commitable action being submitted, or null
	 *  for read-only actions.
	 */
	protected $action;

	/** @var RevisionActionPermissions */
	protected $permissions;

	/**
	 * A list of supported post actions
	 * @var array
	 */
	protected $supportedPostActions = [];

	/**
	 * A list of supported get actions
	 * @var array
	 */
	protected $supportedGetActions = [];

	/**
	 * Templates for each view actions
	 * @var array
	 */
	protected $templates = [];

	public function __construct( Workflow $workflow, ManagerGroup $storage ) {
		$this->workflow = $workflow;
		$this->storage = $storage;
	}

	/**
	 * Called by $this->onSubmit to populate $this->errors based
	 * on $this->action and $this->submitted.
	 */
	abstract protected function validate();

	/**
	 * @inheritDoc
	 */
	abstract public function commit();

	/**
	 * @param IContextSource $context
	 * @param string $action
	 */
	public function init( IContextSource $context, $action ) {
		$this->context = $context;
		$this->action = $action;
		$this->permissions = new RevisionActionPermissions( Container::get( 'flow_actions' ), $context->getUser() );
	}

	/**
	 * @return IContextSource
	 */
	public function getContext() {
		return $this->context;
	}

	/**
	 * Returns true if the block can submit the requested action, or false
	 * otherwise.
	 *
	 * @param string $action
	 * @return bool
	 */
	public function canSubmit( $action ) {
		return in_array( $this->getActionName( $action ), $this->supportedPostActions );
	}

	/**
	 * Returns true if the block can render the requested action, or false
	 * otherwise.
	 *
	 * @param string $action
	 * @return bool
	 */
	public function canRender( $action ) {
		return // GET actions can be rendered
			in_array( $this->getActionName( $action ), $this->supportedGetActions ) ||
			// POST actions are usually redirected to 'view' after successfully
			// completing the request, but can also be rendered (e.g. to show
			// error message after unsuccessful submission)
			$this->canSubmit( $action );
	}

	/**
	 * Get the template name for a specific action or an array of template
	 * for all possible view actions in this block
	 *
	 * @param string|null $action
	 * @return string|array
	 * @throws InvalidInputException
	 */
	public function getTemplate( $action = null ) {
		if ( $action === null ) {
			return $this->templates;
		}
		if ( !isset( $this->templates[$action] ) ) {
			throw new InvalidInputException( 'Template is not defined for action: ' . $action, 'invalid-input' );
		}
		return $this->templates[$action];
	}

	/**
	 * @param array $data
	 * @return bool|null true when accepted, false when not accepted.
	 *  null when this action does not support submission.
	 */
	public function onSubmit( array $data ) {
		if ( !$this->canSubmit( $this->action ) ) {
			return null;
		}

		$this->submitted = $data;
		$this->validate();

		return !$this->hasErrors();
	}

	public function wasSubmitted() {
		return $this->submitted !== null;
	}

	/**
	 * Checks if any errors have occurred in the block (no argument), or if a
	 * specific error has occurred (argument being the error type)
	 *
	 * @param string|null $type
	 * @return bool
	 */
	public function hasErrors( $type = null ) {
		if ( $type === null ) {
			return (bool)$this->errors;
		}
		return isset( $this->errors[$type] );
	}

	/**
	 * Returns an array of all error types encountered in this block. The values
	 * in the returned array can be used to pass to getErrorMessage() or
	 * getErrorExtra() to respectively fetch the specific error message or
	 * additional details.
	 *
	 * @return array
	 */
	public function getErrors() {
		return array_keys( $this->errors );
	}

	/**
	 * @param string $type
	 * @return \Message
	 */
	public function getErrorMessage( $type ) {
		return $this->errors[$type]['message'] ?? null;
	}

	/**
	 * @param string $type
	 * @return mixed
	 */
	public function getErrorExtra( $type ) {
		return $this->errors[$type]['extra'] ?? null;
	}

	/**
	 * @param string $type
	 * @param \Message $message
	 * @param mixed|null $extra
	 */
	public function addError( $type, \Message $message, $extra = null ) {
		$this->errors[$type] = [
			'message' => $message,
			'extra' => $extra,
		];
	}

	public function getWorkflow() {
		return $this->workflow;
	}

	public function getWorkflowId() {
		return $this->workflow->getId();
	}

	public function getStorage() {
		return $this->storage;
	}

	/**
	 * Given a certain action name, this returns the valid action name. This is
	 * meant for BC compatibility with renamed actions.
	 *
	 * @param string $action
	 * @return string
	 */
	public function getActionName( $action ) {
		// BC for renamed actions
		/** @var FlowActions $actions */
		$actions = Container::get( 'flow_actions' );
		$alias = $actions->getValue( $action );
		if ( is_string( $alias ) ) {
			// All proper actions return arrays, but aliases return a string
			$action = $alias;
		}

		return $action;
	}

	/**
	 * Run through AbuseFilter and friends.
	 * @todo Having to call spamFilter in each place that creates a revision
	 *  is error-prone.
	 *
	 * @param AbstractRevision|null $old null when $new is first revision
	 * @param AbstractRevision $new
	 * @return bool True when content is allowed by spam filter
	 */
	protected function checkSpamFilters( ?AbstractRevision $old, AbstractRevision $new ) {
		/** @var \Flow\SpamFilter\Controller $spamFilter */
		$spamFilter = Container::get( 'controller.spamfilter' );
		$status = $spamFilter->validate( $this->context, $new, $old, $this->workflow->getArticleTitle(), $this->workflow->getOwnerTitle() );
		if ( $status->isOK() ) {
			return true;
		}

		$message = $status->getMessage();

		$details = $status->getValue();

		$this->addError( 'spamfilter', $message, [
			'messageKey' => $message->getKey(),
			'details' => $details,
		] );
		return false;
	}

	/**
	 * @return string The new edit token
	 */
	public function getEditToken() {
		return $this->context->getUser()->getEditToken();
	}

	/**
	 * @param \OutputPage $out
	 */
	public function setPageTitle( \OutputPage $out ) {
		if ( $out->getPageTitle() ) {
			// Don't override page title if another block has already set it.
			// If this should *really* be done, the specific block extending
			// this AbstractBlock should just implement this itself ;)
			return;
		}

		$out->setPageTitle( $this->workflow->getArticleTitle()->getFullText() );
	}
}
