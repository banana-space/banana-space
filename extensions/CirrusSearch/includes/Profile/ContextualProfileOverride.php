<?php

namespace CirrusSearch\Profile;

/**
 * Overrider that generates a name from a template and
 * contextual information from the profile request.
 * Known "context values" are:
 * - "language": defaults to language code from \IContextSource::getLanguage()
 * @see \IContextSource::getLanguage()
 */
class ContextualProfileOverride implements SearchProfileOverride {
	/**
	 * Language code context, defaults to current context language
	 * @see \IContextSource::getLanguage()
	 */
	const LANGUAGE = 'language';

	/** @var string */
	private $template;

	/** @var array */
	private $params;

	/** @var int */
	private $priority;

	/**
	 * @param string $template A templated profile name
	 * @param string[] $params Map from string in $template to context parameter
	 *  to replace with. All parameters must be available in the context
	 *  parameters or no override will be applied.
	 * @param int $priority
	 */
	public function __construct( $template, array $params, $priority = SearchProfileOverride::CONTEXTUAL_PRIO ) {
		if ( !$params ) {
			throw new \InvalidArgumentException( 'Must provide at least 1 parameter' );
		}
		$this->template = $template;
		$this->params = $params;
		$this->priority = $priority;
	}

	/**
	 * The priority of this override, lower wins.
	 * @return int
	 */
	public function priority() {
		return $this->priority;
	}

	/**
	 * Get the overridden name or null if it cannot be overridden.
	 * @param string[] $contextParams
	 * @return string|null
	 */
	public function getOverriddenName( array $contextParams ) {
		$replacePairs = [];
		foreach ( $this->params as $pattern => $param ) {
			if ( !isset( $contextParams[$param] ) ) {
				return null;
			}
			$replacePairs[$pattern] = $contextParams[$param];
		}
		return strtr( $this->template, $replacePairs );
	}

	/**
	 * @return array
	 */
	public function explain(): array {
		return [
			'type' => 'contextual',
			'priority' => $this->priority(),
			'template' => $this->template
		];
	}
}
