<?php

namespace Flow\Log;

use Flow\Collection\PostCollection;
use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Data\ManagerGroup;
use Flow\Model\UUID;
use Flow\Repository\TreeRepository;
use Flow\RevisionActionPermissions;
use Flow\Templating;
use Flow\UrlGenerator;
use LogEntry;
use LogFormatter;
use LogPage;
use Message;

class ActionFormatter extends LogFormatter {
	/**
	 * @var UUID[]
	 */
	private static $uuids = [];

	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @var Templating
	 */
	protected $templating;

	/**
	 * @param LogEntry $entry
	 */
	public function __construct( LogEntry $entry ) {
		parent::__construct( $entry );

		$this->permissions = Container::get( 'permissions' );
		$this->templating = Container::get( 'templating' );

		$params = $this->entry->getParameters();
		// serialized topicId or postId can be stored
		foreach ( $params as $key => $value ) {
			if ( $value instanceof UUID ) {
				static::$uuids[$value->getAlphadecimal()] = $value;
			}
		}
	}

	/**
	 * Formats an activity log entry.
	 *
	 * @return string The log entry
	 */
	protected function getActionMessage() {
		/*
		 * At this point, all log entries will already have been created & we've
		 * gathered all uuids in constructor: we can now batch-load all of them.
		 * We won't directly be using that batch-loaded data (nothing will even
		 * be returned) but it'll ensure that everything we need will be
		 * retrieved from cache/storage efficiently & waiting in memory for when
		 * we request it again.
		 */
		static $loaded = false;
		if ( !$loaded ) {
			/** @var ManagerGroup storage */
			$storage = Container::get( 'storage' );
			/** @var TreeRepository $treeRepository */
			$treeRepository = Container::get( 'repository.tree' );

			$query = new LogQuery( $storage, $treeRepository );
			$query->loadMetadataBatch( static::$uuids );
			$loaded = true;
		}

		$root = $this->getRoot();
		if ( !$root ) {
			// failed to load required data
			return '';
		}

		$type = $this->entry->getType();
		$action = $this->entry->getSubtype();
		$title = $this->entry->getTarget();
		$params = $this->entry->getParameters();

		if ( isset( $params['postId'] ) ) {
			/** @var UrlGenerator $urlGenerator */
			$urlGenerator = Container::get( 'url_generator' );

			// generate link that highlights the post
			$anchor = $urlGenerator->postLink(
				$title,
				UUID::create( $params['topicId'] ),
				UUID::create( $params['postId'] )
			);
			$title = $anchor->resolveTitle();
		}

		$rootLastRevision = $root->getLastRevision();

		// Give grep a chance to find the usages:
		// A few of the -topic-title-not-visible are not reachable with the current
		// config (since people looking at the suppression log can see suppressed
		// content), but are included to make it less brittle.
		// logentry-delete-flow-delete-post, logentry-delete-flow-delete-post-topic-title-not-visible,
		// logentry-delete-flow-restore-post, logentry-delete-flow-restore-post-topic-title-not-visible,
		// logentry-suppress-flow-restore-post, logentry-suppress-flow-restore-post-topic-title-not-visible,
		// logentry-suppress-flow-suppress-post, logentry-suppress-flow-suppress-post-topic-title-not-visible,
		// logentry-delete-flow-delete-topic, logentry-delete-flow-delete-topic-topic-title-not-visible,
		// logentry-delete-flow-restore-topic, logentry-delete-flow-restore-topic-topic-title-not-visible,
		// logentry-suppress-flow-restore-topic, logentry-suppress-flow-restore-topic-topic-title-not-visible,
		// logentry-suppress-flow-suppress-topic, logentry-suppress-flow-suppress-topic-topic-title-not-visible,
		// logentry-lock-flow-lock-topic, logentry-lock-flow-lock-topic-topic-title-not-visible
		// logentry-lock-flow-restore-topic, logentry-lock-flow-restore-topic-topic-title-not-visible,
		$messageKey = "logentry-$type-$action";
		$isTopicTitleVisible = $this->permissions->isAllowed( $rootLastRevision, 'view-topic-title' );

		if ( !$isTopicTitleVisible ) {
			$messageKey .= '-topic-title-not-visible';
		}

		$message = $this->msg( $messageKey )
			->params( [
				Message::rawParam( $this->getPerformerElement() ),
				$this->entry->getPerformer()->getName(),
			] );

		if ( $isTopicTitleVisible ) {
			$message->params( [
				$title, // Title of topic
				$title->getFullURL(), // Full URL of topic, with highlighted post if applicable
			] );

			$message->plaintextParams( $this->templating->getContent( $rootLastRevision, 'topic-title-plaintext' ) );
		}

		$message->params( $root->getWorkflow()->getOwnerTitle() ); // board title object

		$message->parse();

		return \Html::rawElement(
			'span',
			[ 'class' => 'plainlinks' ],
			$message->parse()
		);
	}

	/**
	 * The native LogFormatter::getActionText provides no clean way of handling
	 * the Flow action text in a plain text format (e.g. as used by CheckUser)
	 *
	 * @return string
	 */
	public function getActionText() {
		if ( $this->canView( LogPage::DELETED_ACTION ) ) {
			$text = $this->getActionMessage();
			return $this->plaintext ? Utils::htmlToPlaintext( $text ) : $text;
		} else {
			return parent::getActionText();
		}
	}

	/**
	 * @return PostCollection|bool
	 */
	protected function getRoot() {
		$params = $this->entry->getParameters();

		try {
			if ( !isset( $params['topicId'] ) ) {
				// failed finding the expected data in storage
				wfWarn( __METHOD__ . ': Failed to locate topicId in log_params for: ' . serialize( $params ) .
					' (forgot to run FlowFixLog.php?)' );
				return false;
			}

			$uuid = UUID::create( $params['topicId'] );
			$collection = PostCollection::newFromId( $uuid );

			// see if this post is valid
			$collection->getLastRevision();
			return $collection;
		} catch ( \Exception $e ) {
			// failed finding the expected data in storage
			wfWarn( __METHOD__ . ': Failed to locate root for: ' . serialize( $params ) .
				' (potentially storage issue)' );
			return false;
		}
	}
}
