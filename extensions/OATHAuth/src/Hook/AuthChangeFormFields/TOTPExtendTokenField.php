<?php

namespace MediaWiki\Extension\OATHAuth\Hook\AuthChangeFormFields;

use MediaWiki\Auth\AuthenticationRequest;

class TOTPExtendTokenField {
	/**
	 * @var AuthenticationRequest[]
	 */
	protected $requests;

	/**
	 * @var array
	 */
	protected $fieldInfo;

	/**
	 * @var array
	 */
	protected $formDescriptor;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 * @return bool
	 */
	public static function callback( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$handler = new static(
			$requests,
			$fieldInfo,
			$formDescriptor,
			$action
		);

		return $handler->execute();
	}

	/**
	 * @param AuthenticationRequest[] $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	protected function __construct( $requests, $fieldInfo, &$formDescriptor, $action ) {
		$this->requests = $requests;
		$this->fieldInfo = $fieldInfo;
		$this->formDescriptor = &$formDescriptor;
		$this->action = $action;
	}

	protected function execute() {
		if ( $this->shouldSkip() ) {
			return true;
		}

		$this->formDescriptor['OATHToken'] += [
			'cssClass' => 'loginText',
			'id' => 'wpOATHToken',
			'size' => 20,
			'dir' => 'ltr',
			'autofocus' => true,
			'persistent' => false,
			'autocomplete' => false,
			'spellcheck' => false,
		];
		return true;
	}

	protected function shouldSkip() {
		return !isset( $this->fieldInfo['OATHToken'] );
	}
}
