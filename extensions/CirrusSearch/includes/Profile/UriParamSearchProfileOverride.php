<?php

namespace CirrusSearch\Profile;

class UriParamSearchProfileOverride implements SearchProfileOverride {

	/**
	 * @var \WebRequest
	 */
	private $request;

	/**
	 * @var string
	 */
	private $uriParam;

	/**
	 * @var integer
	 */
	private $priority;

	/**
	 * @param \WebRequest $request
	 * @param string $uriParam
	 * @param int $priority
	 */
	public function __construct( \WebRequest $request, $uriParam, $priority = SearchProfileOverride::URI_PARAM_PRIO ) {
		$this->request = $request;
		$this->uriParam = $uriParam;
		$this->priority = $priority;
	}

	/**
	 * Get the overridden name or null if it cannot be overridden.
	 * @param string[] $contextParams
	 * @return string|null
	 */
	public function getOverriddenName( array $contextParams ) {
		return $this->request->getVal( $this->uriParam );
	}

	/**
	 * The priority of this override, lower wins
	 * @return int
	 */
	public function priority() {
		return $this->priority;
	}

	/**
	 * @return array
	 */
	public function explain(): array {
		return [
			'type' => 'uriParam',
			'priority' => $this->priority(),
			'uriParam' => $this->uriParam
		];
	}
}
