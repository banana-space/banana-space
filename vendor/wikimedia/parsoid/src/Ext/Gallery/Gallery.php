<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use DOMDocument;
use DOMElement;
use stdClass;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * Implements the php parser's `renderImageGallery` natively.
 *
 * Params to support (on the extension tag):
 * - showfilename
 * - caption
 * - mode
 * - widths
 * - heights
 * - perrow
 *
 * A proposed spec is at: https://phabricator.wikimedia.org/P2506
 */
class Gallery extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'Gallery',
			'tags' => [
				[
					'name' => 'gallery',
					'handler' => self::class,
				]
			],
			'styles' => [ 'mediawiki.page.gallery.styles' ]
		];
	}

	/**
	 * Parse the gallery caption.
	 * @param ParsoidExtensionAPI $extApi
	 * @param array $extArgs
	 * @return DOMElement|null
	 */
	private function pCaption( ParsoidExtensionAPI $extApi, array $extArgs ): ?DOMElement {
		$doc = $extApi->extArgToDOM( $extArgs, 'caption' );
		if ( !$doc ) {
			return null;
		}

		$body = DOMCompat::getBody( $doc );
		return $body;
	}

	/**
	 * Parse a single line of the gallery.
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $line
	 * @param int $lineStartOffset
	 * @param Opts $opts
	 * @return ParsedLine|null
	 */
	private static function pLine(
		ParsoidExtensionAPI $extApi, string $line, int $lineStartOffset,
		Opts $opts
	): ?ParsedLine {
		// Regexp from php's `renderImageGallery`
		if ( !preg_match( '/^([^|]+)(\|(?:.*))?$/D', $line, $matches ) ) {
			return null;
		}

		$titleStr = $matches[1];
		$imageOptStr = $matches[2] ?? '';

		// TODO: % indicates rawurldecode.

		$mode = Mode::byName( $opts->mode );

		$imageOpts = [
			"|{$mode->dimensions( $opts )}",
			// NOTE: We add "none" here so that this renders in the block form
			// (ie. figure) for an easier structure to manipulate.
			'|none',
			[ $imageOptStr, $lineStartOffset + strlen( $titleStr ) ],
		];

		$thumb = $extApi->renderMedia( $titleStr, $imageOpts );
		if ( !$thumb || $thumb->nodeName !== 'figure' ) {
			return null;
		}

		$doc = $thumb->ownerDocument;
		$rdfaType = $thumb->getAttribute( 'typeof' );

		// Detach from document
		DOMCompat::remove( $thumb );

		// Detach figcaption as well
		$figcaption = DOMCompat::querySelector( $thumb, 'figcaption' );
		if ( !$figcaption ) {
			$figcaption = $doc->createElement( 'figcaption' );
		} else {
			DOMCompat::remove( $figcaption );
		}

		if ( $opts->showfilename ) {
			// No need for error checking on this call since it was already
			// done in $extApi->renderMedia() above
			$title = $extApi->makeTitle(
				$titleStr,
				$extApi->getSiteConfig()->canonicalNamespaceId( 'file' )
			);
			$file = $title->getPrefixedDBKey();
			$galleryfilename = $doc->createElement( 'a' );
			$galleryfilename->setAttribute( 'href', $extApi->getTitleUri( $title ) );
			$galleryfilename->setAttribute( 'class', 'galleryfilename galleryfilename-truncate' );
			$galleryfilename->setAttribute( 'title', $file );
			$galleryfilename->appendChild( $doc->createTextNode( $file ) );
			$figcaption->insertBefore( $galleryfilename, $figcaption->firstChild );
		}

		$gallerytext = null;
		for ( $capChild = $figcaption->firstChild;
			 $capChild !== null;
			 $capChild = $capChild->nextSibling ) {
			if (
				DOMUtils::isText( $capChild ) &&
				preg_match( '/^\s*$/D', $capChild->nodeValue )
			) {
				// skip blank text nodes
				continue;
			}
			// Found a non-blank node!
			$gallerytext = $figcaption;
			break;
		}

		return new ParsedLine( $thumb, $gallerytext, $rdfaType );
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DOMDocument {
		$attrs = $extApi->extArgsToArray( $args );
		$opts = new Opts( $extApi, $attrs );

		$offset = $extApi->getExtTagOffsets()->innerStart();

		// Prepare the lines for processing
		$lines = explode( "\n", $content );
		$lines = array_map( function ( $line ) use ( &$offset ) {
				$lineObj = [ 'line' => $line, 'offset' => $offset ];
				$offset += strlen( $line ) + 1; // For the nl
				return $lineObj;
		}, $lines );

		$caption = $opts->caption ? $this->pCaption( $extApi, $args ) : null;
		$lines = array_map( function ( $lineObj ) use ( $extApi, $opts ) {
			return $this->pLine(
				$extApi, $lineObj['line'], $lineObj['offset'], $opts
			);
		}, $lines );

		// Drop invalid lines like "References: 5."
		$lines = array_filter( $lines, function ( $lineObj ) {
			return $lineObj !== null;
		} );

		$mode = Mode::byName( $opts->mode );
		$doc = $mode->render( $extApi, $opts, $caption, $lines );
		return $doc;
	}

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $node
	 * @return string
	 */
	private function contentHandler(
		ParsoidExtensionAPI $extApi, DOMElement $node
	): string {
		$content = "\n";
		for ( $child = $node->firstChild; $child; $child = $child->nextSibling ) {
			switch ( $child->nodeType ) {
			case XML_ELEMENT_NODE:
				DOMUtils::assertElt( $child );
				// Ignore if it isn't a "gallerybox"
				if (
					$child->nodeName !== 'li' ||
					$child->getAttribute( 'class' ) !== 'gallerybox'
				) {
					break;
				}
				$thumb = DOMCompat::querySelector( $child, '.thumb' );
				if ( !$thumb ) {
					break;
				}
				// FIXME: The below would benefit from a refactoring that
				// assumes the figure structure, as in the link handler.
				$elt = DOMUtils::selectMediaElt( $thumb );
				if ( $elt ) {
					// FIXME: Should we preserve the original namespace?  See T151367
					if ( $elt->hasAttribute( 'resource' ) ) {
						$resource = $elt->getAttribute( 'resource' );
						$content .= preg_replace( '#^\./#', '', $resource, 1 );
						// FIXME: Serializing of these attributes should
						// match the link handler so that values stashed in
						// data-mw aren't ignored.
						if ( $elt->hasAttribute( 'alt' ) ) {
							$alt = $elt->getAttribute( 'alt' );
							$content .= '|alt=' .
								$extApi->escapeWikitext( $alt, $child, $extApi::IN_MEDIA );
						}
						// The first "a" is for the link, hopefully.
						$a = DOMCompat::querySelector( $thumb, 'a' );
						if ( $a && $a->hasAttribute( 'href' ) ) {
							$href = $a->getAttribute( 'href' );
							if ( $href !== $resource ) {
								$href = preg_replace( '#^\./#', '', $href, 1 );
								$content .= '|link=' .
									$extApi->escapeWikitext( $href, $child, $extApi::IN_MEDIA );
							}
						}
					}
				} else {
					// TODO: Previously (<=1.5.0), we rendered valid titles
					// returning mw:Error (apierror-filedoesnotexist) as
					// plaintext.  Continue to serialize this content until
					// that version is no longer supported.
					$content .= $thumb->textContent;
				}
				$gallerytext = DOMCompat::querySelector( $child, '.gallerytext' );
				if ( $gallerytext ) {
					$showfilename = DOMCompat::querySelector( $gallerytext, '.galleryfilename' );
					if ( $showfilename ) {
						DOMCompat::remove( $showfilename ); // Destructive to the DOM!
					}
					$caption = $extApi->domChildrenToWikitext( $gallerytext,
						$extApi::IN_IMG_CAPTION,
						true /* singleLine */
					);
					// Drop empty captions
					if ( !preg_match( '/^\s*$/D', $caption ) ) {
						$content .= '|' . $caption;
					}
				}
				$content .= "\n";
				break;
			case XML_TEXT_NODE:
			case XML_COMMENT_NODE:
				// Ignore it
				break;
			default:
				PHPUtils::unreachable( 'should not be here!' );
				break;
			}
		}
		return $content;
	}

	/** @inheritDoc */
	public function domToWikitext(
		ParsoidExtensionAPI $extApi, DOMElement $node, bool $wrapperUnmodified
	) {
		$dataMw = DOMDataUtils::getDataMw( $node );
		$dataMw->attrs = $dataMw->attrs ?? new stdClass;
		// Handle the "gallerycaption" first
		$galcaption = DOMCompat::querySelector( $node, 'li.gallerycaption' );
		if (
			$galcaption &&
			// FIXME: VE should signal to use the HTML by removing the
			// `caption` from data-mw.
			!is_string( $dataMw->attrs->caption ?? null )
		) {
			$dataMw->attrs->caption = $extApi->domChildrenToWikitext( $galcaption,
				$extApi::IN_IMG_CAPTION | $extApi::IN_OPTION,
				false /* singleLine */
			);
		}
		$startTagSrc = $extApi->extStartTagToWikitext( $node );

		if ( !$dataMw->body ) {
			return $startTagSrc; // We self-closed this already.
		} else {
			// FIXME: VE should signal to use the HTML by removing the
			// `extsrc` from the data-mw.
			if ( is_string( $dataMw->body->extsrc ?? null ) ) {
				$content = $dataMw->body->extsrc;
			} else {
				$content = $this->contentHandler( $extApi, $node );
			}
			return $startTagSrc . $content . '</' . $dataMw->name . '>';
		}
	}

	/** @inheritDoc */
	public function modifyArgDict(
		ParsoidExtensionAPI $extApi, object $argDict
	): void {
		// FIXME: Only remove after VE switches to editing HTML.
		if ( $extApi->getSiteConfig()->nativeGalleryEnabled() ) {
			// Remove extsrc from native extensions
			unset( $argDict->body->extsrc );

			// Remove the caption since it's redundant with the HTML
			// and we prefer editing it there.
			unset( $argDict->attrs->caption );
		}
	}
}
