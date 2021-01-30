<?php

namespace Flow\Block;

use Flow\Model\UUID;
use IContextSource;

interface Block {
	/**
	 * @param IContextSource $context
	 * @param string $action
	 */
	public function init( IContextSource $context, $action );

	/**
	 * Perform validation of data model
	 *
	 * @param array $data
	 * @return bool True if data model is valid
	 */
	public function onSubmit( array $data );

	/**
	 * Write updates to storage
	 */
	public function commit();

	/**
	 * Render the API output of this Block.
	 * Templating is provided for convenience
	 *
	 * @param array $options
	 * @return array
	 */
	public function renderApi( array $options );

	/**
	 * @return string Unique name among all blocks on an object
	 */
	public function getName();

	/**
	 * @return UUID
	 */
	public function getWorkflowId();

	/**
	 * Returns an array of all error types encountered in this block. The values
	 * in the returned array can be used to pass to getErrorMessage() or
	 * getErrorExtra() to respectively fetch the specific error message or
	 * additional details.
	 *
	 * @return array
	 */
	public function getErrors();

	/**
	 * Checks if any errors have occurred in the block (no argument), or if a
	 * specific error has occurred (argument being the error type)
	 *
	 * @param string|null $type
	 * @return bool
	 */
	public function hasErrors( $type = null );

	/**
	 * Returns true if the block can render the requested action, or false
	 * otherwise.
	 *
	 * @param string $action
	 * @return bool
	 */
	public function canRender( $action );
}
