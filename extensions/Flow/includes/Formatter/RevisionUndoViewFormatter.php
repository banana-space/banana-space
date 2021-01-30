<?php

namespace Flow\Formatter;

use DifferenceEngine;
use IContextSource;
use TextContent;

class RevisionUndoViewFormatter {
	protected $revisionViewFormatter;

	public function __construct( RevisionViewFormatter $revisionViewFormatter ) {
		$this->revisionViewFormatter = $revisionViewFormatter;
	}

	/**
	 * Undoes the change that occurred between $start and $stop
	 * @param FormatterRow $start
	 * @param FormatterRow $stop
	 * @param FormatterRow $current
	 * @param IContextSource $context
	 * @return array
	 */
	public function formatApi(
		FormatterRow $start,
		FormatterRow $stop,
		FormatterRow $current,
		IContextSource $context
	) {
		$currentWikitext = $current->revision->getContentInWikitext();

		$undoContent = $this->getUndoContent(
			$start->revision->getContentInWikitext(),
			$stop->revision->getContentInWikitext(),
			$currentWikitext
		);

		$differenceEngine = new DifferenceEngine();
		$differenceEngine->setContent(
			new TextContent( $currentWikitext ),
			new TextContent( $undoContent )
		);

		$this->revisionViewFormatter->setContentFormat( 'wikitext' );

		// @todo if stop === current we could do a little less processing
		return [
			'start' => $this->revisionViewFormatter->formatApi( $start, $context ),
			'stop' => $this->revisionViewFormatter->formatApi( $stop, $context ),
			'current' => $this->revisionViewFormatter->formatApi( $current, $context ),
			'undo' => [
				'possible' => $undoContent !== false,
				'content' => $undoContent,
				'diff_content' => $differenceEngine->getDiffBody(),
			],
			'articleTitle' => $start->workflow->getArticleTitle(),
		];
	}

	protected function getUndoContent( $startContent, $stopContent, $currentContent ) {
		if ( $currentContent === $stopContent ) {
			return $startContent;
		} else {
			// 3-way merge
			$ok = wfMerge( $stopContent, $startContent, $currentContent, $result );
			if ( $ok ) {
				return $result;
			} else {
				return false;
			}
		}
	}
}
