<?php

namespace Flow\Exception;

use MWException;
use OutputPage;
use RequestContext;

/**
 * Flow base exception
 */
class FlowException extends MWException {

	/**
	 * Flow exception error code
	 * @var string
	 */
	protected $code;

	/**
	 * The output object
	 * @var OutputPage
	 */
	protected $output;

	/**
	 * @param string $message The message from exception, used for debugging error
	 * @param string $code The error code used to display error message
	 */
	public function __construct( $message, $code = 'default' ) {
		global $wgOut;
		parent::__construct( $message );
		$this->code = $code;
		// Set output object to the global $wgOut object by default
		$this->output = $wgOut;
	}

	/**
	 * Set the output object
	 * @param OutputPage $output
	 */
	public function setOutput( OutputPage $output ) {
		$this->output = $output;
	}

	/**
	 * Get the message key for the localized error message
	 * @return string
	 */
	public function getErrorCode() {
		$list = $this->getErrorCodeList();
		if ( !in_array( $this->code, $list ) ) {
			$this->code = 'default';
		}
		return 'flow-error-' . $this->code;
	}

	/**
	 * Error code list for this exception
	 * @return string[]
	 */
	protected function getErrorCodeList() {
		// flow-error-default
		return [ 'default' ];
	}

	/**
	 * Override parent method: we can use wfMessage here
	 *
	 * @return bool
	 */
	public function useMessageCache() {
		return true;
	}

	/**
	 * Overrides MWException getHTML, adding a more human-friendly error message
	 *
	 * @return string
	 */
	public function getHTML() {
		/*
		 * We'll want both a proper humanized error msg & the stacktrace the
		 * parent exception handler generated.
		 * We'll create a stub OutputPage object here, to use its showErrorPage
		 * to add our own humanized error message. Then we'll append the stack-
		 * trace (parent::getHTML) and then just return the combined HTML.
		 */
		$rc = new RequestContext();
		$output = $rc->getOutput();
		$output->showErrorPage( $this->getPageTitle(), $this->getErrorCode() );
		$output->addHTML( parent::getHTML() );
		return $output->getHTML();
	}

	/**
	 * Helper function for msg function in the convenience of a default callback
	 * @param string $key
	 * @return string
	 */
	public function parsePageTitle( $key ) {
		global $wgSitename;
		return $this->msg( $key, "$1 - $wgSitename", $this->msg( 'internalerror', 'Internal error' ) );
	}

	/**
	 * Error page title
	 * @return string
	 */
	public function getPageTitle() {
		return $this->parsePageTitle( 'errorpagetitle' );
	}

	/**
	 * Exception from API/commandline will be handled by MWException::report(),
	 * Overwrite the HTML display only
	 */
	public function reportHTML() {
		$this->output->setStatusCode( $this->getStatusCode() );

		/*
		 * Parent exception handler uses global $wgOut
		 * We want to play nice and do inheritance and all, but that means we'll
		 * have to cheat here and assign out $this->output to $wgOut in order
		 * to have parent::reportHTML use the correct OutputPage object.
		 * After that, restore original $wgOut.
		 */
		global $wgOut;
		$wgOutBkp = $wgOut;
		$wgOut = $this->output;
		parent::reportHTML(); // this will do ->output() already
		$wgOut = $wgOutBkp;
	}

	/**
	 * Default status code is 500, which is server error
	 * @return int
	 */
	public function getStatusCode() {
		return 500;
	}
}
