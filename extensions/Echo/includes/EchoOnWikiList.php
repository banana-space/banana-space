<?php

/**
 * Implements EchoContainmentList interface for sourcing a list of items from a wiki
 * page. Uses the pages latest revision ID as cache key.
 */
class EchoOnWikiList implements EchoContainmentList {
	/**
	 * @var Title|null A title object representing the page to source the list from,
	 *                        or null if the page does not exist.
	 */
	protected $title;

	/**
	 * @param int $titleNs An NS_* constant representing the mediawiki namespace of the page
	 * @param string $titleString String portion of the wiki page title
	 */
	public function __construct( $titleNs, $titleString ) {
		$title = Title::newFromText( $titleString, $titleNs );
		if ( $title !== null && $title->getArticleID() ) {
			$this->title = $title;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getValues() {
		if ( !$this->title ) {
			return [];
		}

		$article = WikiPage::newFromID( $this->title->getArticleID() );
		if ( $article === null || !$article->exists() ) {
			return [];
		}
		$text = ContentHandler::getContentText( $article->getContent() );
		if ( $text === null ) {
			return [];
		}
		return array_filter( array_map( 'trim', explode( "\n", $text ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey() {
		if ( !$this->title ) {
			return '';
		}

		return (string)$this->title->getLatestRevID();
	}
}
