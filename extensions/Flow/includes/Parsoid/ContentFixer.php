<?php

namespace Flow\Parsoid;

use DOMDocument;
use DOMXPath;
use Flow\Conversion\Utils;
use Flow\Exception\FlowException;
use Flow\Model\AbstractRevision;
use Title;

class ContentFixer {
	/**
	 * @var Fixer[] Array of Fixer objects
	 */
	protected $contentFixers = [];

	/**
	 * Accepts multiple content fixers.
	 *
	 * @param Fixer $contentFixer,...
	 * @throws FlowException When provided arguments are not an instance of Fixer
	 */
	public function __construct( Fixer $contentFixer /* [, Fixer $contentFixer2 [, ...]] */ ) {
		$this->contentFixers = func_get_args();

		// validate data
		foreach ( $this->contentFixers as $contentFixer ) {
			if ( !$contentFixer instanceof Fixer ) {
				throw new FlowException( 'Invalid content fixer', 'default' );
			}
		}
	}

	/**
	 * @param AbstractRevision $revision
	 * @return string
	 */
	public function getContent( AbstractRevision $revision ) {
		return $this->apply(
			$revision->getContent( 'html' ),
			$revision->getCollection()->getTitle()
		);
	}

	/**
	 * Applies all contained content fixers to the provided HTML content.
	 * The resulting content is then suitable for display to the end user.
	 *
	 * @param string $content Html
	 * @param Title $title
	 * @return string Html
	 */
	public function apply( $content, Title $title ) {
		$dom = self::createDOM( $content );
		$this->applyToDom( $dom, $title );
		return Utils::getInnerHtml( $dom->getElementsByTagName( 'body' )->item( 0 ) );
	}

	/**
	 * Applies all content fixers to the provided DOMDocument.
	 * Like apply(), but modifies a DOM in place rather than parsing and reserializing a string.
	 *
	 * @param DOMDocument $dom
	 * @param Title $title
	 */
	public function applyToDom( DOMDocument $dom, Title $title ) {
		$xpath = new DOMXPath( $dom );
		foreach ( $this->contentFixers as $i => $contentFixer ) {
			$found = $xpath->query( $contentFixer->getXPath() );
			if ( !$found ) {
				wfDebugLog( 'Flow', __METHOD__ . ': Invalid XPath from ' . get_class( $contentFixer ) . ' of: ' .
					$contentFixer->getXPath() );
				unset( $this->contentFixers[$i] );
				continue;
			}

			foreach ( $found as $node ) {
				$contentFixer->apply( $node, $title );
			}
		}
	}

	/**
	 * Creates a DOM with extra considerations for BC with
	 * previous parsoid content
	 *
	 * @param string $content HTML from parsoid
	 * @return DOMDocument
	 * @throws \Flow\Exception\WikitextException
	 */
	public static function createDOM( $content ) {
		 // The body tag is required otherwise <meta> tags at the top are
		 // magic'd into <head> rather than kept with the content.
		if (
			substr( $content, 0, 5 ) !== '<body'
			&& substr( $content, 0, 9 ) !== '<!DOCTYPE'
			// We might have set the html/head/base href in AbstractRevision#getContent, so
			// make one more check before body wrapping the content.
			&& substr( $content, 0, 12 ) !== '<html><head>'
		) {
			// BC: content currently comes from parsoid and is stored
			// wrapped in <body> tags, but prior to I0d9659f we were
			// storing only the contents and not the body tag itself.
			$content = "<body>$content</body>";
		}
		return Utils::createDOM( $content );
	}
}
