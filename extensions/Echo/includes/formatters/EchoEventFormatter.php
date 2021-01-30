<?php

use MediaWiki\Logger\LoggerFactory;

/**
 * Abstract class that each "formatter" should implement.
 *
 * A formatter is an output type, example formatters would be:
 * * Special:Notifications
 * * HTML email
 * * plaintext email
 *
 * The formatter does not maintain any state except for the
 * arguments passed in the constructor (user and language)
 */
abstract class EchoEventFormatter {

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
	 * @param EchoEvent $event
	 * @return string[]|string|false Output format depends on implementation, false if it cannot be formatted
	 */
	final public function format( EchoEvent $event ) {
		// Deleted events should have been filtered out before getting there.
		// This is just to be sure.
		if ( $event->isDeleted() ) {
			return false;
		}

		if ( !EchoEventPresentationModel::supportsPresentationModel( $event->getType() ) ) {
			LoggerFactory::getInstance( 'Echo' )->debug(
				"No presentation model found for event type \"{type}\"",
				[
					'type' => $event->getType(),
				]
			);
			return false;
		}

		$model = EchoEventPresentationModel::factory( $event, $this->language, $this->user );
		if ( !$model->canRender() ) {
			return false;
		}

		return $this->formatModel( $model );
	}

	/**
	 * @param EchoEventPresentationModel $model
	 * @return string[]|string
	 */
	abstract protected function formatModel( EchoEventPresentationModel $model );
}
