<?php

namespace Flow\Content;

use Article;
use Flow\Actions\FlowAction;
use Flow\Container;
use Flow\Diff\FlowBoardContentDiffView;
use Flow\FlowActions;
use Flow\Model\UUID;
use FormatJson;
use IContextSource;
use MWException;
use Page;

class BoardContentHandler extends \ContentHandler {
	public function __construct( $modelId ) {
		if ( $modelId !== CONTENT_MODEL_FLOW_BOARD ) {
			throw new MWException( __CLASS__ . " initialised for invalid content model" );
		}

		parent::__construct( CONTENT_MODEL_FLOW_BOARD, [ CONTENT_FORMAT_JSON ] );
	}

	protected function getDiffEngineClass() {
		return FlowBoardContentDiffView::class;
	}

	public function isSupportedFormat( $format ) {
		// Necessary for backwards-compatability where
		// the format "json" was used
		if ( $format === 'json' ) {
			$format = CONTENT_FORMAT_JSON;
		}

		return parent::isSupportedFormat( $format );
	}

	/**
	 * Serializes a Content object of the type supported by this ContentHandler.
	 *
	 * @since 1.21
	 *
	 * @param \Content $content The Content object to serialize
	 * @param string|null $format The desired serialization format
	 * @return string Serialized form of the content
	 * @throws MWException
	 */
	public function serializeContent( \Content $content, $format = null ) {
		if ( !$content instanceof BoardContent ) {
			throw new MWException( "Expected a BoardContent object, got a " . get_class( $content ) );
		}

		$info = [];

		if ( $content->getWorkflowId() ) {
			$info['flow-workflow'] = $content->getWorkflowId()->getAlphadecimal();
		}

		return FormatJson::encode( $info );
	}

	/**
	 * Unserializes a Content object of the type supported by this ContentHandler.
	 *
	 * @since 1.21
	 *
	 * @param string $blob Serialized form of the content
	 * @param string|null $format The format used for serialization
	 *
	 * @return BoardContent The Content object created by deserializing $blob
	 */
	public function unserializeContent( $blob, $format = null ) {
		$info = FormatJson::decode( $blob, true );
		$uuid = null;

		if ( !$info ) {
			// Temporary: Fix T167198 and instead throw an exception, to
			// prevent corruption from software that does not understand
			// Flow/content models.

			return $this->makeEmptyContent();
		} elseif ( isset( $info['flow-workflow'] ) ) {
			$uuid = UUID::create( $info['flow-workflow'] );
		}

		return new BoardContent( CONTENT_MODEL_FLOW_BOARD, $uuid );
	}

	/**
	 * Creates an empty Content object of the type supported by this
	 * ContentHandler.
	 *
	 * @since 1.21
	 *
	 * @return BoardContent
	 */
	public function makeEmptyContent() {
		return new BoardContent;
	}

	/**
	 * Don't let people turn random pages into Flow ones. They either need to be:
	 * * in a Flow-enabled namespace already (where content model is flow-board by
	 *   default).  In such a namespace, non-existent pages are created as Flow.
	 * * explicitly allowed for a user, requiring special permissions
	 *
	 * @param \Title $title
	 * @return bool
	 */
	public function canBeUsedOn( \Title $title ) {
		/** @var \Flow\TalkpageManager $manager */
		$manager = Container::get( 'occupation_controller' );
		return $manager->canBeUsedOn( $title );
	}

	/**
	 * Returns overrides for action handlers.
	 * Classes listed here will be used instead of the default one when
	 * (and only when) $wgActions[$action] === true. This allows subclasses
	 * to override the default action handlers.
	 *
	 * @since 1.21
	 *
	 * @return array Associative array mapping action names to handler callables
	 */
	public function getActionOverrides() {
		/** @var FlowActions $actions */
		$actions = Container::get( 'flow_actions' );
		$output = [];

		foreach ( $actions->getActions() as $action ) {
			$actionData = $actions->getValue( $action );
			if ( !is_array( $actionData ) ) {
				continue;
			}

			if ( !isset( $actionData['handler-class'] ) ) {
				continue;
			}

			if ( $actionData['handler-class'] === FlowAction::class ) {
				$output[$action] = function (
					Page $article,
					IContextSource $source
				) use ( $action ) {
					/** @var Article $article */
					return new FlowAction( $article, $source, $action );
				};
			} else {
				$output[$action] = $actionData['handler-class'];
			}
		}

		// Flow has its own handling for action=edit
		$output['edit'] = \Flow\Actions\EditAction::class;

		return $output;
	}
}
