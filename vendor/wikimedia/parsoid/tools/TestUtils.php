<?php
declare( strict_types = 1 );
// phpcs:disable Generic.Files.LineLength.TooLong

namespace Wikimedia\Parsoid\Tools;

use DOMElement;
use DOMNode;
use DOMText;
use Error;
use Exception;
use JakubOnderka\PhpConsoleColor\ConsoleColor;
use SebastianBergmann\Diff\Differ;
use Wikimedia\Parsoid\Html2Wt\DOMNormalizer;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WikitextSerializer;
use Wikimedia\Parsoid\Mocks\MockEnv;
use Wikimedia\Parsoid\ParserTests\Stats;
use Wikimedia\Parsoid\ParserTests\Test;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

class TestUtils {
	// PORT-FIXME: Used to be colors::mode in all the use sites
	public static $colors_mode;

	/** @var Differ $differ */
	private static $differ;

	/** @var ConsoleColor $consoleColor */
	private static $consoleColor;

	/**
	 * Little helper function for encoding XML entities.
	 *
	 * @param string $str
	 * @return string
	 */
	public static function encodeXml( string $str ) {
		// PORT-FIXME: Find replacement
		// return entities::encodeXML( $str );
		return $str;
	}

	/**
	 * Specialized normalization of the PHP parser & Parsoid output, to ignore
	 * a few known-ok differences in parser test runs.
	 *
	 * This code is also used by the Parsoid round-trip testing code.
	 *
	 * If parsoidOnly is true-ish, we allow more markup through (like property
	 * and typeof attributes), for better checking of parsoid-only test cases.
	 *
	 * @param DOMElement|string $domBody
	 * @param array $options
	 *  - parsoidOnly (bool) Is this test Parsoid Only? Optional. Default: false
	 *  - preserveIEW (bool) Should inter-element WS be preserved? Optional. Default: false
	 *  - scrubWikitext (bool) Are we running html2wt in scrubWikitext mode? Optional. Default: false
	 *  - rtTestMode (bool) Are we running the test in roundtrip test mode? Optional. Default: false
	 * @return string
	 */
	public static function normalizeOut( $domBody, array $options = [] ): string {
		$parsoidOnly = !empty( $options['parsoidOnly'] );
		$preserveIEW = !empty( $options['preserveIEW'] );

		if ( !empty( $options['scrubWikitext'] ) ) {
			// Mock env obj
			//
			// FIXME: This is ugly.
			// (a) The normalizer shouldn't need the full env.
			//     Pass options and a logger instead?
			// (b) DOM diff code is using page-id for some reason.
			//     That feels like a carryover of 2013 era code.
			//     If possible, get rid of it and diff-mark dependency
			//     on the env object.
			$mockEnv = new MockEnv( [ 'scrubWikitext' => true ] );
			$mockSerializer = new WikitextSerializer( [ 'env' => $mockEnv ] );
			$mockState = new SerializerState( $mockSerializer, [
				'selserMode' => false,
				'rtTestMode' => !empty( $options['rtTestMode'] )
			] );
			if ( is_string( $domBody ) ) {
				$domBody = DOMCompat::getBody( $mockEnv->createDocument( $domBody ) );
			}
			DOMDataUtils::visitAndLoadDataAttribs( $domBody, [ 'markNew' => true ] );
			$domBody = ( new DOMNormalizer( $mockState ) )->normalize( $domBody );
			DOMDataUtils::visitAndStoreDataAttribs( $domBody );
		} elseif ( is_string( $domBody ) ) {
			$domBody = DOMCompat::getBody( DOMUtils::parseHTML( $domBody ) );
		}

		$stripTypeof = $parsoidOnly ?
			'/^mw:Placeholder$/' :
			'/^mw:(?:Placeholder|Nowiki|Transclusion|Entity)$/';
		$domBody = self::unwrapSpansAndNormalizeIEW( $domBody, $stripTypeof, $parsoidOnly, $preserveIEW );
		$out = ContentUtils::toXML( $domBody, [ 'innerXML' => true ] );
		// NOTE that we use a slightly restricted regexp for "attribute"
		//  which works for the output of DOM serialization.  For example,
		//  we know that attribute values will be surrounded with double quotes,
		//  not unquoted or quoted with single quotes.  The serialization
		//  algorithm is given by:
		//  http://www.whatwg.org/specs/web-apps/current-work/multipage/the-end.html#serializing-html-fragments
		if ( !preg_match( '#[^<]*(<\w+(\s+[^\0-\cZ\s"\'>/=]+(="[^"]*")?)*/?>[^<]*)*#u', $out ) ) {
			throw new Error( 'normalizeOut input is not in standard serialized form' );
		}

		// Eliminate a source of indeterminacy from leaked strip markers
		$out = preg_replace( '/UNIQ-.*?-QINU/u', '', $out );

		// And from the imagemap extension - the id attribute is not always around, it appears!
		$out = preg_replace( '/<map name="ImageMap_[^"]*"( id="ImageMap_[^"]*")?( data-parsoid="[^"]*")?>/u', '<map>', $out );

		// Normalize COINS ids -- they aren't stable
		$out = preg_replace( '/\s?id=[\'"]coins_\d+[\'"]/iu', '', $out );

		// Eliminate transience from priority hints (T216499)
		$out = preg_replace( '/\s?importance="high"/u', '', $out );
		$out = preg_replace( '/\s?elementtiming="thumbnail-(high|top)"/u', '', $out );

		// maplink extension
		$out = preg_replace( '/\s?data-overlays=\'[^\']*\'/u', '', $out );

		if ( $parsoidOnly ) {
			// unnecessary attributes, we don't need to check these
			// style is in there because we should only check classes.
			$out = preg_replace( '/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=\\\\?"[^\"]*\\\\?"/u', '', $out );
			// single-quoted variant
			$out = preg_replace( "/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=\\\\?'[^\']*\\\\?'/u", '', $out );
			// apos variant
			$out = preg_replace( '/ (data-parsoid|prefix|about|rev|datatype|inlist|usemap|vocab|content|style)=&apos;.*?&apos;/u', '', $out );

			// strip self-closed <nowiki /> because we frequently test WTS
			// <nowiki> insertion by providing an html/parsoid section with the
			// <meta> tags stripped out, allowing the html2wt test to verify that
			// the <nowiki> is correctly added during WTS, while still allowing
			// the html2html and wt2html versions of the test to pass as a
			// sanity check.  If <meta>s were not stripped, these tests would all
			// have to be modified and split up.  Not worth it at this time.
			// (see commit 689b22431ad690302420d049b10e689de6b7d426)
			$out = preg_replace( '#<span typeof="mw:Nowiki"></span>#', '', $out );

			return $out;
		}

		// Normalize headings by stripping out Parsoid-added ids so that we don't
		// have to add these ids to every parser test that uses headings.
		// We will test the id generation scheme separately via mocha tests.
		$out = preg_replace( '/(<h[1-6].*?) id="[^\"]*"([^>]*>)/u', '$1$2', $out );
		// strip meta/link elements
		$out = preg_replace(
			'#</?(?:meta|link)(?: [^\0-\cZ\s"\'>/=]+(?:=(?:"[^"]*"|\'[^\']*\'))?)*/?>#u', '', $out );
		// Ignore troublesome attributes.
		// Strip JSON attributes like data-mw and data-parsoid early so that
		// comment stripping in normalizeNewlines does not match unbalanced
		// comments in wikitext source.
		$out = preg_replace( '/ (data-mw|data-parsoid|resource|rel|prefix|about|rev|datatype|inlist|property|usemap|vocab|content|class)=\\\\?"[^"]*\\\\?"/u', '', $out );
		// single-quoted variant
		$out = preg_replace( "/ (data-mw|data-parsoid|resource|rel|prefix|about|rev|datatype|inlist|property|usemap|vocab|content|class)=\\\\?'[^']*\\\\?'/u", '', $out );
		// strip typeof last
		$out = preg_replace( '/ typeof="[^\"]*"/u', '', $out );
		// replace mwt ids
		$out = preg_replace( '/ id="mw((t\d+)|([\w-]{2,}))"/u', '', $out );
		$out = preg_replace( '/<span[^>]+about="[^"]*"[^>]*>/u', '', $out );
		$out = preg_replace( '#(\s)<span>\s*</span>\s*#u', '$1', $out );
		$out = preg_replace( '#<span>\s*</span>#u', '', $out );
		$out = preg_replace( '#(href=")(?:\.?\./)+#u', '$1', $out );
		// replace unnecessary URL escaping
		$out = preg_replace_callback( '/ href="[^"]*"/u', function ( $m ) {
			return Utils::decodeURI( $m[0] );
		}, $out );
		// strip thumbnail size prefixes
		return preg_replace(
			'#(src="[^"]*?)/thumb(/[0-9a-f]/[0-9a-f]{2}/[^/]+)/[0-9]+px-[^"/]+(?=")#u', '$1$2',
			$out
		);
	}

	/**
	 * @param DOMNode $node
	 * @param ?string $stripSpanTypeof
	 */
	private static function cleanSpans(
		DOMNode $node, ?string $stripSpanTypeof
	): void {
		if ( !$stripSpanTypeof ) {
			return;
		}

		$child = null;
		$next = null;
		for ( $child = $node->firstChild; $child; $child = $next ) {
			$next = $child->nextSibling;
			if ( $child instanceof DOMElement && $child->nodeName === 'span' &&
				preg_match( $stripSpanTypeof, $child->getAttribute( 'typeof' ) ?? '' )
			) {
				self::unwrapSpan( $node, $child, $stripSpanTypeof );
			}
		}
	}

	/**
	 * @param DOMNode $parent
	 * @param DOMNode $node
	 * @param ?string $stripSpanTypeof
	 */
	private static function unwrapSpan(
		DOMNode $parent, DOMNode $node, ?string $stripSpanTypeof
	):void {
		// first recurse to unwrap any spans in the immediate children.
		self::cleanSpans( $node, $stripSpanTypeof );
		// now unwrap this span.
		DOMUtils::migrateChildren( $node, $parent, $node );
		$parent->removeChild( $node );
	}

	/**
	 * @param ?DOMNode $node
	 * @return bool
	 */
	private static function newlineAround( ?DOMNode $node ): bool {
		return $node &&
			preg_match( '/^(body|caption|div|dd|dt|li|p|table|tr|td|th|tbody|dl|ol|ul|h[1-6])$/D', $node->nodeName );
	}

	/**
	 * @param DOMNode $node
	 * @param array $opts
	 * @return DOMNode
	 */
	private static function normalizeIEWVisitor(
		DOMNode $node, array $opts
	): DOMNode {
		$child = null;
		$next = null;
		$prev = null;
		if ( $node->nodeName === 'pre' ) {
			// Preserve newlines in <pre> tags
			$opts['inPRE'] = true;
		}
		if ( !$opts['preserveIEW'] && $node instanceof DOMText ) {
			if ( !$opts['inPRE'] ) {
				$node->data = preg_replace( '/\s+/u', ' ', $node->data );
			}
			if ( $opts['stripLeadingWS'] ) {
				$node->data = preg_replace( '/^\s+/u', '', $node->data, 1 );
			}
			if ( $opts['stripTrailingWS'] ) {
				$node->data = preg_replace( '/\s+$/uD', '', $node->data, 1 );
			}
		}
		// unwrap certain SPAN nodes
		self::cleanSpans( $node, $opts['stripSpanTypeof'] );
		// now remove comment nodes
		if ( !$opts['parsoidOnly'] ) {
			for ( $child = $node->firstChild;  $child;  $child = $next ) {
				$next = $child->nextSibling;
				if ( DOMUtils::isComment( $child ) ) {
					$node->removeChild( $child );
				}
			}
		}
		// reassemble text nodes split by a comment or span, if necessary
		if ( $node instanceof DOMElement ) {
			DOMCompat::normalize( $node );
		}
		// now recurse.
		if ( $node->nodeName === 'pre' ) {
			// hack, since PHP adds a newline before </pre>
			$opts['stripLeadingWS'] = false;
			$opts['stripTrailingWS'] = true;
		} elseif ( $node->nodeName === 'span' &&
			preg_match( '/^mw[:]/', $node->getAttribute( 'typeof' ) ?? '' )
		) {
			// SPAN is transparent; pass the strip parameters down to kids
		} else {
			$opts['stripLeadingWS'] = $opts['stripTrailingWS'] = self::newlineAround( $node );
		}
		$child = $node->firstChild;
		// Skip over the empty mw:FallbackId <span> and strip leading WS
		// on the other side of it.
		if ( preg_match( '/^h[1-6]$/D', $node->nodeName ) &&
			$child && WTUtils::isFallbackIdSpan( $child )
		) {
			$child = $child->nextSibling;
		}
		for ( ; $child; $child = $next ) {
			$next = $child->nextSibling;
			$newOpts = $opts;
			$newOpts['stripTrailingWS'] = $opts['stripTrailingWS'] && !$child->nextSibling;
			self::normalizeIEWVisitor( $child, $newOpts );
			$opts['stripLeadingWS'] = false;
		}

		if ( $opts['inPRE'] || $opts['preserveIEW'] ) {
			return $node;
		}

		// now add newlines around appropriate nodes.
		for ( $child = $node->firstChild;  $child; $child = $next ) {
			$prev = $child->previousSibling;
			$next = $child->nextSibling;
			if ( self::newlineAround( $child ) ) {
				if ( $prev && $prev instanceof DOMText ) {
					$prev->data = preg_replace( '/\s*$/uD', "\n", $prev->data, 1 );
				} else {
					$prev = $node->ownerDocument->createTextNode( "\n" );
					$node->insertBefore( $prev, $child );
				}
				if ( $next && $next instanceof DOMText ) {
					$next->data = preg_replace( '/^\s*/u', "\n", $next->data, 1 );
				} else {
					$next = $node->ownerDocument->createTextNode( "\n" );
					$node->insertBefore( $next, $child->nextSibling );
				}
			}
		}
		return $node;
	}

	/**
	 * Normalize newlines in IEW to spaces instead.
	 *
	 * @param DOMElement $body The document body node to normalize.
	 * @param string|null $stripSpanTypeof Regular expression to strip typeof attributes
	 * @param bool $parsoidOnly
	 * @param bool $preserveIEW
	 * @return DOMElement
	 */
	public static function unwrapSpansAndNormalizeIEW(
		DOMElement $body, string $stripSpanTypeof = null, bool $parsoidOnly = false, bool $preserveIEW = false
	): DOMElement {
		$opts = [
			'preserveIEW' => $preserveIEW,
			'parsoidOnly' => $parsoidOnly,
			'stripSpanTypeof' => $stripSpanTypeof,
			'stripLeadingWS' => true,
			'stripTrailingWS' => true,
			'inPRE' => false
		];
		// clone body first, since we're going to destructively mutate it.
		return self::normalizeIEWVisitor( $body->cloneNode( true ), $opts );
	}

	/**
	 * Strip some php output we aren't generating.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function normalizePhpOutput( string $html ): string {
		$html = preg_replace(
			// do not expect section editing for now
			'/<span[^>]+class="mw-headline"[^>]*>(.*?)<\/span> '
			. '*(<span class="mw-editsection"><span class="mw-editsection-bracket">'
			. '\[<\/span>.*?<span class="mw-editsection-bracket">\]<\/span><\/span>)?/u',
			'$1',
			$html
		);
		return preg_replace(
			'#<a[^>]+class="mw-headline-anchor"[^>]*>§</a>#', '',
			$html
		);
	}

	/**
	 * Normalize the expected parser output by parsing it using a HTML5 parser and
	 * re-serializing it to HTML. Ideally, the parser would normalize inter-tag
	 * whitespace for us. For now, we fake that by simply stripping all newlines.
	 *
	 * @param string $source
	 * @return string
	 */
	public static function normalizeHTML( string $source ): string {
		try {
			$body = self::unwrapSpansAndNormalizeIEW( DOMCompat::getBody( DOMUtils::parseHTML( $source ) ) );
			$html = ContentUtils::toXML( $body, [ 'innerXML' => true ] );

			// a few things we ignore for now..
			//  .replace(/\/wiki\/Main_Page/g, 'Main Page')
			// do not expect a toc for now
			$html = preg_replace(
				'/<div[^>]+?id="toc"[^>]*>\s*<div id="toctitle"[^>]*>[\s\S]+?<\/div>[\s\S]+?<\/div>\s*/u',
				'',
				$html );
			$html = self::normalizePhpOutput( $html );
			// remove empty span tags
			$html = preg_replace( '/(\s)<span>\s*<\/span>\s*/u', '$1', $html );
			$html = preg_replace( '/<span>\s*<\/span>/u', '', $html );
			// general class and titles, typically on links
			$html = preg_replace( '/ (class|rel|about|typeof)="[^"]*"/', '', $html );
			// strip red link markup, we do not check if a page exists yet
			$html = preg_replace(
				"#/index.php\\?title=([^']+?)&amp;action=edit&amp;redlink=1#", '/wiki/$1', $html );
			// strip red link title info
			$html = preg_replace(
				"/ \\((?:page does not exist|encara no existeix|bet ele jaratılmag'an|lonkásá  ezalí tɛ̂)\\)/",
				'', $html );
			// the expected html has some extra space in tags, strip it
			$html = preg_replace( '/<a +href/', '<a href', $html );
			$html = preg_replace( '#href="/wiki/#', 'href="', $html );
			$html = preg_replace( '/" +>/', '">', $html );
			// parsoid always add a page name to lonely fragments
			$html = preg_replace( '/href="#/', 'href="Main Page#', $html );
			// replace unnecessary URL escaping
			$html = preg_replace_callback( '/ href="[^"]*"/',
				function ( $m ) {
					return Utils::decodeURI( $m[0] );
				},
				$html );
			// strip empty spans
			$html = preg_replace( '#(\s)<span>\s*</span>\s*#u', '$1', $html );
			return preg_replace( '#<span>\s*</span>#u', '', $html );
		} catch ( Exception $e ) {
			error_log( 'normalizeHTML failed on' . $source . ' with the following error: ' . $e );
			return $source;
		}
	}

	/**
	 * @param string $string
	 * @param string $color
	 * @param bool $inverse
	 * @return string
	 */
	public static function colorString(
		string $string, string $color, bool $inverse = false
	): string {
		if ( !self::$consoleColor ) {
			self::$consoleColor = new ConsoleColor();
		}

		if ( $inverse ) {
			$color = [ $color, 'reverse' ];
		}

		if ( self::$consoleColor->isSupported() ) {
			return self::$consoleColor->apply( $color, $string );
		} else {
			return $string;
		}
	}

	/**
	 * Colorize given number if <> 0.
	 *
	 * @param int $count
	 * @param string $color
	 * @return string Colorized count
	 */
	private static function colorizeCount( int $count, string $color ): string {
		$s = (string)$count;
		return self::colorString( $s, $color );
	}

	/**
	 * @param array $modesRan
	 * @param Stats $stats
	 *  - failedTests int Number of failed tests due to differences in output.
	 *  - passedTests int Number of tests passed without any special consideration.
	 *  - modes array All of the stats (failedTests, passedTests) per-mode.
	 * @param string|null $file
	 * @param array|null $testFilter
	 * @param bool $knownFailuresChanged
	 * @return int
	 */
	public static function reportSummary(
		array $modesRan, Stats $stats, ?string $file, ?array $testFilter, bool $knownFailuresChanged
	): int {
		$curStr = null;
		$mode = null;
		$thisMode = null;
		$failTotalTests = $stats->failedTests;
		$happiness = $stats->passedTestsUnexpected === 0 && $stats->failedTestsUnexpected === 0;
		$filename = $file === null ? 'ALL TESTS' : $file;

		print "==========================================================\n";
		print 'SUMMARY:' . self::colorString( $filename, $happiness ? 'green' : 'red' ) . "\n";
		if ( $file !== null ) {
			print 'Execution time: ' . round( 1000 * ( microtime( true ) - $stats->startTime ), 3 ) . "ms\n";
		}

		if ( $failTotalTests !== 0 ) {
			foreach ( $modesRan as $mode ) {
				$curStr = $mode . ': ';
				$thisMode = $stats->modes[$mode];
				$curStr .= self::colorizeCount( $thisMode->passedTests, 'green' ) . ' passed (';
				$curStr .= self::colorizeCount( $thisMode->passedTestsUnexpected, 'red' ) . ' unexpected) / ';
				$curStr .= self::colorizeCount( $thisMode->failedTests, 'red' ) . ' failed (';
				$curStr .= self::colorizeCount( $thisMode->failedTestsUnexpected, 'red' ) . ' unexpected)';
				print $curStr . "\n";
			}

			$curStr = 'TOTAL' . ': ';
			$curStr .= self::colorizeCount( $stats->passedTests, 'green' ) . ' passed (';
			$curStr .= self::colorizeCount( $stats->passedTestsUnexpected, 'red' ) . ' unexpected) / ';
			$curStr .= self::colorizeCount( $stats->failedTests, 'red' ) . ' failed (';
			$curStr .= self::colorizeCount( $stats->failedTestsUnexpected, 'red' ) . ' unexpected)';
			print $curStr . "\n";

			if ( $file === null ) {
				$buf = self::colorizeCount( $stats->passedTests, 'green' );
				$buf .= ' total passed tests (expected ';
				$buf .= (string)( $stats->passedTests - $stats->passedTestsUnexpected + $stats->failedTestsUnexpected );
				$buf .=	'), ';
				$buf .= self::colorizeCount( $failTotalTests, 'red' ) . ' total failures (expected ';
				$buf .= (string)( $stats->failedTests - $stats->failedTestsUnexpected + $stats->passedTestsUnexpected );
				$buf .= ")\n";
				print $buf;
			}
		} else {
			if ( $testFilter !== null ) {
				$buf = 'Passed ' . $stats->passedTests . ' of '
					. $stats->passedTests . ' tests matching ' . $testFilter['raw'];
			} else {
				// Should not happen if it does: Champagne!
				$buf = 'Passed ' . $stats->passedTests . ' of ' . $stats->passedTests .	' tests';
			}
			print $buf . '... ' . self::colorString( 'ALL TESTS PASSED!', 'green' ) . "\n";
		}

		// If we logged error messages, complain about it.
		$logMsg = self::colorString( 'No errors logged.', 'green' );
		if ( $stats->loggedErrorCount > 0 ) {
			$logMsg = self::colorString( $stats->loggedErrorCount . ' errors logged.', 'red' );
		}
		if ( $file === null ) {
			if ( $stats->loggedErrorCount > 0 ) {
				$logMsg = self::colorString( '' . $stats->loggedErrorCount, 'red' );
			} else {
				$logMsg = self::colorString( '' . $stats->loggedErrorCount, 'green' );
			}
			$logMsg .= ' errors logged.';
		}
		print $logMsg . "\n";

		$failures = $stats->allFailures();

		// If the knownFailures changed, complain about it.
		if ( $knownFailuresChanged ) {
			print self::colorString( 'Known failures changed!', 'red' ) . "\n";
		}

		if ( $file === null ) {
			if ( $failures === 0 ) {
				print '--> ' . self::colorString( 'NO UNEXPECTED RESULTS', 'green' ) . " <--\n";
				if ( $knownFailuresChanged ) {
					print "Perhaps some tests were deleted or renamed.\n";
					print "Use `bin/parserTests.js --updateKnownFailures` to update knownFailures list.\n";
				}
			} else {
				print self::colorString( '--> ' . $failures . ' UNEXPECTED RESULTS. <--', 'red' ) . "\n";
			}
		}

		return $failures;
	}

	/**
	 * @param ?array $iopts
	 * @return string
	 */
	private static function prettyPrintIOptions(
		?array $iopts = null
	): string {
		if ( !$iopts ) {
			return '';
		}

		$ppValue = null; // Forward declaration
		$ppValue = function ( $v ) use ( &$ppValue ) {
			if ( is_array( $v ) ) {
				return implode( ',', array_map( $ppValue, $v ) );
			}

			if ( is_string( $v ) &&
				( preg_match( '/^\[\[[^\]]*\]\]$/D', $v ) || preg_match( '/^[-\w]+$/D', $v ) )
			) {
				return $v;
			}

			return json_encode( $v );
		};

		$strPieces = array_map(
			function ( $k ) use ( $iopts, $ppValue ) {
				if ( $iopts[$k] === '' ) {
					return $k;
				}
				return $k . '=' . $ppValue( $iopts[$k] );
			},
			array_keys( $iopts )
		);
		return implode( ' ', $strPieces );
	}

	/**
	 * @param Stats $stats
	 * @param Test $item
	 * @param array $options
	 * @param string $mode
	 * @param string $title
	 * @param array $actual
	 * @param array $expected
	 * @param bool $expectFail Whether this test was expected to fail (on knownFailures list).
	 * @param bool $failureOnly Whether we should print only a failure message, or go on to print the diff.
	 * @param array $kf knownFailures.
	 * @return bool true if the failure was expected.
	 */
	public static function printFailure(
		Stats $stats, Test $item, array $options, string $mode, string $title,
		array $actual, array $expected, bool $expectFail, bool $failureOnly, array $kf
	): bool {
		$stats->failedTests++;
		$stats->modes[$mode]->failedTests++;
		$fail = [
			'title' => $title,
			'raw' => $actual ? $actual['raw'] : null,
			'expected' => $expected ? $expected['raw'] : null,
			'actualNormalized' => $actual ? $actual['normal'] : null
		];
		$stats->modes[$mode]->failList[] = &$fail;

		$extTitle = str_replace( "\n", ' ', "$title ($mode)" );

		$knownFailures = false;
		if ( ScriptUtils::booleanOption( $options['knownFailures'] ?? null ) && $expectFail ) {
			// compare with remembered output
			$normalizeAbout = function ( $s ) {
				return preg_replace( "/(about=\\\\?[\"']#mwt)\d+/", '$1', $s );
			};
			$offsetType = $options['offsetType'] ?? 'byte';
			if ( $normalizeAbout( $kf[$title][$mode] ) !== $normalizeAbout( $actual['raw'] ) && $offsetType === 'byte' ) {
				$knownFailures = true;
			} else {
				if ( !ScriptUtils::booleanOption( $options['quiet'] ?? '' ) ) {
					print self::colorString( 'EXPECTED FAIL', 'red' ) . ': ' . self::colorString( $extTitle, 'yellow' ) . "\n";
				}
				return true;
			}
		}

		$stats->failedTestsUnexpected++;
		$stats->modes[$mode]->failedTestsUnexpected++;
		$fail['unexpected'] = true;

		if ( !$failureOnly ) {
			print "=====================================================\n";
		}

		if ( $knownFailures ) {
			print self::colorString( 'UNEXPECTED CHANGE TO KNOWN FAILURE OUTPUT', 'red', true ) . ': '
				. self::colorString( $extTitle, 'yellow' ) . "\n";
			print self::colorString( 'Known failure, but the output changed!', 'red' ) . "\n";
		} else {
			print self::colorString( 'UNEXPECTED FAIL', 'red', true ) . ': '
				. self::colorString( $extTitle, 'yellow' ) . "\n";
		}

		if ( $mode === 'selser' ) {
			if ( $item->wt2wtPassed ) {
				print self::colorString( 'Even worse, the non-selser wt2wt test passed!', 'red' ) . "\n";
			} elseif ( $actual && $item->wt2wtResult !== $actual['raw'] ) {
				print self::colorString( 'Even worse, the non-selser wt2wt test had a different result!', 'red' ) . "\n";
			}
		}

		if ( !$failureOnly ) {
			// PORT-FIXME: Removed comments .. maybe need to put it back
			// print implode( "\n", $item->comments ) . "\n";
			if ( $options ) {
				print self::colorString( 'OPTIONS', 'cyan' ) . ':' . "\n";
				print self::prettyPrintIOptions( $item->options ) . "\n";
			}
			print self::colorString( 'INPUT', 'cyan' ) . ':' . "\n";
			print $actual['input'] . "\n";
			print $options['getActualExpected']( $actual, $expected, $options['getDiff'] ) . "\n";
		}

		return false;
	}

	/**
	 * @param Stats $stats
	 * @param Test $item
	 * @param array $options
	 * @param string $mode
	 * @param string $title
	 * @param bool $expectSuccess Whether this success was expected (or was it a known failure).
	 * @return bool true if the success was expected.
	 */
	public static function printSuccess(
		Stats $stats, Test $item, array $options, string $mode, string $title, bool $expectSuccess
	): bool {
		$quiet = ScriptUtils::booleanOption( $options['quiet'] ?? null );
		$stats->passedTests++;
		$stats->modes[$mode]->passedTests++;

		$extTitle = str_replace( "\n", ' ', "$title ($mode)" );

		if ( ScriptUtils::booleanOption( $options['knownFailures'] ?? null ) && !$expectSuccess ) {
			$stats->passedTestsUnexpected++;
			$stats->modes[$mode]->passedTestsUnexpected++;
			print self::colorString( 'UNEXPECTED PASS', 'green', true ) . ': ' .
				self::colorString( $extTitle, 'yellow' ) . "\n";
			return false;
		}
		if ( !$quiet ) {
			$outStr = 'EXPECTED PASS';

			$outStr = self::colorString( $outStr, 'green' ) . ': '
				. self::colorString( $extTitle, 'yellow' );

			print $outStr . "\n";

			if ( $mode === 'selser' && isset( $item->wt2wtPassed ) && !$item->wt2wtPassed ) {
				print self::colorString( 'Even better, the non-selser wt2wt test failed!', 'red' ) . "\n";
			}
		}
		return true;
	}

	/**
	 * Print the actual and expected outputs.
	 *
	 * @param array $actual
	 *  - string raw
	 *  - string normal
	 * @param array $expected
	 *  - string raw
	 *  - string normal
	 * @param Callable $getDiff Returns a string showing the diff(s) for the test.
	 *  - array actual
	 *  - array expected
	 * @return string
	 */
	public static function getActualExpected( array $actual, array $expected, callable $getDiff ): string {
		if ( self::$colors_mode === 'none' ) {
			$mkVisible = function ( $s ) {
				return $s;
			};
		} else {
			$mkVisible = function ( $s ) {
				return preg_replace( '/\xA0/', self::colorString( "␣", "white" ),
					preg_replace( '/\n/', self::colorString( "↵\n", "white" ), $s ) );
			};
		}

		$returnStr = '';
		$returnStr .= self::colorString( 'RAW EXPECTED', 'cyan' ) . ":\n";
		$returnStr .= $expected['raw'] . "\n";

		$returnStr .= self::colorString( 'RAW RENDERED', 'cyan' ) . ":\n";
		$returnStr .= $actual['raw'] . "\n";

		$returnStr .= self::colorString( 'NORMALIZED EXPECTED', 'magenta' ) . ":\n";
		$returnStr .= $mkVisible( $expected['normal'] ) . "\n";

		$returnStr .= self::colorString( 'NORMALIZED RENDERED', 'magenta' ) . ":\n";
		$returnStr .= $mkVisible( $actual['normal'] ) . "\n";

		$returnStr .= self::colorString( 'DIFF', 'cyan' ) . ":\n";
		$returnStr .= $getDiff( $actual, $expected );

		return $returnStr;
	}

	/**
	 * @param array $actual
	 *  - string normal
	 * @param array $expected
	 *  - string normal
	 * @return string Colorized diff
	 */
	public static function doDiff( array $actual, array $expected ): string {
		// safe to always request color diff, because we set color mode='none'
		// if colors are turned off.
		$e = preg_replace( '/\xA0/', "␣", $expected['normal'] );
		$a = preg_replace( '/\xA0/', "␣", $actual['normal'] );
		// PORT_FIXME:
		if ( !self::$differ ) {
			self::$differ = new Differ();
		}

		$diffs = self::$differ->diff( $e, $a );
		$diffs = preg_replace_callback( '/^(-.*)/m', function ( $m ) {
			return self::colorString( $m[0], 'green' );
		}, $diffs );
		$diffs = preg_replace_callback( '/^(\+.*)/m', function ( $m ) {
			return self::colorString( $m[0], 'red' );
		}, $diffs );

		return $diffs;
	}

	/**
	 * @param Callable $reportFailure
	 * @param Callable $reportSuccess
	 * @param array $kf knownFailures.
	 * @param Stats $stats
	 * @param Test $item
	 * @param array $options
	 * @param string $mode
	 * @param array $expected
	 * @param array $actual
	 * @param Callable|null $pre
	 * @param Callable|null $post
	 * @return bool True if the result was as expected.
	 */
	public static function printResult(
		callable $reportFailure, callable $reportSuccess, array $kf,
		Stats $stats, Test $item, array $options, string $mode,
		array $expected, array $actual, callable $pre = null, callable $post = null
	): bool {
		$title = $item->title; // Title may be modified here, so pass it on.

		$quick = ScriptUtils::booleanOption( $options['quick'] ?? null );
		$parsoidOnly = isset( $item->altHtmlSections['html/parsoid'] ) ||
			isset( $item->options['parsoid'] );

		if ( $mode === 'selser' ) {
			$title .= ' ' . ( $item->changes ? json_encode( $item->changes ) : '[manual]' );
		} elseif ( $mode === 'wt2html' && isset( $item->options['langconv'] ) ) {
			$title .= ' [langconv]';
		}

		$tb = $kf[$title] ?? [];
		$expectFail = isset( $tb[$mode] );
		$fail = $expected['normal'] !== $actual['normal'];
		// Return whether the test was as expected, independent of pass/fail
		$asExpected = null;

		if ( $mode === 'wt2wt' ) {
			$item->wt2wtPassed = !$fail;
			$item->wt2wtResult = $actual['raw'];
		}

		// don't report selser fails when nothing was changed or it's a dup
		if (
			$mode === 'selser' && $item->changetree !== [ 'manual' ] &&
			( $item->changes === [] || $item->duplicateChange )
		) {
			return true;
		}

		if ( is_callable( $pre ) ) {
			$pre( $stats, $mode, $title, $item->time );
		}

		if ( $fail ) {
			$asExpected = $reportFailure( $stats, $item, $options, $mode, $title, $actual, $expected, $expectFail, $quick, $kf );
		} else {
			$asExpected = $reportSuccess( $stats, $item, $options, $mode, $title, !$expectFail );
		}

		if ( is_callable( $post ) ) {
			$post( $stats, $mode );
		}

		return $asExpected;
	}

	/**
	 * Simple function for reporting the start of the tests.
	 *
	 * This method can be reimplemented in the options of the ParserTests object.
	 */
	public static function reportStartOfTests() {
	}

	/**
	 * Get the actual and expected outputs encoded for XML output.
	 *
	 * @inheritDoc getActualExpected
	 *
	 * @return string $The XML representation of the actual and expected outputs.
	 */
	public static function getActualExpectedXML( array $actual, array $expected, callable $getDiff ) {
		$returnStr = '';

		$returnStr .= "RAW EXPECTED:\n";
		$returnStr .= self::encodeXml( $expected['raw'] ) . "\n\n";

		$returnStr .= "RAW RENDERED:\n";
		$returnStr .= self::encodeXml( $actual['raw'] ) . "\n\n";

		$returnStr .= "NORMALIZED EXPECTED:\n";
		$returnStr .= self::encodeXml( $expected['normal'] ) . "\n\n";

		$returnStr .= "NORMALIZED RENDERED:\n";
		$returnStr .= self::encodeXml( $actual['normal'] ) . "\n\n";

		$returnStr .= "DIFF:\n";
		$returnStr .= self::encodeXml( $getDiff( $actual, $expected, false ) );

		return $returnStr;
	}

	/**
	 * Report the start of the tests output.
	 *
	 * @inheritDoc reportStart
	 */
	public static function reportStartXML(): void {
	}

	/**
	 * Report the end of the tests output.
	 *
	 * @inheritDoc reportSummary
	 */
	public static function reportSummaryXML(
		array $modesRan, Stats $stats, string $file, ?array $testFilter, bool $knownFailuresChanged
	): int {
		$failures = $stats->allFailures();

		if ( $file === null ) {
			/* Summary for all tests; not included in XML format output. */
			return $failures;
		}
		print '<testsuites file="' . $file . '">';
		for ( $i = 0;  $i < count( $modesRan );  $i++ ) {
			$mode = $modesRan[$i];
			print '<testsuite name="parserTests-' . $mode . '">';
			print $stats->modes[$mode]->result;
			print '</testsuite>';
		}
		print '</testsuites>';
		return $failures;
	}

	/**
	 * Print a failure message for a test in XML.
	 *
	 * @inheritDoc printFailure
	 */
	public static function reportFailureXML(
		Stats $stats, Test $item, array $options, string $mode, string $title,
		array $actual, array $expected, bool $expectFail, bool $failureOnly, array $kf
	): void {
		$stats->failedTests++;
		$stats->modes[$mode]->failedTests++;
		$failEle = '';
		$knownFailures = false;
		if ( ScriptUtils::booleanOption( $options['knownFailures'] ) && $expectFail ) {
			// compare with remembered output
			$knownFailures = $kf[$title][$mode] === $actual['raw'];
		}
		if ( !$knownFailures ) {
			$failEle .= "<failure type=\"parserTestsDifferenceInOutputFailure\">\n";
			$failEle .= self::getActualExpectedXML( $actual, $expected, $options['getDiff'] );
			$failEle .= "\n</failure>";
			$stats->failedTestsUnexpected++;
			$stats->modes[$mode]->failedTestsUnexpected++;
			$stats->modes[$mode]->result .= $failEle;
		}
	}

	/**
	 * Print a success method for a test in XML.
	 *
	 * @inheritDoc printSuccess
	 */
	public static function reportSuccessXML(
		Stats $stats, Test $item, array $options, string $mode, string $title, bool $expectSuccess
	): void {
		$stats->passedTests++;
		$stats->modes[$mode]->passedTests++;
	}

	/**
	 * @param Stats $stats
	 * @param string $mode
	 * @param string $title
	 * @param array $time
	 */
	private static function pre(
		Stats $stats, string $mode, string $title, array $time
	): void {
		$testcaseEle = '<testcase name="' . self::encodeXml( $title ) . '" ';
		$testcaseEle .= 'assertions="1" ';

		$timeTotal = null;
		if ( $time && $time['end'] && $time['start'] ) {
			$timeTotal = $time['end'] - $time['start'];
			if ( !is_nan( $timeTotal ) ) {
				$testcaseEle .= 'time="' . ( ( $time['end'] - $time['start'] ) / 1000.0 ) . '"';
			}
		}

		$testcaseEle .= '>';
		$stats->modes[$mode]->result .= $testcaseEle;
	}

	/**
	 * @param Stats $stats
	 * @param string $mode
	 */
	private static function post( Stats $stats, string $mode ): void {
		$stats->modes[$mode]->result .= '</testcase>';
	}

	/**
	 * Print the result of a test in XML.
	 *
	 * @inheritDoc printResult
	 */
	public static function reportResultXML( ...$args ) {
		$args = array_merge( [ [ self::class, 'reportFailureXML' ], [ self::class, 'reportSuccessXML' ] ], $args );
		$args = array_merge( $args, [ [ self::class, 'pre' ], [ self::class, 'post' ] ] );
		call_user_func_array( [ self::class, 'printResult' ], $args );

		// In xml, test all cases always
		return true;
	}

	/**
	 * Process CLI opts and return
	 *
	 * @param Maintenance $script
	 */
	public static function setupOpts( Maintenance $script ): void {
		$opts = ScriptUtils::addStandardOptions( [
			'wt2html' => [
				'description' => 'Wikitext -> HTML(DOM)',
				'default' => false,
				'boolean' => true
			],
			'html2wt' => [
				'description' => 'HTML(DOM) -> Wikitext',
				'default' => false,
				'boolean' => true
			],
			'wt2wt' => [
				'description' => 'Roundtrip testing: Wikitext -> DOM(HTML) -> Wikitext',
				'default' => false,
				'boolean' => true
			],
			'html2html' => [
				'description' => 'Roundtrip testing: HTML(DOM) -> Wikitext -> HTML(DOM)',
				'default' => false,
				'boolean' => true
			],
			'selser' => [
				'description' => 'Roundtrip testing: Wikitext -> DOM(HTML) -> Wikitext (with selective serialization). ' .
					'Set to "noauto" to just run the tests with manual selser changes.',
				'boolean' => false
			],
			'changetree' => [
				'description' => 'Changes to apply to parsed HTML to generate new HTML to be serialized (useful with selser)',
				'default' => null,
				'boolean' => false
			],
			'numchanges' => [
				'description' => 'Make multiple different changes to the DOM, run a selser test for each one.',
				'default' => 20,
				'boolean' => false
			],
			'cache' => [
				'description' => 'Get tests cases from cache file',
				'boolean' => true,
				'default' => false
			],
			'filter' => [
				'description' => 'Only run tests whose descriptions match given string'
			],
			'regex' => [
				'description' => 'Only run tests whose descriptions match given regex',
				'alias' => [ 'regexp', 're' ]
			],
			'run-disabled' => [
				'description' => 'Run disabled tests',
				'default' => false,
				'boolean' => true
			],
			'run-php' => [
				'description' => 'Run php-only tests',
				'default' => false,
				'boolean' => true
			],
			'maxtests' => [
				'description' => 'Maximum number of tests to run',
				'boolean' => false
			],
			'quick' => [
				'description' => 'Suppress diff output of failed tests',
				'boolean' => true,
				'default' => false
			],
			'quiet' => [
				'description' => 'Suppress notification of passed tests (shows only failed tests)',
				'boolean' => true,
				'default' => false
			],
			'offsetType' => [
				'description' => 'Test DSR offset conversion code while running tests.',
				'boolean' => false,
				'default' => 'byte',
			],
			'knownFailures' => [
				'description' => 'Compare against known failures',
				'default' => true,
				'boolean' => false
			],
			'updateKnownFailures' => [
				'description' => 'Update parserTests-knownFailures.json with failing tests.',
				'default' => false,
				'boolean' => true
			],
			'exit-zero' => [
				'description' => "Don't exit with nonzero status if failures are found.",
				'default' => false,
				'boolean' => true
			],
			'xml' => [
				'description' => 'Print output in JUnit XML format.',
				'default' => false,
				'boolean' => true
			],
			'exit-unexpected' => [
				'description' => 'Exit after the first unexpected result.',
				'default' => false,
				'boolean' => true
			],
			'update-tests' => [
				'description' => 'Update parserTests.txt with results from wt2html fails.'
			],
			'update-unexpected' => [
				'description' => 'Update parserTests.txt with results from wt2html unexpected fails.',
				'default' => false,
				'boolean' => true
			]
		], [
			// override defaults for standard options
			'fetchTemplates' => false,
			'usePHPPreProcessor' => false,
			'fetchConfig' => false
		] );

		foreach ( $opts as $opt => $optInfo ) {
			$script->addOption( $opt,
				$optInfo['description'], false, empty( $optInfo['boolean'] ), false );
			if ( isset( $optInfo['default'] ) ) {
				$script->setOptionDefault( $opt, $optInfo['default'] );
			}
		}
	}

	/**
	 * @param Maintenance $script
	 * @return array
	 */
	public static function processOptions( Maintenance $script ): array {
		$options = $script->optionsToArray();

		if ( $options['help'] ) {
			$script->maybeHelp();
			print "Additional dump options specific to parserTests script:\n"
			 . "* dom:post-changes  : Dumps DOM after applying selser changetree\n"
			 . "Examples\n"
			 . "\$ php parserTests.php --selser --filter '...' --dump dom:post-changes\n"
			 . "\$ php parserTests.php --selser --filter '...' --changetree '...' --dump dom:post-changes\n";
			die( 0 );
		}

		ScriptUtils::setColorFlags( $options );

		if ( !( $options['wt2wt'] || $options['wt2html'] || $options['html2wt'] || $options['html2html']
			|| isset( $options['selser'] ) )
		) {
			$options['wt2wt'] = true;
			$options['wt2html'] = true;
			$options['html2html'] = true;
			$options['html2wt'] = true;
			if ( ScriptUtils::booleanOption( $options['updateKnownFailures'] ?? null ) ) {
				// turn on all modes by default for --updateKnownFailures
				$options['selser'] = true;
				// sanity checking (T53448 asks to be able to use --filter here)
				if ( isset( $options['filter'] ) || isset( $options['regex'] ) ||
					isset( $options['maxtests'] ) || $options['exit-unexpected']
				) {
					print "\nERROR: can't combine --updateKnownFailures with --filter, --maxtests or --exit-unexpected";
					die( 1 );
				}
			}
		}

		if ( $options['xml'] ) {
			$options['reportResult']  = [ self::class, 'reportResultXML' ];
			$options['reportStart']   = [ self::class, 'reportStartXML' ];
			$options['reportSummary'] = [ self::class, 'reportSummaryXML' ];
			$options['reportFailure'] = [ self::class, 'reportFailureXML' ];
			self::$colors_mode = 'none';
		}

		if ( !is_callable( $options['reportFailure'] ?? null ) ) {
			// default failure reporting is standard out,
			// see printFailure for documentation of the default.
			$options['reportFailure'] = [ self::class, 'printFailure' ];
		}

		if ( !is_callable( $options['reportSuccess'] ?? null ) ) {
			// default success reporting is standard out,
			// see printSuccess for documentation of the default.
			$options['reportSuccess'] = [ self::class, 'printSuccess' ];
		}

		if ( !is_callable( $options['reportStart'] ?? null ) ) {
			// default summary reporting is standard out,
			// see reportStart for documentation of the default.
			$options['reportStart'] = [ self::class, 'reportStartOfTests' ];
		}

		if ( !is_callable( $options['reportSummary'] ?? null ) ) {
			// default summary reporting is standard out,
			// see reportSummary for documentation of the default.
			$options['reportSummary'] = [ self::class, 'reportSummary' ];
		}

		if ( !is_callable( $options['reportResult'] ?? null ) ) {
			// default result reporting is standard out,
			// see printResult for documentation of the default.
			$options['reportResult'] = function ( ...$args ) use ( &$options ) {
				return self::printResult( $options['reportFailure'], $options['reportSuccess'], ...$args );
			};
		}

		if ( !is_callable( $options['getDiff'] ?? null ) ) {
			// this is the default for diff-getting, but it can be overridden
			// see doDiff for documentation of the default.
			$options['getDiff'] = [ self::class, 'doDiff' ];
		}

		if ( !is_callable( $options['getActualExpected'] ?? null ) ) {
			// this is the default for getting the actual and expected
			// outputs, but it can be overridden
			// see getActualExpected for documentation of the default.
			$options['getActualExpected'] = [ self::class, 'getActualExpected' ];
		}

		$options['modes'] = [];

		if ( $options['wt2html'] ) {
			$options['modes'][] = 'wt2html';
		}
		if ( $options['wt2wt'] ) {
			$options['modes'][] = 'wt2wt';
		}
		if ( $options['html2html'] ) {
			$options['modes'][] = 'html2html';
		}
		if ( $options['html2wt'] ) {
			$options['modes'][] = 'html2wt';
		}
		if ( isset( $options['selser'] ) ) {
			$options['modes'][] = 'selser';
		}

		return $options;
	}
}
