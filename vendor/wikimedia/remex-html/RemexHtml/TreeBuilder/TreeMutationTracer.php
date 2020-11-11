<?php

namespace RemexHtml\TreeBuilder;

use RemexHtml\Tokenizer\Attributes;

/**
 * This is a debugging helper class which calls the supplied callback function
 * each time there is a TreeHandler event, giving a descriptive message. It
 * then forwards the event through to the supplied handler.
 */
class TreeMutationTracer implements TreeHandler {
	/** @var TreeHandler */
	private $handler;

	/** @var callable */
	private $callback;

	/** @var int */
	private $verbosity;

	/**
	 * Constructor.
	 *
	 * @param TreeHandler $handler The next pipeline stage
	 * @param callable $callback The message output function
	 * @param int $verbosity Set to non-zero to call dump() on the handler
	 *   before and after each event.
	 */
	public function __construct( TreeHandler $handler, callable $callback, $verbosity = 0 ) {
		$this->handler = $handler;
		$this->callback = $callback;
		$this->verbosity = $verbosity;
	}

	/**
	 * Send a message
	 */
	private function trace( $msg ) {
		call_user_func( $this->callback, "[Tree] $msg" );
	}

	/**
	 * Send a message for an event
	 */
	private function traceEvent( $funcName, $args ) {
		$this->trace( call_user_func_array( [ TraceFormatter::class, $funcName ], $args ) );
	}

	private function handleMutation( $funcName, $args ) {
		$this->traceEvent( $funcName, $args );
		$this->before();
		call_user_func_array( [ $this->handler, $funcName ], $args );
		$this->after();
	}

	private function handleSimple( $funcName, $args ) {
		$this->traceEvent( $funcName, $args );
		call_user_func_array( [ $this->handler, $funcName ], $args );
	}

	/**
	 * A helper called before the underlying handler is called.
	 */
	private function before() {
		if ( $this->verbosity > 0 ) {
			$this->trace( "Before: " . $this->handler->dump() . "\n" );
		}
	}

	/**
	 * A helper called after the underlying handler is called.
	 */
	private function after() {
		if ( $this->verbosity > 0 ) {
			$this->trace( "After:  " . $this->handler->dump() . "\n" );
		}
	}

	public function startDocument( $fns, $fn ) {
		$this->handleSimple( __FUNCTION__, func_get_args() );
	}

	public function endDocument( $pos ) {
		$this->handleSimple( __FUNCTION__, func_get_args() );
	}

	public function characters( $preposition, $refNode, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function insertElement( $preposition, $refNode, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function endTag( Element $element, $sourceStart, $sourceLength ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function comment( $preposition, $refNode, $text, $sourceStart, $sourceLength ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function error( $text, $pos ) {
		$this->handleSimple( __FUNCTION__, func_get_args() );
	}

	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function removeNode( Element $element, $sourceStart ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}

	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$this->handleMutation( __FUNCTION__, func_get_args() );
	}
}
