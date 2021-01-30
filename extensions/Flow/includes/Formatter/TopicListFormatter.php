<?php

namespace Flow\Formatter;

use Flow\Data\Pager\PagerPage;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\UrlGenerator;
use IContextSource;

class TopicListFormatter extends BaseTopicListFormatter {
	/**
	 * @var UrlGenerator
	 */
	protected $urlGenerator;

	/**
	 * @var RevisionFormatter
	 */
	protected $serializer;

	public function __construct( UrlGenerator $urlGenerator, RevisionFormatter $serializer ) {
		$this->urlGenerator = $urlGenerator;
		$this->serializer = $serializer;
	}

	public function setContentFormat( $contentFormat, UUID $revisionId = null ) {
		$this->serializer->setContentFormat( $contentFormat, $revisionId );
	}

	public function buildEmptyResult( Workflow $workflow ) {
		$title = $workflow->getArticleTitle();
		return [
			'workflowId' => $workflow->getId()->getAlphadecimal(),
			'title' => $title->getPrefixedText(),
			'actions' => $this->buildApiActions( $workflow ),
			'links' => $this->buildLinks( $workflow ),
		] + parent::buildEmptyResult( $workflow );
	}

	public function formatApi(
		Workflow $listWorkflow,
		array $workflows,
		array $found,
		PagerPage $page,
		IContextSource $ctx
	) {
		$res = $this->buildResult( $workflows, $found, $ctx ) +
			$this->buildEmptyResult( $listWorkflow );
		$pagingOption = $page->getPagingLinksOptions();
		$res['links']['pagination'] = $this->buildPaginationLinks(
			$listWorkflow,
			$pagingOption
		);

		return $res;
	}

	/**
	 * Method is called from static::formatApi & ApiFlowSearch::formatApi, to ensure
	 * both have similar output.
	 *
	 * @param Workflow[] $workflows
	 * @param FormatterRow[] $found
	 * @param IContextSource $ctx
	 * @return array
	 */
	public function buildResult( array $workflows, array $found, IContextSource $ctx ) {
		$revisions = $posts = $replies = [];
		foreach ( $found as $formatterRow ) {
			$serialized = $this->serializer->formatApi( $formatterRow, $ctx );
			if ( !$serialized ) {
				continue;
			}
			$revisions[$serialized['revisionId']] = $serialized;
			$posts[$serialized['postId']][] = $serialized['revisionId'];
			$replies[$serialized['replyToId']][] = $serialized['postId'];
		}

		foreach ( $revisions as $i => $serialized ) {
			$alpha = $serialized['postId'];
			$revisions[$i]['replies'] = $replies[$alpha] ?? [];
		}

		$list = [];
		if ( $workflows ) {
			$orig = $workflows;
			$workflows = [];
			foreach ( $orig as $workflow ) {
				$alpha = $workflow->getId()->getAlphadecimal();
				if ( isset( $posts[$alpha] ) ) {
					$list[] = $alpha;
					$workflows[$alpha] = $workflow;
				} else {
					wfDebugLog( 'Flow', __METHOD__ . ": No matching root post for workflow $alpha" );
				}
			}

			foreach ( $list as $alpha ) {
				// Metadata that requires everything to be serialied first
				$metadata = $this->generateTopicMetadata( $posts, $revisions, $workflows, $alpha, $ctx );
				foreach ( $posts[$alpha] as $revId ) {
					$revisions[$revId] += $metadata;
				}
			}
		}

		return [
			// array_values must be used to ensure 0-indexed array
			'roots' => $list,
			'posts' => $posts,
			'revisions' => $revisions,
		];
	}

	protected function buildApiActions( Workflow $workflow ) {
		$actions = [];

		if ( !$workflow->isDeleted() ) {
			$actions['newtopic'] = $this->urlGenerator->newTopicAction( $workflow->getArticleTitle() );
		}

		return $actions;
	}

	protected function generateTopicMetadata( array $posts, array $revisions, array $workflows, $postAlphaId, IContextSource $ctx ) {
		$language = $ctx->getLanguage();
		$user = $ctx->getUser();

		$replies = -1;
		$authors = [];
		$stack = new \SplStack;
		$stack->push( $revisions[$posts[$postAlphaId][0]] );
		do {
			$data = $stack->pop();
			$replies++;
			$authors[] = $data['creator']['name'];
			foreach ( $data['replies'] as $postId ) {
				$stack->push( $revisions[$posts[$postId][0]] );
			}
		} while ( !$stack->isEmpty() );

		/** @var Workflow|null $workflow */
		$workflow = $workflows[$postAlphaId] ?? null;
		$ts = $workflow ? $workflow->getLastUpdatedObj()->getTimestamp() : 0;
		return [
			'reply_count' => $replies,
			'last_updated_readable' => $language->userTimeAndDate( $ts, $user ),
			// ms timestamp
			'last_updated' => $ts * 1000,
		];
	}

	private function buildLinks( Workflow $workflow ) {
		$links = [];

		if ( !$workflow->isDeleted() ) {
			$title = $workflow->getArticleTitle();
			$saveSortBy = true;
			$links['board-sort']['updated'] = $this->urlGenerator->boardLink( $title, 'updated', $saveSortBy )->getLinkURL();
			$links['board-sort']['newest'] = $this->urlGenerator->boardLink( $title, 'newest', $saveSortBy )->getLinkURL();

			// Link to designated new-topic page, for no-JS users
			$links['newtopic'] = $this->urlGenerator->newTopicAction( $title, $workflow->getId() )->getLinkURL();
		}

		return $links;
	}
}
