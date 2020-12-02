<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

/**
 * Factory for picking the right DOMHandler for a DOM element.
 * Porting note: this class is based on DOMHandlers.js and WikitextSerializer.getDOMHandler
 * PORT-FIXME: memoize handlers, maybe
 */
class DOMHandlerFactory {

	/**
	 * Get the DOMHandler that's appropriate for serializing a HTML tag.
	 *
	 * Porting note: this is the equivalent of DOMHandlers.tagHandlers[tag].
	 * @param string $tag
	 * @return DOMHandler|null
	 */
	public function newFromTagHandler( string $tag ): ?DOMHandler {
		switch ( $tag ) {
			case 'a':
				return new AHandler();
			case 'audio':
				return new MediaHandler();
			case 'b':
				return new QuoteHandler( "'''" );
			case 'body':
				return new BodyHandler();
			case 'br':
				return new BRHandler();
			case 'caption':
				return new CaptionHandler();
			case 'dd':
				return new DDHandler(); // multi-line dt/dd
			case 'dd_row':
				return new DDHandler( 'row' ); // single-line dt/dd
			case 'dl':
				return new ListHandler( [ 'dt', 'dd' ] );
			case 'dt':
				return new DTHandler();
			case 'figure':
				return new FigureHandler();
			case 'figure-inline':
				return new MediaHandler();
			case 'hr':
				return new HRHandler();
			case 'h1':
				return new HeadingHandler( '=' );
			case 'h2':
				return new HeadingHandler( '==' );
			case 'h3':
				return new HeadingHandler( '===' );
			case 'h4':
				return new HeadingHandler( '====' );
			case 'h5':
				return new HeadingHandler( '=====' );
			case 'h6':
				return new HeadingHandler( '======' );
			case 'i':
				return new QuoteHandler( "''" );
			case 'img':
				return new ImgHandler();
			case 'li':
				return new LIHandler();
			case 'link':
				return new LinkHandler();
			case 'meta':
				return new MetaHandler();
			case 'ol':
				return new ListHandler( [ 'li' ] );
			case 'p':
				return new PHandler();
			case 'pre':
				return new PreHandler(); // Wikitext indent pre generated with leading space
			case 'pre_html':
				return new HTMLPreHandler(); // HTML pre
			case 'span':
				return new SpanHandler();
			case 'table':
				return new TableHandler();
			case 'tbody':
				return new JustChildrenHandler();
			case 'td':
				return new TDHandler();
			case 'tfoot':
				return new JustChildrenHandler();
			case 'th':
				return new THHandler();
			case 'thead':
				return new JustChildrenHandler();
			case 'tr':
				return new TRHandler();
			case 'ul':
				return new ListHandler( [ 'li' ] );
			case 'video':
				return new MediaHandler();
			default:
				return null;
		}
	}

	/**
	 * Get a DOMHandler for an element node.
	 * @param DOMNode|null $node
	 * @return DOMHandler|null
	 */
	public function getDOMHandler( ?DOMNode $node ): ?DOMHandler {
		if ( !$node || !DOMUtils::isElt( $node ) ) {
			return new DOMHandler();
		}
		'@phan-var DOMElement $node';/** @var DOMElement $node */

		if ( WTUtils::isFirstEncapsulationWrapperNode( $node ) ) {
			return new EncapsulatedContentHandler();
		}

		$dp = DOMDataUtils::getDataParsoid( $node );

		// If available, use a specialized handler for serializing
		// to the specialized syntactic form of the tag.
		$handler = $this->newFromTagHandler( $node->nodeName . '_' . ( $dp->stx ?? null ) );

		// Unless a specialized handler is available, use the HTML handler
		// for html-stx tags. But, <a> tags should never serialize as HTML.
		if ( !$handler && ( $dp->stx ?? null ) === 'html' && $node->nodeName !== 'a' ) {
			return new FallbackHTMLHandler();
		}

		// If in a HTML table tag, serialize table tags in the table
		// using HTML tags, instead of native wikitext tags.
		if ( isset( WikitextConstants::$HTML['ChildTableTags'][$node->nodeName] )
			 && !isset( WikitextConstants::$ZeroWidthWikitextTags[$node->nodeName] )
			 && WTUtils::inHTMLTableTag( $node )
		) {
			return new FallbackHTMLHandler();
		}

		// If parent node is a list in html-syntax, then serialize
		// list content in html-syntax rather than wiki-syntax.
		if ( DOMUtils::isListItem( $node )
			 && DOMUtils::isList( $node->parentNode )
			 && WTUtils::isLiteralHTMLNode( $node->parentNode )
		) {
			return new FallbackHTMLHandler();
		}

		// Pick the best available handler
		return $handler ?: $this->newFromTagHandler( $node->nodeName ) ?: new FallbackHTMLHandler();
	}

}
