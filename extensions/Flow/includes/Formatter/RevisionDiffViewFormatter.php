<?php

namespace Flow\Formatter;

use Flow\Model\UUID;
use Flow\UrlGenerator;
use IContextSource;

class RevisionDiffViewFormatter {

	/**
	 * @var RevisionViewFormatter
	 */
	protected $revisionViewFormatter;

	/**
	 * @var UrlGenerator
	 */
	protected $urlGenerator;

	public function __construct(
		RevisionViewFormatter $revisionViewFormatter,
		UrlGenerator $urlGenerator
	) {
		$this->revisionViewFormatter = $revisionViewFormatter;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Diff would format against two revisions
	 * @param FormatterRow $newRow
	 * @param FormatterRow $oldRow
	 * @param IContextSource $ctx
	 * @return array
	 */
	public function formatApi( FormatterRow $newRow, FormatterRow $oldRow, IContextSource $ctx ) {
		$oldRes = $this->revisionViewFormatter->formatApi( $oldRow, $ctx );
		$newRes = $this->revisionViewFormatter->formatApi( $newRow, $ctx );

		$oldContent = $oldRow->revision->getContentInWikitext();
		$newContent = $newRow->revision->getContentInWikitext();

		$differenceEngine = new \DifferenceEngine();

		$differenceEngine->setContent(
			new \TextContent( $oldContent ),
			new \TextContent( $newContent )
		);

		if ( $oldRow->revision->isFirstRevision() ) {
			$prevLink = null;
		} else {
			$prevLink = $this->urlGenerator->diffLink(
				$oldRow->revision,
				$ctx->getTitle(),
				UUID::create( $oldRes['workflowId'] )
			)->getLocalURL();
		}

		// this is probably a network request which typically goes in the query
		// half, but we don't have to worry about batching because we only show
		// one diff at a time so just do it.
		$nextRevision = $newRow->revision->getCollection()->getNextRevision( $newRow->revision );
		if ( $nextRevision === null ) {
			$nextLink = null;
		} else {
			$nextLink = $this->urlGenerator->diffLink(
				$nextRevision,
				$ctx->getTitle(),
				UUID::create( $newRes['workflowId'] )
			)->getLocalURL();
		}

		return [
			'new' => $newRes,
			'old' => $oldRes,
			'diff_content' => $differenceEngine->getDiffBody(),
			'links' => [
				'previous' => $prevLink,
				'next' => $nextLink,
			],
		];
	}
}
