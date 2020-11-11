<?php

/**
 * Do not generate any input element, just accept a value. How that value gets submitted is someone
 * else's responsibility.
 */
class HTMLSubmittedValueField extends HTMLFormField {
	public function getTableRow( $value ) {
		return '';
	}

	public function getDiv( $value ) {
		return '';
	}

	public function getRaw( $value ) {
		return '';
	}

	public function getInputHTML( $value ) {
		return '';
	}

	public function canDisplayErrors() {
		return false;
	}

	public function hasVisibleOutput() {
		return false;
	}
}
