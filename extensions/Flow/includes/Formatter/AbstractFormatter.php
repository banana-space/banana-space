<?php

namespace Flow\Formatter;

use Flow\Container;
use Flow\FlowActions;
use Flow\Model\Anchor;
use Flow\Model\PostRevision;
use Flow\RevisionActionPermissions;
use Html;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Message;

/**
 * This is a "utility" class that might come in useful to generate
 * some output per Flow entry, e.g. for RecentChanges, Contributions, ...
 * These share a lot of common characteristics (like displaying a date, links to
 * the posts, some description of the action, ...)
 *
 * Just extend from this class to use these common util methods, and make sure
 * to pass the correct parameters to these methods. Basically, you'll need to
 * create a new method that'll accept the objects for your specific
 * implementation (like ChangesList & RecentChange objects for RecentChanges, or
 * ContribsPager and a DB row for Contributions). From those rows, you should be
 * able to derive the objects needed to pass to these utility functions (mainly
 * Workflow, AbstractRevision, Title, User and Language objects) and return the
 * output.
 *
 * For implementation examples, check Flow\RecentChanges\Formatter or
 * Flow\Contributions\Formatter.
 */
abstract class AbstractFormatter {
	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @var RevisionFormatter
	 */
	protected $serializer;

	public function __construct(
		RevisionActionPermissions $permissions,
		RevisionFormatter $serializer
	) {
		$this->permissions = $permissions;
		$this->serializer = $serializer;
	}

	abstract protected function getHistoryType();

	/**
	 * @see RevisionFormatter::buildLinks
	 * @see RevisionFormatter::getDateFormats
	 *
	 * @param array $data Expects an array with keys 'dateFormats', 'isModeratedNotLocked'
	 *  and 'links'. The former should be an array having the key $key being
	 *  tossed in here; the latter an array of links in the [key => [href, msg]]
	 *  format, where 'key' corresponds with a $linksKeys value. The central is
	 *  a boolean.
	 * @param string $key Date format to use - any of the keys in the array
	 *  returned by RevisionFormatter::getDateFormats
	 * @param string[] $linkKeys Link key(s) to use as link for the timestamp;
	 *  the first available key will be used (but accepts an array of multiple
	 *  keys for when different kinds of data are tossed in, which may not all
	 *  have the same kind of links available)
	 * @return string HTML
	 */
	protected function formatTimestamp(
		array $data,
		$key = 'timeAndDate',
		array $linkKeys = [ 'header-revision', 'topic-revision', 'post-revision', 'summary-revision' ]
	) {
		// Format timestamp: add link
		$formattedTime = $data['dateFormats'][$key];

		// Find the first available link to attach to the timestamp
		$anchor = null;
		foreach ( $linkKeys as $linkKey ) {
			if ( isset( $data['links'][$linkKey] ) ) {
				$anchor = $data['links'][$linkKey];
				break;
			}
		}

		$class = [ 'mw-changeslist-date' ];
		if ( $data['isModeratedNotLocked'] ) {
			$class[] = 'history-deleted';
		}

		if ( $anchor instanceof Anchor ) {
			return Html::rawElement(
				'span',
				[ 'class' => $class ],
				$anchor->toHtml( $formattedTime )
			);
		} else {
			return Html::element( 'span', [ 'class' => $class ], $formattedTime );
		}
	}

	/**
	 * Generate HTML for "(foo | bar | baz)"  based on the links provided by
	 * RevisionFormatter.
	 *
	 * @param array $links Contains any combination of Anchor|Message|string
	 * @param IContextSource $ctx
	 * @param string[]|null $request List of link names to be allowed in result output
	 * @return string Html valid for user output
	 */
	protected function formatAnchorsAsPipeList(
		array $links,
		IContextSource $ctx,
		array $request = null
	) {
		if ( $request === null ) {
			$request = array_keys( $links );
		} elseif ( !$request ) {
			// empty array was passed
			return '';
		}
		$have = array_combine( $request, $request );

		$formatted = [];
		foreach ( $links as $key => $link ) {
			if ( isset( $have[$key] ) ) {
				if ( $link instanceof Anchor ) {
					$formatted[] = $link->toHtml();
				} elseif ( $link instanceof Message ) {
					$formatted[] = $link->escaped();
				} else {
					// plain text
					$formatted[] = htmlspecialchars( $key );
				}
			}
		}

		if ( $formatted ) {
			$content = $ctx->getLanguage()->pipeList( $formatted );
			if ( $content ) {
				return $ctx->msg( 'parentheses' )->rawParams( $content )->escaped();
			}
		}

		return '';
	}

	/**
	 * Gets the "diff" link; linking to the diff against the previous revision,
	 * in a format that can be wrapped in an array and passed to
	 * formatLinksAsPipeList.
	 *
	 * @param Anchor[] $input
	 * @param IContextSource $ctx
	 * @return Anchor|Message
	 */
	protected function getDiffAnchor( array $input, IContextSource $ctx ) {
		if ( !isset( $input['diff'] ) ) {
			// plain text with no link
			return $ctx->msg( 'diff' );
		}

		return $input['diff'];
	}

	/**
	 * Gets the "prev" link; linking to the diff against the previous revision,
	 * in a format that can be wrapped in an array and passed to
	 * formatLinksAsPipeList.
	 *
	 * @param Anchor[] $input
	 * @param IContextSource $ctx
	 * @return Anchor|Message
	 */
	protected function getDiffPrevAnchor( array $input, IContextSource $ctx ) {
		if ( !isset( $input['diff-prev'] ) ) {
			// plain text with no link
			return $ctx->msg( 'last' );
		}

		return $input['diff-prev'];
	}

	/**
	 * Gets the "cur" link; linking to the diff against the current revision,
	 * in a format that can be wrapped in an array and passed to
	 * formatLinksAsPipeList.
	 *
	 * @param Anchor[] $input
	 * @param IContextSource $ctx
	 * @return Anchor|Message
	 */
	protected function getDiffCurAnchor( array $input, IContextSource $ctx ) {
		if ( !isset( $input['diff-cur'] ) ) {
			// plain text with no link
			return $ctx->msg( 'cur' );
		}

		return $input['diff-cur'];
	}

	/**
	 * Gets the "hist" link; linking to the history of a certain element, in a
	 * format that can be wrapped in an array and passed to
	 * formatLinksAsPipeList.
	 *
	 * @param Anchor[] $input
	 * @param IContextSource $ctx
	 * @return Anchor|Message
	 */
	protected function getHistAnchor( array $input, IContextSource $ctx ) {
		if ( isset( $input['post-history'] ) ) {
			$anchor = $input['post-history'];
		} elseif ( isset( $input['topic-history'] ) ) {
			$anchor = $input['topic-history'];
		} elseif ( isset( $input['board-history'] ) ) {
			$anchor = $input['board-history'];
		} else {
			$anchor = null;
		}

		if ( $anchor instanceof Anchor ) {
			$anchor->setMessage( $ctx->msg( 'hist' ) );
			return $anchor;
		} else {
			// plain text with no link
			return $ctx->msg( 'hist' );
		}
	}

	/**
	 * @return string Html valid for user output
	 */
	protected function changeSeparator() {
		return ' <span class="mw-changeslist-separator">. .</span> ';
	}

	/**
	 * @param array $data
	 * @param IContextSource $ctx
	 * @return Message
	 */
	protected function getDescription( array $data, IContextSource $ctx ) {
		// Build description message, piggybacking on history i18n
		$changeType = $data['changeType'];
		$actions = $this->permissions->getActions();

		$key = $actions->getValue( $changeType, 'history', 'i18n-message' );
		// find specialized message for this particular formatter type
		// E.g. the -irc messages.
		$msg = $ctx->msg( $key . '-' . $this->getHistoryType() );
		if ( !$msg->exists() ) {
			// fallback to default msg
			$msg = $ctx->msg( $key );
		}

		return $msg->params( $this->getDescriptionParams( $data, $actions, $changeType ) );
	}

	/**
	 * @param array $data
	 * @param FlowActions $actions
	 * @param string $changeType
	 * @return array
	 */
	protected function getDescriptionParams( array $data, FlowActions $actions, $changeType ) {
		$source = $actions->getValue( $changeType, 'history', 'i18n-params' );
		$params = [];
		foreach ( $source as $param ) {
			if ( isset( $data['properties'][$param] ) ) {
				$params[] = $data['properties'][$param];
			} else {
				wfDebugLog( 'Flow', __METHOD__ .
					": Missing expected parameter $param for change type $changeType" );
				$params[] = '';
			}
		}

		return $params;
	}

	/**
	 * Generate an HTML revision description.
	 *
	 * @param array $data
	 * @param IContextSource $ctx
	 * @return string Html valid for user output
	 */
	protected function formatDescription( array $data, IContextSource $ctx ) {
		$msg = $this->getDescription( $data, $ctx );
		return '<span class="plainlinks">' . $msg->parse() . '</span>';
	}

	/**
	 * Returns HTML links to the page title and (if the action is topic-related)
	 * the topic.
	 *
	 * @param array $data
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @return string HTML linking to topic & board
	 */
	protected function getTitleLink( array $data, FormatterRow $row, IContextSource $ctx ) {
		$ownerLink = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$row->workflow->getOwnerTitle(),
			null,
			[ 'class' => 'mw-title' ]
		);

		if ( !isset( $data['links']['topic'] ) || !$row->revision instanceof PostRevision ) {
			return $ownerLink;
		}
		/** @var Anchor $topic */
		$topic = $data['links']['topic'];

		// generated link has generic link text, should be actual topic title
		// @phan-suppress-next-line PhanUndeclaredMethod $row->revision being PostRevision is not inferred
		$root = $row->revision->getRootPost();
		if ( $root && $this->permissions->isAllowed( $root, 'view' ) ) {
			$topicDisplayText = Container::get( 'templating' )
				->getContent( $root, 'topic-title-plaintext' );
			$topic->setMessage( $topicDisplayText );
		}

		return $ctx->msg( 'flow-rc-topic-of-board' )->rawParams(
			$topic->toHtml(),
			$ownerLink
		)->escaped();
	}
}
