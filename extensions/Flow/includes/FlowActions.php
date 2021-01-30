<?php

namespace Flow;

use Flow\Data\Utils\MultiDimArray;

class FlowActions {
	/**
	 * @var MultiDimArray
	 */
	protected $actions;

	/**
	 * @param array $actions
	 */
	public function __construct( array $actions ) {
		$this->actions = new MultiDimArray();
		$this->actions[] = $actions;
	}

	/**
	 * @return string[]
	 */
	public function getActions() {
		return array_keys( $this->actions->all() );
	}

	/**
	 * Function can be overloaded depending on how deep the desired value is.
	 *
	 * @param string $action
	 * @param string $type
	 * @return bool True when the requested parameter exists and is not null
	 */
	public function hasValue( $action, $type = null /* [, $option = null [, ...]] */ ) {
		$arguments = func_get_args();
		try {
			return isset( $this->actions[$arguments] );
		} catch ( \OutOfBoundsException $e ) {
			// Do nothing; the whole remainder of this method is fail-case.
		}

		/*
		 * If no value is found, check if the action is not actually referencing
		 * another action (for BC reasons), then try fetching the requested data
		 * from that action.
		 */
		try {
			$referencedAction = $this->actions[$action];
			if ( is_string( $referencedAction ) && $referencedAction != $action ) {
				// Replace action name in arguments.
				$arguments[0] = $referencedAction;
				return isset( $this->actions[$arguments] );
			}
		} catch ( \OutOfBoundsException $e ) {
			// Do nothing; the whole remainder of this method is fail-case.
		}

		return false;
	}

	/**
	 * Function can be overloaded depending on how deep the desired value is.
	 *
	 * @param string $action
	 * @param string $type
	 * @return mixed|null Requested value or null if missing
	 */
	public function getValue( $action, $type = null /* [, $option = null [, ...]] */ ) {
		$arguments = func_get_args();

		try {
			return $this->actions[$arguments];
		} catch ( \OutOfBoundsException $e ) {
			// Do nothing; the whole remainder of this method is fail-case.
		}

		/*
		 * If no value is found, check if the action is not actually referencing
		 * another action (for BC reasons), then try fetching the requested data
		 * from that action.
		 */
		try {
			$referencedAction = $this->actions[$action];
			if ( is_string( $referencedAction ) && $referencedAction != $action ) {
				// Remove action name from arguments
				array_shift( $arguments );

				return $this->getValue( $referencedAction, ...$arguments );
			}
		} catch ( \OutOfBoundsException $e ) {
			// Do nothing; the whole remainder of this method is fail-case.
		}

		return null;
	}
}
