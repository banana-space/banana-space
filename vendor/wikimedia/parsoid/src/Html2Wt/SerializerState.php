<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt;

use Composer\Semver\Semver;
use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Html2Wt\ConstrainedText\ConstrainedText;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

/**
 * State object for the wikitext serializers.
 */
class SerializerState {

	/**
	 * Regexp for checking if what we have consumed wikimarkup that has special meaning at the
	 * beginning of the line, and is indeed at the beginning of the line (modulo comments and
	 * other ignored elements).
	 *
	 * @return string
	 */
	private function solWikitextRegexp(): string {
		static $solWikitextRegexp = null;
		if ( $solWikitextRegexp === null ) {
			$sol = PHPUtils::reStrip(
				$this->env->getSiteConfig()->solTransparentWikitextNoWsRegexp(),
				'@'
			);
			$solWikitextRegexp = '@' .
				'^((?:' . $sol . '|' .
				# SSS FIXME: What about onlyinclude and noinclude?
				'<includeonly>.*?</includeonly>' .
				')*)' .
				'([\ \*#:;{\|!=].*)$' .
				'@D';
		}
		return $solWikitextRegexp;
	}

	/**
	 * Regexp for checking whether we are at the start of the line (modulo comments and
	 * other ignored elements).
	 *
	 * @return string
	 */
	private function solRegexp(): string {
		static $solRegexp = null;
		if ( $solRegexp === null ) {
			$sol = PHPUtils::reStrip(
				$this->env->getSiteConfig()->solTransparentWikitextNoWsRegexp(),
				'@'
			);
			$solRegexp = '@' .
				'(^|\\n)' .
				'(' .
				# SSS FIXME: What about onlyinclude and noinclude?
				'<includeonly>.*?</includeonly>' .
				'|' . $sol .
				')*$' .
				'@D';
		}
		return $solRegexp;
	}

	/**
	 * Are we currently running round-trip tests?  If yes, then we know
	 * there won't be any edits and we more aggressively try to use original
	 * source and source flags during serialization since this is a test of
	 * Parsoid's efficacy in preserving information.
	 * @var bool
	 */
	public $rtTestMode = true;

	/**
	 * Separator information:
	 * - constraints (array<array|int>|null): min/max number of newlines
	 * - src (string|null): collected separator text from DOM text/comment nodes
	 * - lastSourceNode (?DOMNode): Seems to be bookkeeping to make sure we don't reuse
	 *     original separators when `emitChunk` is called
	 *     consecutively on the same node.  However, it also
	 *     differs from `state.prevNode` in that it only gets
	 *     updated when a node calls `emitChunk` so that nodes
	 *     serializing `justChildren` don't mix up `buildSep`.
	 * PORT-FIXME: could use a dedicated class
	 * @var stdClass
	 */
	public $sep;

	/**
	 * Is the serializer at the start of a new wikitext line?
	 * @var bool
	 */
	public $onSOL = true;

	/**
	 * True when wts kicks off, false after the first char has been output
	 * SSS FIXME: Can this be done away with in some way?
	 * @var bool
	 */
	public $atStartOfOutput = true;

	/**
	 * Is the serializer currently handling link content (children of `<a>`)?
	 * @var bool
	 */
	public $inLink = false;

	/**
	 * Is the serializer currently handling caption content?
	 * @var bool
	 */
	public $inCaption = false;

	/**
	 * Is the serializer currently handling an indent-pre tag?
	 * @var bool
	 */
	public $inIndentPre = false;

	/**
	 * Is the serializer currently handling a html-pre tag?
	 * @var bool
	 */
	public $inHTMLPre = false;

	/**
	 * Is the serializer currently handling a tag that the PHP parser
	 * treats as a block tag?
	 * @var bool
	 */
	public $inPHPBlock = false;

	/**
	 * Is the serializer being invoked recursively to serialize a
	 * template-generated attribute (via `WSP.getAttributeValue`'s
	 * template handling).  If so, we should suppress some
	 * serialization escapes, like autolink protection, since
	 * these are not valid for attribute values.
	 * @var bool
	 */
	public $inAttribute = false;

	/**
	 * Is the serializer currently processing a subtree that has been
	 * modified compared to original content (ex: via VE / CX)?
	 *
	 * @var bool
	 */
	public $inModifiedContent;

	/**
	 * Did we introduce nowikis for indent-pre protection?
	 * If yes, we might run a post-pass to strip useless ones.
	 * @var bool
	 */
	public $hasIndentPreNowikis = false;

	/**
	 * Did we introduce nowikis to preserve quote semantics?
	 * If yes, we might run a post-pass to strip useless ones.
	 * @var bool
	 */
	public $hasQuoteNowikis = false;

	/**
	 * Did we introduce `<nowiki />`s?
	 * If yes, we do a postpass to remove unnecessary trailing ones.
	 * @var bool
	 */
	public $hasSelfClosingNowikis = false;

	/**
	 * Did we introduce nowikis around `=.*=` text?
	 * If yes, we do a postpass to remove unnecessary escapes.
	 * @var bool
	 */
	public $hasHeadingEscapes = false;

	/**
	 * Records the nesting level of wikitext tables
	 * @var int
	 */
	public $wikiTableNesting = 0;

	/**
	 * Stack of wikitext escaping handlers -- these handlers are responsible
	 * for smart escaping when the surrounding wikitext context is known.
	 * @var callable[] See {@link serializeChildren()}
	 */
	public $wteHandlerStack = [];

	/**
	 * This array is used by the wikitext escaping algorithm -- represents
	 * a "single line" of output wikitext as represented by a block node in
	 * the DOM.
	 * - firstNode (?DOMNode): first DOM node processed on this line
	 * - text (string): output so far from all nodes on the current line
	 * - chunks (ConstrainedText[]): list of chunks comprising the current line
	 * @var stdClass
	 * XXX: replace with output buffering per line
	 * PORT-FIXME: could use a dedicated class
	 */
	public $currLine;

	/**
	 * Stack used to enforce single-line context
	 * @var SingleLineContext
	 */
	public $singleLineContext;

	/**
	 * Text to be emitted at the start of file, for redirects
	 * @var string|null
	 */
	public $redirectText = null;

	/** @var WikitextSerializer */
	public $serializer;

	/** @var ParsoidExtensionAPI */
	public $extApi;

	/** @var ConstrainedText|string The serialized output */
	public $out = '';

	/**
	 * Whether to use heuristics to determine if a list item, heading, table cell, etc.
	 * should have whitespace inserted after the "*#=|!" wikitext chars? This is normally
	 * true by default, but not so if HTML content version is older than 1.7.0.
	 * In practice, we are now at version 2.1, but Flow stores HTML, so till Flow migrates
	 * all its content over to a later version, we need a boolean flag.
	 * @var bool
	 */
	public $useWhitespaceHeuristics;

	/**
	 * Are we in selective serialization mode?
	 * @see SelectiveSerializer
	 * @var bool
	 */
	public $selserMode;

	/** @var SelserData */
	private $selserData;

	/**
	 * If in selser mode, while processing a node, do we know if
	 * its previous node has not been modified in an edit?
	 * @var bool
	 */
	public $prevNodeUnmodified;

	/**
	 * If in selser mode, while processing a node, do we know if
	 * it has not been modified in an edit?
	 * @var bool
	 */
	public $currNodeUnmodified;

	/**
	 * Should we run the wikitext escaping code on the wikitext chunk
	 * that will be emitted? True unless we are in HTML <pre>.
	 * @var bool
	 */
	public $escapeText = false;

	/**
	 * Used as fast patch for special protected characters in WikitextEscapeHandlers and
	 * comes from LanguageVariantHandler
	 * @var string|null
	 */
	public $protect;

	/** @var Env */
	private $env;

	/** @var DOMElement */
	private $prevNode;

	/**
	 * Log prefix to use in trace output
	 * @var string
	 */
	private $logPrefix = 'OUT:';

	/**
	 * @param WikitextSerializer $serializer
	 * @param array $options
	 */
	public function __construct( WikitextSerializer $serializer, array $options = [] ) {
		$this->env = $serializer->env;
		$this->serializer = $serializer;
		$this->extApi = new ParsoidExtensionAPI( $this->env, [ 'html2wt' => [ 'state' => $this ] ] );
		foreach ( $options as $name => $option ) {
			// PORT-FIXME validate
			if ( !( $option instanceof Env ) ) {
				$this->$name = Utils::clone( $option );
			}
		}
		$this->resetCurrLine( null );
		$this->singleLineContext = new SingleLineContext();
		$this->resetSep();
	}

	/**
	 * @note Porting note: this replaces direct access
	 * @return Env
	 */
	public function getEnv(): Env {
		return $this->env;
	}

	/**
	 * Initialize a few boolean flags based on serialization mode.
	 * FIXME: Ideally, this should be private. Requires shuffing around
	 * where SerializerState is constructed so that $selserMode is known
	 * at the time of construction.
	 * @private for use by WikitextSerializer only
	 * @param bool $selserMode Are we running selective serialization?
	 */
	public function initMode( bool $selserMode ): void {
		$this->useWhitespaceHeuristics =
			Semver::satisfies( $this->env->getInputContentVersion(), '>=1.7.0' );
		$this->selserMode = $selserMode;
		$this->rtTestMode = $this->rtTestMode && !$this->selserMode; // Always false in selser mode.
	}

	/**
	 * Appends the seperator source and updates the SOL state if necessary.
	 * @param string $src
	 */
	public function appendSep( string $src ): void {
		$this->sep->src = ( $this->sep->src ?: '' ) . $src;
		$this->sepIntroducedSOL( $src );
	}

	/**
	 * Cycle the state after processing a node.
	 * @param DOMNode $node
	 */
	public function updateSep( DOMNode $node ): void {
		$this->sep->lastSourceNode = $node;
	}

	private function resetSep() {
		$this->sep = PHPUtils::arrayToObject( [
			'constraints' => null,
			'src' => null,
			'lastSourceNode' => null,
		] );
	}

	/**
	 * Reset the current line state.
	 * @param DOMNode|null $node
	 */
	private function resetCurrLine( ?DOMNode $node ): void {
		$this->currLine = (object)[
			'text' => '',
			'chunks' => [],
			'firstNode' => $node
		];
	}

	/**
	 * Process and emit a line of ConstrainedText chunks, adjusting chunk boundaries as necessary.
	 * (Start of line and end of line are always safe for ConstrainedText chunks, so we don't need
	 * to buffer more than the last line.)
	 */
	private function flushLine(): void {
		$this->out .= ConstrainedText::escapeLine( $this->currLine->chunks );
		$this->currLine->chunks = [];
	}

	/**
	 * Extracts a subset of the page source bound by the supplied indices.
	 * @param int $start Start offset, in bytes
	 * @param int $end End offset, in bytes
	 * @return string|null
	 */
	public function getOrigSrc( int $start, int $end ): ?string {
		Assert::invariant( $this->selserMode, 'SerializerState::$selserMode must be set' );
		if (
			$start <= $end &&
			// FIXME: Having a $start greater than the source length is
			// probably a canary for corruption.  Maybe we should be throwing
			// here instead.  See T240053
			$start <= strlen( $this->selserData->oldText )
		) {
			return substr( $this->selserData->oldText, $start, $end - $start );
		} else {
			return null;
		}
	}

	/**
	 * Like it says on the tin.
	 * @param DOMNode $node
	 */
	public function updateModificationFlags( DOMNode $node ): void {
		$this->prevNodeUnmodified = $this->currNodeUnmodified;
		$this->currNodeUnmodified = false;
		$this->prevNode = $node;
	}

	/**
	 * Separators put us in SOL state.
	 * @param string $sep
	 */
	private function sepIntroducedSOL( string $sep ): void {
		// Don't get tripped by newlines in comments!  Be wary of nowikis added
		// by makeSepIndentPreSafe on the last line.
		if ( substr( preg_replace( Utils::COMMENT_REGEXP, '', $sep ), -1 ) === "\n" ) {
			// Since we are stashing away newlines for emitting
			// before the next element, we are in SOL state wrt
			// the content of that next element.
			//
			// FIXME: The only serious caveat is if all these newlines
			// will get stripped out in the context of any parent node
			// that suppress newlines (ex: <li> nodes that are forcibly
			// converted to non-html wikitext representation -- newlines
			// will get suppressed in those context). We currently don't
			// handle arbitrary HTML which cause these headaches. And,
			// in any case, we might decide to emit such HTML as native
			// HTML to avoid these problems. To be figured out later when
			// it is a real issue.
			$this->onSOL = true;
		}
	}

	/**
	 * Accumulates chunks on the current line.
	 * @param ConstrainedText $text
	 * @param DOMNode $node
	 */
	private function pushToCurrLine( ConstrainedText $text, DOMNode $node ) {
		// TODO $node is probably not needed since ConstrainedText already includes it
		$this->currLine->chunks[] = $text;
	}

	/**
	 * Pushes the seperator to the current line and resets the separator state.
	 * @param string $sep
	 * @param DOMNode $node
	 * @param string $debugPrefix
	 */
	private function emitSep( string $sep, DOMNode $node, string $debugPrefix ): void {
		$sep = ConstrainedText::cast( $sep, $node );

		// Replace newlines if we're in a single-line context
		if ( $this->singleLineContext->enforced() ) {
			$sep->text = preg_replace( '/\n/', ' ', $sep->text );
		}

		$this->pushToCurrLine( $sep, $node );

		// Reset separator state
		$this->resetSep();
		$this->updateSep( $node );

		$this->sepIntroducedSOL( $sep->text );

		$this->serializer->trace( '--->', $debugPrefix, function () use ( $sep ) {
			return PHPUtils::jsonEncode( $sep->text );
		} );
	}

	/**
	 * Determines if we can use the original seperator for this node or if we
	 * need to build one based on its constraints, and then emits it.
	 *
	 * The following comment applies to `origSepUsable` but is placed outside the
	 * function body since character count (including comments) can prevent
	 * inlining in older versions of v8 (node < 8.3).
	 *
	 * ---
	 *
	 * When block nodes are deleted, the deletion affects whether unmodified
	 * newline separators between a pair of unmodified P tags can be reused.
	 *
	 * Example:
	 * ```
	 * Original WT  : "<div>x</div>foo\nbar"
	 * Original HTML: "<div>x</div><p>foo</p>\n<p>bar</p>"
	 * Edited HTML  : "<p>foo</p>\n<p>bar</p>"
	 * Annotated DOM: "<mw:DiffMarker is-block><p>foo</p>\n<p>bar</p>"
	 * Expected WT  : "foo\n\nbar"
	 * ```
	 *
	 * Note the additional newline between "foo" and "bar" even though originally,
	 * there was just a single newline.
	 *
	 * So, even though the two P tags and the separator between them is
	 * unmodified, it is insufficient to rely on just that. We have to look at
	 * what has happened on the two wikitext lines onto which the two P tags
	 * will get serialized.
	 *
	 * Now, if you check the code for `nextToDeletedBlockNodeInWT`, that code is
	 * not really looking at ALL the nodes before/after the nodes that could
	 * serialize onto the wikitext lines. It is looking at the immediately
	 * adjacent nodes, i.e. it is not necessary to look if a block-tag was
	 * deleted 2 or 5 siblings away. If we had to actually examine all of those,
	 * nodes, this would get very complex, and it would be much simpler to just
	 * discard the original separators => potentially lots of dirty diffs.
	 *
	 * To understand why it is sufficient (for correctness) to examine just
	 * the immediately adjacent nodes, let us look at an additional example.
	 * ```
	 * Original WT  : "a<div>b</div>c<div>d</div>e\nf"
	 * Original HTML: "<p>a</p><div>b</div><p>c</p><div>d</div><p>e</p>\n<p>f</p>"
	 * ```
	 * Note how `<block>` tags and `<p>` tags interleave in the HTML. This would be
	 * the case always no matter how much inline content showed up between the
	 * block tags in wikitext. If the b-`<div>` was deleted, we don't care
	 * about it, since we still have the d-`<div>` before the P tag that preserves
	 * the correctness of the single `"\n"` separator. If the d-`<div>` was deleted,
	 * we conservatively ignore the original separator and let normal P-P constraints
	 * take care of it. At worst, we might generate a dirty diff in this scenario.
	 *
	 * @param DOMNode $node
	 */
	private function emitSepForNode( DOMNode $node ): void {
		$again = ( $node === $this->sep->lastSourceNode );
		$origSepUsable = !$again
			&& $this->prevNodeUnmodified && !WTSUtils::nextToDeletedBlockNodeInWT( $this->prevNode, true )
			&& $this->currNodeUnmodified && !WTSUtils::nextToDeletedBlockNodeInWT( $node, false );

		$origSep = null;
		if ( $origSepUsable ) {
			if ( DOMUtils::isElt( $this->prevNode ) && DOMUtils::isElt( $node ) ) {
				'@phan-var DOMElement $node';/** @var DOMElement $node */
				$origSep = $this->getOrigSrc(
					DOMDataUtils::getDataParsoid( $this->prevNode )->dsr->end,
					DOMDataUtils::getDataParsoid( $node )->dsr->start
				);
			} else {
				$origSep = $this->sep->src;
			}
		}

		if ( $origSep !== null && WTSUtils::isValidSep( $origSep ) ) {
			$this->emitSep( $origSep, $node, 'ORIG-SEP:' );
		} else {
			$sep = $this->serializer->buildSep( $node );
			$this->emitSep( $sep ?: '', $node, 'SEP:' );
		}
	}

	/**
	 * Pushes the chunk to the current line.
	 * @param ConstrainedText|string $res
	 * @param DOMNode $node
	 */
	public function emitChunk( $res, DOMNode $node ): void {
		$res = ConstrainedText::cast( $res, $node );

		// Replace newlines if we're in a single-line context
		if ( $this->singleLineContext->enforced() ) {
			$res->text = preg_replace( '/\n/', ' ', $res->text );
		}

		// Emit separator first
		if ( $res->noSep ) {
			/* skip separators for internal tokens from SelSer */
		} else {
			$this->emitSepForNode( $node );
		}

		if ( $this->onSOL ) {
			// process escapes in our full line
			$this->flushLine();
			$this->resetCurrLine( $node );
		}

		// Escape 'res' if necessary
		if ( $this->escapeText ) {
			$res = new ConstrainedText( [
				'text' => $this->serializer->escapeWikiText( $this, $res->text, [
					'node' => $node,
					'isLastChild' => DOMUtils::nextNonDeletedSibling( $node ) === null,
				] ),
				'prefix' => $res->prefix,
				'suffix' => $res->suffix,
				'node' => $res->node,
			] );
			$this->escapeText = false;
		} else {
			// If 'res' is coming from selser and the current node is a paragraph tag,
			// check if 'res' might need some leading chars nowiki-escaped before being output.
			// Because of block-tag p-wrapping behavior, sol-sensitive characters that used to
			// be in non-sol positions, but yet wrapped in p-tags, could end up in sol-position
			// if those block tags get deleted during edits.
			//
			// Ex: a<div>foo</div>*b
			// -- wt2html --> <p>a</p><div>foo<div><p>*b</p>
			// --   EDIT  --> <p>a</p><p>*b</p>
			// -- html2wt --> a\n\n<nowiki>*</nowiki>b
			//
			// In this scenario, the <p>a</p>, <p>*b</p>, and <p>#c</p>
			// will be marked unmodified and will be processed below.
			if ( $this->selserMode
				&& $this->onSOL
				&& $this->currNodeUnmodified
				// 'node' came from original Parsoid HTML unmodified. So, if its content
				// needs nowiki-escaping, we know that the reason it didn't parse into
				// lists/headings/whatever is because it didn't occur at the start of the
				// line => it had a block-tag in the original wikitext. So if the previous
				// node was also unmodified (and since it also came from original Parsoid
				// HTML), we can safely infer that it couldn't have been an inline node or
				// a P-tag (if it were, the p-wrapping code would have swallowed that content
				// into 'node'). So, it would have to be some sort of block tag => this.onSOL
				// couldn't have been true (because we could have serialized 'node' on the
				// same line as the block tag) => we can save some effort by eliminating
				// scenarios where 'this.prevNodeUnmodified' is true.
				 && !$this->prevNodeUnmodified
				&& $node->nodeName === 'p' && !WTUtils::isLiteralHTMLNode( $node )
			) {
				$pChild = DOMUtils::firstNonSepChild( $node );
				// If a text node, we have to make sure that the text doesn't
				// get reparsed as non-text in the wt2html pipeline.
				if ( $pChild && DOMUtils::isText( $pChild ) ) {
					$match = $res->match( $this->solWikitextRegexp() );
					if ( $match && isset( $match[2] ) ) {
						if ( preg_match( '/^([\*#:;]|{\||.*=$)/D', $match[2] )
							// ! and | chars are harmless outside tables
							|| ( preg_match( '/^[\|!]/', $match[2] ) && $this->wikiTableNesting > 0 )
							// indent-pres are suppressed inside <blockquote>
							|| ( preg_match( '/^ [^\s]/', $match[2] )
								&& !DOMUtils::hasAncestorOfName( $node, 'blockquote' ) )
						) {
							$res = ConstrainedText::cast( ( $match[1] ?: '' )
								. '<nowiki>' . substr( $match[2], 0, 1 ) . '</nowiki>'
								. substr( $match[2], 1 ), $node );
						}
					}
				}
			}
		}

		// Emitting text that has not been escaped
		$this->currLine->text .= $res->text;

		// Output res
		$this->serializer->trace( '--->', $this->logPrefix, function () use ( $res ) {
			return PHPUtils::jsonEncode( $res->text );
		} );
		$this->pushToCurrLine( $res, $node );

		// Update sol flag. Test for newlines followed by optional includeonly or comments
		if ( !$res->match( $this->solRegexp() ) ) {
			$this->onSOL = false;
		}

		// We've emit something so we're no longer at SOO.
		$this->atStartOfOutput = false;
	}

	/**
	 * Serialize the children of a DOM node, sharing the global serializer state.
	 * Typically called by a DOM-based handler to continue handling its children.
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper ( $state, $text, $opts )
	 *   PORT-FIXME document better; should this be done via WikitextEscapeHandlers somehow?
	 * @param DOMNode|null $firstChild
	 */
	public function serializeChildren(
		DOMElement $node, callable $wtEscaper = null, DOMNode $firstChild = null
	): void {
		// SSS FIXME: Unsure if this is the right thing always
		if ( $wtEscaper ) {
			$this->wteHandlerStack[] = $wtEscaper;
		}

		$child = $firstChild ?: $node->firstChild;
		while ( $child !== null ) {
			// We always get the next child to process
			$child = $this->serializer->serializeNode( $child );
		}

		if ( $wtEscaper ) {
			array_pop( $this->wteHandlerStack );
		}

		// If we serialized children explicitly,
		// we were obviously processing a modified node.
		$this->currNodeUnmodified = false;
	}

	/**
	 * Abstracts some steps taken in `serializeChildrenToString` and `serializeDOM`
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper See {@link serializeChildren()}
	 * @internal For use by WikitextSerializer only
	 */
	public function kickOffSerialize( DOMElement $node, callable $wtEscaper = null ): void {
		$this->updateSep( $node );
		$this->currNodeUnmodified = false;
		$this->updateModificationFlags( $node );
		$this->resetCurrLine( $node->firstChild );
		$this->serializeChildren( $node, $wtEscaper );
		// Emit child-parent seps.
		$this->emitSepForNode( $node );
		// We've reached EOF, flush the remaining buffered text.
		$this->flushLine();
	}

	/**
	 * Serialize children to a string
	 *
	 * FIXME(arlorla): Shouldn't affect the separator state, but accidents have
	 * have been known to happen. T109793 suggests using its own wts / state.
	 *
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper See {@link serializeChildren()}
	 * @param string $inState
	 * @return string
	 */
	private function serializeChildrenToString(
		DOMElement $node, ?callable $wtEscaper, string $inState
	): string {
		$states = [ 'inLink', 'inCaption', 'inIndentPre', 'inHTMLPre', 'inPHPBlock', 'inAttribute' ];
		Assert::parameter( in_array( $inState, $states, true ), '$inState', 'Must be one of: '
			. implode( ', ', $states ) );
		// FIXME: Make sure that the separators emitted here conform to the
		// syntactic constraints of syntactic context.
		$oldSep = $this->sep;
		$oldSOL = $this->onSOL;
		$oldOut = $this->out;
		$oldStart = $this->atStartOfOutput;
		$oldCurrLine = $this->currLine;
		$oldLogPrefix = $this->logPrefix;
		// Modification flags
		$oldPrevNodeUnmodified = $this->prevNodeUnmodified;
		$oldCurrNodeUnmodified = $this->currNodeUnmodified;
		$oldPrevNode = $this->prevNode;

		$this->out = '';
		$this->logPrefix = 'OUT(C):';
		$this->resetSep();
		$this->onSOL = false;
		$this->atStartOfOutput = false;
		$this->$inState = true;

		$this->kickOffSerialize( $node, $wtEscaper );

		// restore the state
		$bits = $this->out;
		$this->out = $oldOut;
		$this->$inState = false;
		$this->sep = $oldSep;
		$this->onSOL = $oldSOL;
		$this->atStartOfOutput = $oldStart;
		$this->currLine = $oldCurrLine;
		$this->logPrefix = $oldLogPrefix;
		// Modification flags
		$this->prevNodeUnmodified = $oldPrevNodeUnmodified;
		$this->currNodeUnmodified = $oldCurrNodeUnmodified;
		$this->prevNode = $oldPrevNode;
		return $bits;
	}

	/**
	 * Serialize children of a link to a string
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper See {@link serializeChildren()}
	 * @return string
	 */
	public function serializeLinkChildrenToString( $node, $wtEscaper = null ): string {
		return $this->serializeChildrenToString( $node, $wtEscaper, 'inLink' );
	}

	/**
	 * Serialize children of a caption to a string
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper See {@link serializeChildren()}
	 * @return string
	 */
	public function serializeCaptionChildrenToString( $node, $wtEscaper = null ): string {
		return $this->serializeChildrenToString( $node, $wtEscaper, 'inCaption' );
	}

	/**
	 * Serialize children of an indent-pre to a string
	 * @param DOMElement $node
	 * @param callable|null $wtEscaper See {@link serializeChildren()}
	 * @return string
	 */
	public function serializeIndentPreChildrenToString( $node, $wtEscaper = null ): string {
		return $this->serializeChildrenToString( $node, $wtEscaper, 'inIndentPre' );
	}

}
