<?php

namespace Flow\Formatter;

use ChangesList;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\UUID;
use Flow\UrlGenerator;
use IContextSource;

class RevisionViewFormatter {
	/**
	 * @var UrlGenerator
	 */
	protected $urlGenerator;

	/**
	 * @var RevisionFormatter
	 */
	protected $serializer;

	/**
	 * @param UrlGenerator $urlGenerator
	 * @param RevisionFormatter $serializer
	 */
	public function __construct( UrlGenerator $urlGenerator, RevisionFormatter $serializer ) {
		$this->urlGenerator = $urlGenerator;
		$this->serializer = $serializer;
	}

	public function setContentFormat( $format, UUID $revisionId = null ) {
		$this->serializer->setContentFormat( $format, $revisionId );
	}

	/**
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @return array
	 */
	public function formatApi( FormatterRow $row, IContextSource $ctx ) {
		$res = $this->serializer->formatApi( $row, $ctx );
		$res['rev_view_links'] = $this->buildLinks( $row, $ctx );
		$res['human_timestamp'] = $ctx->getLanguage()->getHumanTimestamp(
			new \MWTimestamp( $res['timestamp'] )
		);
		if ( $row->revision instanceof PostRevision ) {
			$res['properties']['topic-of-post'] = $this->serializer->processParam(
				'topic-of-post',
				$row->revision,
				$row->workflow->getId(),
				$ctx
			);
			$res['properties']['topic-of-post-text-from-html'] = $this->serializer->processParam(
				'topic-of-post-text-from-html',
				$row->revision,
				$row->workflow->getId(),
				$ctx
			);
		}
		if ( $row->revision instanceof PostSummary ) {
			$res['properties']['post-of-summary'] = $this->serializer->processParam(
				'post-of-summary',
				$row->revision,
				$row->workflow->getId(),
				$ctx
			);
		}
		return $res;
	}

	/**
	 * Generate the links for single and diff view actions
	 *
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @return array
	 */
	public function buildLinks( FormatterRow $row, IContextSource $ctx ) {
		$workflowId = $row->workflow->getId();

		$boardTitle = $row->workflow->getOwnerTitle();
		$title = $row->workflow->getArticleTitle();
		$links = [
			'hist' => $this->urlGenerator->boardHistoryLink( $title ),
			'board' => $this->urlGenerator->boardLink( $boardTitle ),
		];

		if ( $row->revision instanceof PostRevision || $row->revision instanceof PostSummary ) {
			$links['root'] = $this->urlGenerator->topicLink( $row->workflow->getArticleTitle(), $workflowId );
			$links['root']->setMessage( $title->getPrefixedText() );
		}

		if ( $row->revision instanceof PostRevision ) {
			$links['single-view'] = $this->urlGenerator->postRevisionLink(
				$title,
				$workflowId,
				// @phan-suppress-next-line PhanUndeclaredMethod Type not correctly inferred
				$row->revision->getPostId(),
				$row->revision->getRevisionId()
			);
			$links['single-view']->setMessage( $title->getPrefixedText() );
		} elseif ( $row->revision instanceof Header ) {
			$links['single-view'] = $this->urlGenerator->headerRevisionLink(
				$title,
				$workflowId,
				$row->revision->getRevisionId()
			);
			$links['single-view']->setMessage( $title->getPrefixedText() );
		} elseif ( $row->revision instanceof PostSummary ) {
			$links['single-view'] = $this->urlGenerator->summaryRevisionLink(
				$title,
				$workflowId,
				$row->revision->getRevisionId()
			);
			$links['single-view']->setMessage( $title->getPrefixedText() );
		} else {
			wfDebugLog( 'Flow', __METHOD__ . ': Received unknown revision type ' . get_class( $row->revision ) );
		}

		if ( $row->revision->getPrevRevisionId() !== null ) {
			$links['diff'] = $this->urlGenerator->diffLink(
				$row->revision,
				null,
				$workflowId
			);
			$links['diff']->setMessage( $ctx->msg( 'diff' ) );
		} else {
			$links['diff'] = [
				'url' => '',
				'title' => ''
			];
		}

		$recentChange = $row->revision->getRecentChange();
		if ( $recentChange !== null ) {
			$user = $ctx->getUser();
			if ( ChangesList::isUnpatrolled( $recentChange, $user ) ) {
				$token = $user->getEditToken( $recentChange->getAttribute( 'rc_id' ) );
				$links['markPatrolled'] = $this->urlGenerator->markRevisionPatrolledAction(
					$title,
					$workflowId,
					$recentChange,
					$token
				);
			}
		}

		return $links;
	}

}
