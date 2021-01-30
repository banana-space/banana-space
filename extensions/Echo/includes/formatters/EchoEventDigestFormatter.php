<?php

/**
 * Abstract class for formatters that process multiple events.
 *
 * The formatter does not maintain any state except for the
 * arguments passed in the constructor (user and language)
 */
abstract class EchoEventDigestFormatter {

	/** @var User */
	protected $user;

	/** @var Language */
	protected $language;

	public function __construct( User $user, Language $language ) {
		$this->user = $user;
		$this->language = $language;
	}

	/**
	 * Equivalent to IContextSource::msg for the current
	 * language
	 *
	 * @param string ...$args
	 * @return Message
	 */
	protected function msg( ...$args ) {
		/**
		 * @var Message $msg
		 */
		$msg = wfMessage( ...$args );
		$msg->inLanguage( $this->language );

		return $msg;
	}

	/**
	 * @param EchoEvent[] $events
	 * @param string $distributionType 'web' or 'email'
	 * @return string[]|false Output format depends on implementation, false if it cannot be formatted
	 */
	final public function format( array $events, $distributionType ) {
		$models = [];
		foreach ( $events as $event ) {
			$model = EchoEventPresentationModel::factory( $event, $this->language, $this->user, $distributionType );
			if ( $model->canRender() ) {
				$models[] = $model;
			}
		}

		return $models ? $this->formatModels( $models ) : false;
	}

	/**
	 * @param EchoEventPresentationModel[] $models
	 * @return string[]|string
	 */
	abstract protected function formatModels( array $models );
}
