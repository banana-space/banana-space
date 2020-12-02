<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

/**
 * Stand-alone XMLSerializer for DOM3 documents.
 *
 * The output is identical to standard XHTML5 DOM serialization, as given by
 * http://www.w3.org/TR/html-polyglot/
 * and
 * https://html.spec.whatwg.org/multipage/syntax.html#serialising-html-fragments
 * except that we may quote attributes with single quotes, *only* where that would
 * result in more compact output than the standard double-quoted serialization.
 */
class XMLSerializer {

	// https://html.spec.whatwg.org/#serialising-html-fragments
	private static $alsoSerializeAsVoid = [
		'basefont' => true,
		'bgsound' => true,
		'frame' => true,
		'keygen' => true
	];

	/** HTML5 elements with raw (unescaped) content */
	private static $hasRawContent = [
		'style' => true,
		'script' => true,
		'xmp' => true,
		'iframe' => true,
		'noembed' => true,
		'noframes' => true,
		'plaintext' => true,
		'noscript' => true
	];

	/**
	 * Elements that strip leading newlines
	 * http://www.whatwg.org/specs/web-apps/current-work/multipage/the-end.html#html-fragment-serialization-algorithm
	 * @namespace
	 * @private
	 */
	private static $newlineStrippingElements = [
		'pre' => true,
		'textarea' => true,
		'listing' => true
	];

	private static $entityEncodings = [
		'<' => '&lt;',
		'&' => '&amp;',
		'"' => '&quot;',
		"'" => '&apos;',
	];

	/**
	 * HTML entity encoder helper. Replaces calls to the entities npm module.
	 * Only supports the few entities we'll actually need: <&'"
	 * @param string $raw Input string
	 * @param string $encodeChars String with the characters that should be encoded
	 * @return string
	 */
	private static function encodeHtmlEntities( string $raw, string $encodeChars ): string {
		$encodings = array_intersect_key( self::$entityEncodings, array_flip( str_split( $encodeChars ) ) );
		return strtr( $raw, $encodings );
	}

	/**
	 * Serialize an HTML DOM3 node to XHTML. The XHTML and associated information will be fed
	 * step-by-step to the callback given in $accum.
	 * @param DOMNode $node
	 * @param array $options See {@link XMLSerializer::serialize()}
	 * @param callable $accum function( $bit, $node, $flag )
	 *   - $bit: (string) piece of HTML code
	 *   - $node: (DOMNode) ??
	 *   - $flag: (string|null) 'start' or 'end' (??)
	 * @return void
	 */
	private static function serializeToString( DOMNode $node, array $options, callable $accum ): void {
		$child = null;
		if ( !empty( $options['tunnelFosteredContent'] ) &&
			isset( WikitextConstants::$HTML['FosterablePosition'][$node->nodeName] )
		) {
			// Tunnel fosterable metas as comments.
			// This is analogous to what is done when treebuilding.
			$ownerDoc = $node->ownerDocument;
			$allowedTags = WikitextConstants::$HTML['TableContentModels'][$node->nodeName];
			$child = $node->firstChild;
			while ( $child ) {
				$next = $child->nextSibling;
				if ( DOMUtils::isText( $child ) ) {
					Assert::invariant( DOMUtils::isIEW( $child ), 'Only expecting whitespace!' );
				} elseif (
					$child instanceof DOMElement &&
					!in_array( $child->nodeName, $allowedTags, true )
				) {
					Assert::invariant( $child->nodeName === 'meta', 'Only fosterable metas expected!' );
					$as = [];
					foreach ( DOMCompat::attributes( $child ) as $attr ) {
						$as[] = [ $attr->name, $attr->value ];
					}
					$comment = WTUtils::fosterCommentData( $child->getAttribute( 'typeof' ), $as, true );
					$node->replaceChild( $ownerDoc->createComment( $comment ), $child );
				}
				$child = $next;
			}
		}
		switch ( $node->nodeType ) {
			case XML_ELEMENT_NODE:
				DOMUtils::assertElt( $node );
				$child = $node->firstChild;
				$nodeName = $node->tagName;
				$localName = $node->localName;
				$accum( '<' . $localName, $node );
				foreach ( DOMCompat::attributes( $node ) as $attr ) {
					if ( $options['smartQuote']
						// More double quotes than single quotes in value?
						&& substr_count( $attr->value, '"' ) > substr_count( $attr->value, "'" )
					) {
						// use single quotes
						$accum( ' ' . $attr->name . "='"
							. self::encodeHtmlEntities( $attr->value, "<&'" ) . "'",
							$node );
					} else {
						// use double quotes
						$accum( ' ' . $attr->name . '="'
							. self::encodeHtmlEntities( $attr->value, '<&"' ) . '"',
							$node );
					}
				}
				if ( $child || (
					!isset( WikitextConstants::$HTML['VoidTags'][$nodeName] ) &&
					!isset( self::$alsoSerializeAsVoid[$nodeName] )
				) ) {
					$accum( '>', $node, 'start' );
					// if is cdata child node
					if ( isset( self::$hasRawContent[$nodeName] ) ) {
						// TODO: perform context-sensitive escaping?
						// Currently this content is not normally part of our DOM, so
						// no problem. If it was, we'd probably have to do some
						// tag-specific escaping. Examples:
						// * < to \u003c in <script>
						// * < to \3c in <style>
						// ...
						if ( $child ) {
							$accum( $child->nodeValue, $node );
						}
					} else {
						if ( $child && isset( self::$newlineStrippingElements[$localName] )
							&& $child->nodeType === XML_TEXT_NODE && preg_match( '/^\n/', $child->nodeValue )
						) {
							/* If current node is a pre, textarea, or listing element,
							 * and the first child node of the element, if any, is a
							 * Text node whose character data has as its first
							 * character a U+000A LINE FEED (LF) character, then
							 * append a U+000A LINE FEED (LF) character. */
							$accum( "\n", $node );
						}
						while ( $child ) {
							self::serializeToString( $child, $options, $accum );
							$child = $child->nextSibling;
						}
					}
					$accum( '</' . $localName . '>', $node, 'end' );
				} else {
					$accum( '/>', $node, 'end' );
				}
				return;

			case XML_DOCUMENT_NODE:
			case XML_DOCUMENT_FRAG_NODE:
				'@phan-var \DOMDocument|\DOMDocumentFragment $node';
				// @var \DOMDocument|\DOMDocumentFragment $node
				$child = $node->firstChild;
				while ( $child ) {
					self::serializeToString( $child, $options, $accum );
					$child = $child->nextSibling;
				}
				return;

			case XML_TEXT_NODE:
				'@phan-var \DOMText $node'; // @var \DOMText $node
				$accum( self::encodeHtmlEntities( $node->data, '<&' ), $node );
				return;

			case XML_COMMENT_NODE:
				// According to
				// http://www.w3.org/TR/DOM-Parsing/#dfn-concept-serialize-xml
				// we could throw an exception here if node.data would not create
				// a "well-formed" XML comment.  But we use entity encoding when
				// we create the comment node to ensure that node.data will always
				// be okay; see DOMUtils.encodeComment().
				'@phan-var \DOMComment $node'; // @var \DOMComment $node
				$accum( '<!--' . $node->data . '-->', $node );
				return;

			default:
				$accum( '??' . $node->nodeName, $node );
		}
	}

	/**
	 * Add data to an output/memory array (used when serialize() was called with the
	 * captureOffsets flag).
	 * @param array &$out Output array, see {@link self::serialize()} for details on the
	 *   'html' and 'offset' fields. The other fields (positions are 0-based
	 *   and refer to UTF-8 byte indices):
	 *   - start: position in the HTML of the end of the opening tag of <body>
	 *   - last: (DOMNode) last "about sibling" of the currently processed element
	 *     (see {@link WTUtils::getAboutSiblings()}
	 *   - uid: the ID of the element
	 * @param string $bit A piece of the HTML string
	 * @param DOMNode $node The DOM node $bit is a part of
	 * @param string|null $flag 'start' when receiving the final part of the opening tag
	 *   of an element, 'end' when receiving the final part of the closing tag of an element
	 *   or the final part of a self-closing element.
	 */
	private static function accumOffsets(
		array &$out, string $bit, DOMNode $node, ?string $flag = null
	): void {
		if ( DOMUtils::isBody( $node ) ) {
			$out['html'] .= $bit;
			if ( $flag === 'start' ) {
				$out['start'] = strlen( $out['html'] );
			} elseif ( $flag === 'end' ) {
				$out['start'] = null;
				$out['uid'] = null;
			}
		} elseif ( !( $node instanceof DOMElement ) || $out['start'] === null
			|| !DOMUtils::isBody( $node->parentNode )
		) {
			// In case you're wondering, out.start may never be set if body
			// isn't a child of the node passed to serializeToString, or if it
			// is the node itself but options.innerXML is true.
			$out['html'] .= $bit;
			if ( $out['uid'] !== null ) {
				$out['offsets'][$out['uid']]['html'][1] += strlen( $bit );
			}
		} else {
			$newUid = $node->hasAttribute( 'id' ) ? $node->getAttribute( 'id' ) : null;
			// Encapsulated siblings don't have generated ids (but may have an id),
			// so associate them with preceding content.
			if ( $newUid && $newUid !== $out['uid'] && !$out['last'] ) {
				if ( !WTUtils::isEncapsulationWrapper( $node ) ) {
					$out['uid'] = $newUid;
				} elseif ( WTUtils::isFirstEncapsulationWrapperNode( $node ) ) {
					$about = $node->getAttribute( 'about' );
					$aboutSiblings = WTUtils::getAboutSiblings( $node, $about );
					$out['last'] = end( $aboutSiblings );
					$out['uid'] = $newUid;
				}
			}
			if ( $out['last'] === $node && $flag === 'end' ) {
				$out['last'] = null;
			}
			Assert::invariant( $out['uid'] !== null, 'uid cannot be null' );
			if ( !isset( $out['offsets'][$out['uid']] ) ) {
				$dt = strlen( $out['html'] ) - $out['start'];
				$out['offsets'][$out['uid']] = [ 'html' => [ $dt, $dt ] ];
			}
			$out['html'] .= $bit;
			$out['offsets'][$out['uid']]['html'][1] += strlen( $bit );
		}
	}

	/**
	 * Serialize an HTML DOM3 node to an XHTML string.
	 *
	 * @param DOMNode $node
	 * @param array $options
	 *   - smartQuote (bool, default true): use single quotes for attributes when that's less escaping
	 *   - innerXML (bool, default false): only serialize the contents of $node, exclude $node itself
	 *   - captureOffsets (bool, default false): return tag position data (see below)
	 *   - addDoctype (bool default true): prepend a DOCTYPE when a full HTML document is serialized
	 * @return array An array with the following data:
	 *   - html: the serialized HTML
	 *   - offsets: the start and end position of each element in the HTML, in a
	 *     [ $uid => [ 'html' => [ $start, $end ] ], ... ] format where $uid is the element's
	 *     Parsoid ID, $start is the 0-based index of the first character of the element and
	 *     $end is the index of the first character of the opening tag of the next sibling element,
	 *     or the index of the last character of the element's closing tag if there is no next
	 *     sibling. The positions are relative to the end of the opening <body> tag
	 *     (the DOCTYPE header is not counted), and only present when the captureOffsets flag is set.
	 */
	public static function serialize( DOMNode $node, array $options = [] ): array {
		$options += [
			'smartQuote' => true,
			'innerXML' => false,
			'captureOffsets' => false,
			'addDoctype' => true,
		];
		if ( $node instanceof DOMDocument ) {
			$node = $node->documentElement;
		}
		$out = [ 'html' => '', 'offsets' => [], 'start' => null, 'uid' => null, 'last' => null ];
		$accum = $options['captureOffsets']
			? function ( string $bit, DOMNode $node, ?string $flag = null ) use ( &$out ): void {
				self::accumOffsets( $out, $bit, $node, $flag );
			}
			: function ( string $bit ) use ( &$out ): void {
				$out['html'] .= $bit;
			};

		if ( $options['innerXML'] ) {
			for ( $child = $node->firstChild; $child; $child = $child->nextSibling ) {
				self::serializeToString( $child, $options, $accum );
			}
		} else {
			self::serializeToString( $node, $options, $accum );
		}
		// Ensure there's a doctype for documents.
		if ( !$options['innerXML'] && $node->nodeName === 'html' && $options['addDoctype'] ) {
			$out['html'] = "<!DOCTYPE html>\n" . $out['html'];
		}
		// Verify UTF-8 soundness (transitional check for PHP port)
		PHPUtils::assertValidUTF8( $out['html'] );
		// Drop the bookkeeping
		unset( $out['start'], $out['uid'], $out['last'] );
		if ( !$options['captureOffsets'] ) {
			unset( $out['offsets'] );
		}
		return $out;
	}

}
