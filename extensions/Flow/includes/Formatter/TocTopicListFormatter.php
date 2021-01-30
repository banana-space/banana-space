<?php

namespace Flow\Formatter;

use Flow\Data\Pager\PagerPage;
use Flow\Model\Workflow;
use Flow\Templating;

// The output of this is a strict subset of TopicListFormatter.
// Anything accessible from the output of this should be accessible with the same path
// from the output of TopicListFormatter.  However, this output is much more minimal.
class TocTopicListFormatter extends BaseTopicListFormatter {
	/**
	 * @var Templating
	 */
	protected $templating;

	public function __construct( Templating $templating ) {
		$this->templating = $templating;
	}

	/**
	 * Formats the response
	 *
	 * @param Workflow $listWorkflow Workflow corresponding to board/list of topics
	 * @param array $topicRootRevisionsByWorkflowId Associative array mapping topic ID (in alphadecimal form)
	 *  to PostRevision for the topic root.
	 * @param array $workflowsByWorkflowId Associative array mapping topic ID (in alphadecimal form) to
	 *  workflow
	 * @param PagerPage $page page from query, to support pagination
	 *
	 * @return array Array formatted for response
	 */
	public function formatApi( Workflow $listWorkflow, $topicRootRevisionsByWorkflowId, $workflowsByWorkflowId, PagerPage $page ) {
		$result = $this->buildEmptyResult( $listWorkflow );

		foreach ( $topicRootRevisionsByWorkflowId as $topicId => $postRevision ) {
			$result['roots'][] = $topicId;
			$revisionId = $postRevision->getRevisionId()->getAlphadecimal();
			$result['posts'][$topicId] = [ $revisionId ];

			$contentFormat = 'topic-title-wikitext';

			$workflow = $workflowsByWorkflowId[$topicId];

			$moderatedRevision = $this->templating->getModeratedRevision( $postRevision );
			$moderationData = $moderatedRevision->isModerated() ?
				[
					'isModerated' => true,
					'moderateState' => $moderatedRevision->getModerationState(),
				] :
				[
					'isModerated' => false
				];
			$result['revisions'][$revisionId] = [
				// Keep this as a minimal subset of
				// RevisionFormatter->formatApi, and keep the same content
				// format for topic titles as specified in that class for
				// topic titles.

				'content' => [
					// no need to check permissions before fetching content; that should've
					// been done by whatever caller supplies $topicRootRevisionsByWorkflowId,
					'content' => $this->templating->getContent(
						$postRevision,
						$contentFormat
					),
					'format' => $contentFormat,
					'plaintext' => $this->templating->getContent( $postRevision, 'topic-title-plaintext' )
				],
				'last_updated' => $workflow->getLastUpdatedObj()->getTimestamp() * 1000,
			] + $moderationData;
		}

		$pagingOption = $page->getPagingLinksOptions();
		$result['links']['pagination'] = $this->buildPaginationLinks(
			$listWorkflow,
			$pagingOption
		);

		return $result;
	}
}
