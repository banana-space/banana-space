<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

use MediaWiki\Shell\Shell;

class SyntaxHighlight {

	/** @var int The maximum number of lines that may be selected for highlighting. **/
	const HIGHLIGHT_MAX_LINES = 1000;

	/** @var int Maximum input size for the highlighter (100 kB). **/
	const HIGHLIGHT_MAX_BYTES = 102400;

	/** @var string CSS class for syntax-highlighted code. **/
	const HIGHLIGHT_CSS_CLASS = 'mw-highlight';

	/** @var int Cache version. Increment whenever the HTML changes. */
	const CACHE_VERSION = 2;

	/** @var array Mapping of MIME-types to lexer names. **/
	private static $mimeLexers = [
		'text/javascript'  => 'javascript',
		'application/json' => 'javascript',
		'text/xml'         => 'xml',
	];

	/**
	 * Get the Pygments lexer name for a particular language.
	 *
	 * @param string $lang Language name.
	 * @return string|null Lexer name, or null if no matching lexer.
	 */
	private static function getLexer( $lang ) {
		static $lexers = null;

		if ( $lang === null ) {
			return null;
		}

		if ( !$lexers ) {
			$lexers = require __DIR__ . '/../SyntaxHighlight.lexers.php';
		}

		$lexer = strtolower( $lang );

		if ( in_array( $lexer, $lexers ) ) {
			return $lexer;
		}

		$geshi2pygments = SyntaxHighlightGeSHiCompat::getGeSHiToPygmentsMap();

		// Check if this is a GeSHi lexer name for which there exists
		// a compatible Pygments lexer with a different name.
		if ( isset( $geshi2pygments[$lexer] ) ) {
			$lexer = $geshi2pygments[$lexer];
			if ( in_array( $lexer, $lexers ) ) {
				return $lexer;
			}
		}

		return null;
	}

	/**
	 * Register parser hook
	 *
	 * @param Parser &$parser
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		foreach ( [ 'source', 'syntaxhighlight' ] as $tag ) {
			$parser->setHook( $tag, [ 'SyntaxHighlight', 'parserHook' ] );
		}
	}

	/**
	 * Parser hook
	 *
	 * @param string $text
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 * @throws MWException
	 */
	public static function parserHook( $text, $args, $parser ) {
		global $wgUseTidy;

		// Replace strip markers (For e.g. {{#tag:syntaxhighlight|<nowiki>...}})
		$out = $parser->mStripState->unstripNoWiki( $text );

		// Don't trim leading spaces away, just the linefeeds
		$out = preg_replace( '/^\n+/', '', rtrim( $out ) );

		// Convert deprecated attributes
		if ( isset( $args['enclose'] ) ) {
			if ( $args['enclose'] === 'none' ) {
				$args['inline'] = true;
			}
			unset( $args['enclose'] );
		}

		$lexer = isset( $args['lang'] ) ? $args['lang'] : '';

		$result = self::highlight( $out, $lexer, $args );
		if ( !$result->isGood() ) {
			$parser->addTrackingCategory( 'syntaxhighlight-error-category' );
		}
		$out = $result->getValue();

		// HTML Tidy will convert tabs to spaces incorrectly (bug 30930).
		// But the conversion from tab to space occurs while reading the input,
		// before the conversion from &#9; to tab, so we can armor it that way.
		if ( $wgUseTidy ) {
			$out = str_replace( "\t", '&#9;', $out );
		}

		// Allow certain HTML attributes
		$htmlAttribs = Sanitizer::validateAttributes( $args, [ 'style', 'class', 'id', 'dir' ] );
		if ( !isset( $htmlAttribs['class'] ) ) {
			$htmlAttribs['class'] = self::HIGHLIGHT_CSS_CLASS;
		} else {
			$htmlAttribs['class'] .= ' ' . self::HIGHLIGHT_CSS_CLASS;
		}
		if ( !( isset( $htmlAttribs['dir'] ) && $htmlAttribs['dir'] === 'rtl' ) ) {
			$htmlAttribs['dir'] = 'ltr';
		}

		if ( isset( $args['inline'] ) ) {
			// Enforce inlineness. Stray newlines may result in unexpected list and paragraph processing
			// (also known as doBlockLevels()).
			$out = str_replace( "\n", ' ', $out );
			$out = Html::rawElement( 'code', $htmlAttribs, $out );

		} else {
			// Not entirely sure what benefit this provides, but it was here already
			$htmlAttribs['class'] .= ' ' . 'mw-content-' . $htmlAttribs['dir'];

			// Unwrap Pygments output to provide our own wrapper. We can't just always use the 'nowrap'
			// option (pass 'inline'), since it disables other useful things like line highlighting.
			// Tolerate absence of quotes for Html::element() and wgWellFormedXml=false.
			if ( $out !== '' ) {
				$m = [];
				if ( preg_match( '/^<div class="?mw-highlight"?>(.*)<\/div>$/s', trim( $out ), $m ) ) {
					$out = trim( $m[1] );
				} else {
					throw new MWException( 'Unexpected output from Pygments encountered' );
				}
			}

			// Use 'nowiki' strip marker to prevent list processing (also known as doBlockLevels()).
			// However, leave the wrapping <div/> outside to prevent <p/>-wrapping.
			$marker = $parser::MARKER_PREFIX . '-syntaxhighlightinner-' .
				sprintf( '%08X', $parser->mMarkerIndex++ ) . $parser::MARKER_SUFFIX;
			$parser->mStripState->addNoWiki( $marker, $out );

			$out = Html::openElement( 'div', $htmlAttribs ) .
				$marker .
				Html::closeElement( 'div' );
		}

		// Register CSS
		$parser->getOutput()->addModuleStyles( 'ext.pygments' );

		return $out;
	}

	/**
	 * @return string
	 */
	public static function getPygmentizePath() {
		global $wgPygmentizePath;

		// If $wgPygmentizePath is unset, use the bundled copy.
		if ( $wgPygmentizePath === false ) {
			$wgPygmentizePath = __DIR__ . '/../pygments/pygmentize';
		}

		return $wgPygmentizePath;
	}

	/**
	 * @param string $code
	 * @param bool $inline
	 */
	private static function plainCodeWrap( $code, $inline ) {
		if ( $inline ) {
			return htmlspecialchars( $code, ENT_NOQUOTES );
		}

		return Html::rawElement(
			'div',
			[ 'class' => self::HIGHLIGHT_CSS_CLASS ],
			Html::element( 'pre', [], $code )
		);
	}

	/**
	 * Highlight a code-block using a particular lexer.
	 *
	 * @param string $code Code to highlight.
	 * @param string|null $lang Language name, or null to use plain markup.
	 * @param array $args Associative array of additional arguments.
	 *  If it contains a 'line' key, the output will include line numbers.
	 *  If it includes a 'highlight' key, the value will be parsed as a
	 *  comma-separated list of lines and line-ranges to highlight.
	 *  If it contains a 'start' key, the value will be used as the line at which to
	 *  start highlighting.
	 *  If it contains a 'inline' key, the output will not be wrapped in `<div><pre/></div>`.
	 * @return Status Status object, with HTML representing the highlighted
	 *  code as its value.
	 */
	public static function highlight( $code, $lang = null, $args = [] ) {
		$status = new Status;

		$lexer = self::getLexer( $lang );
		if ( $lexer === null && $lang !== null ) {
			$status->warning( 'syntaxhighlight-error-unknown-language', $lang );
		}

		// For empty tag, output nothing instead of empty <pre>.
		if ( $code === '' ) {
			$status->value = '';
			return $status;
		}

		$length = strlen( $code );
		if ( strlen( $code ) > self::HIGHLIGHT_MAX_BYTES ) {
			// Disable syntax highlighting
			$lexer = null;
			$status->warning(
				'syntaxhighlight-error-exceeds-size-limit',
				$length,
				self::HIGHLIGHT_MAX_BYTES
			);
		} elseif ( Shell::isDisabled() ) {
			// Disable syntax highlighting
			$lexer = null;
			$status->warning( 'syntaxhighlight-error-pygments-invocation-failure' );
			wfWarn(
				'MediaWiki determined that it cannot invoke Pygments. ' .
				'As a result, SyntaxHighlight_GeSHi will not perform any syntax highlighting. ' .
				'See the debug log for details: ' .
				'https://www.mediawiki.org/wiki/Manual:$wgDebugLogFile'
			);
		}

		$inline = isset( $args['inline'] );

		if ( $inline ) {
			$code = trim( $code );
		}

		if ( $lexer === null ) {
			// When syntax highlighting is disabled..
			$status->value = self::plainCodeWrap( $code, $inline );
			return $status;
		}

		$options = [
			'cssclass' => self::HIGHLIGHT_CSS_CLASS,
			'encoding' => 'utf-8',
		];

		// Line numbers
		if ( isset( $args['line'] ) ) {
			$options['linenos'] = 'inline';
		}

		if ( $lexer === 'php' && strpos( $code, '<?php' ) === false ) {
			$options['startinline'] = 1;
		}

		// Highlight specific lines
		if ( isset( $args['highlight'] ) ) {
			$lines = self::parseHighlightLines( $args['highlight'] );
			if ( count( $lines ) ) {
				$options['hl_lines'] = implode( ' ', $lines );
			}
		}

		// Starting line number
		if ( isset( $args['start'] ) && ctype_digit( $args['start'] ) ) {
			$options['linenostart'] = (int)$args['start'];
		}

		if ( $inline ) {
			$options['nowrap'] = 1;
		}

		$cache = ObjectCache::getMainWANInstance();
		$error = null;
		$output = $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'highlight', self::makeCacheKeyHash( $code, $lexer, $options ) ),
			$cache::TTL_MONTH,
			function ( $oldValue, &$ttl ) use ( $code, $lexer, $options, &$error ) {
				$optionPairs = [];
				foreach ( $options as $k => $v ) {
					$optionPairs[] = "{$k}={$v}";
				}
				$result = Shell::command(
					self::getPygmentizePath(),
					'-l', $lexer,
					'-f', 'html',
					'-O', implode( ',', $optionPairs )
				)
					->input( $code )
					->restrict( Shell::RESTRICT_DEFAULT | Shell::NO_NETWORK )
					->execute();

				if ( $result->getExitCode() != 0 ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					$error = $result->getStderr();
					return null;
				}

				return $result->getStdout();
			}
		);

		if ( $error !== null || $output === null ) {
			$status->warning( 'syntaxhighlight-error-pygments-invocation-failure' );
			wfWarn( 'Failed to invoke Pygments: ' . $error );
			// Fall back to preformatted code without syntax highlighting
			$output = self::plainCodeWrap( $code, $inline );
		}

		if ( $inline ) {
			// We've already trimmed the input $code before highlighting,
			// but pygment's standard out adds a line break afterwards,
			// which would then be preserved in the paragraph that wraps this,
			// and become visible as a space. Avoid that.
			$output = trim( $output );
		}

		$status->value = $output;
		return $status;
	}

	/**
	 * Construct a cache key for the results of a Pygments invocation.
	 *
	 * @param string $code Code to be highlighted.
	 * @param string $lexer Lexer name.
	 * @param array $options Options array.
	 * @return string Cache key.
	 */
	private static function makeCacheKeyHash( $code, $lexer, $options ) {
		$optionString = FormatJson::encode( $options, false, FormatJson::ALL_OK );
		return md5( "{$code}|{$lexer}|{$optionString}|" . self::CACHE_VERSION );
	}

	/**
	 * Take an input specifying a list of lines to highlight, returning
	 * a raw list of matching line numbers.
	 *
	 * Input is comma-separated list of lines or line ranges.
	 *
	 * @param string $lineSpec
	 * @return int[] Line numbers.
	 */
	protected static function parseHighlightLines( $lineSpec ) {
		$lines = [];
		$values = array_map( 'trim', explode( ',', $lineSpec ) );
		foreach ( $values as $value ) {
			if ( ctype_digit( $value ) ) {
				$lines[] = (int)$value;
			} elseif ( strpos( $value, '-' ) !== false ) {
				list( $start, $end ) = array_map( 'trim', explode( '-', $value ) );
				if ( self::validHighlightRange( $start, $end ) ) {
					for ( $i = intval( $start ); $i <= $end; $i++ ) {
						$lines[] = $i;
					}
				}
			}
			if ( count( $lines ) > self::HIGHLIGHT_MAX_LINES ) {
				$lines = array_slice( $lines, 0, self::HIGHLIGHT_MAX_LINES );
				break;
			}
		}
		return $lines;
	}

	/**
	 * Validate a provided input range
	 * @param int $start
	 * @param int $end
	 * @return bool
	 */
	protected static function validHighlightRange( $start, $end ) {
		// Since we're taking this tiny range and producing a an
		// array of every integer between them, it would be trivial
		// to DoS the system by asking for a huge range.
		// Impose an arbitrary limit on the number of lines in a
		// given range to reduce the impact.
		return ctype_digit( $start ) &&
			ctype_digit( $end ) &&
			$start > 0 &&
			$start < $end &&
			$end - $start < self::HIGHLIGHT_MAX_LINES;
	}

	/**
	 * Hook into Content::getParserOutput to provide syntax highlighting for
	 * script content.
	 *
	 * @param Content $content
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 * @return bool
	 * @since MW 1.21
	 */
	public static function onContentGetParserOutput( Content $content, Title $title,
		$revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		global $wgParser, $wgTextModelsToParse;

		if ( !$generateHtml ) {
			// Nothing special for us to do, let MediaWiki handle this.
			return true;
		}

		// Determine the language
		$extension = ExtensionRegistry::getInstance();
		$models = $extension->getAttribute( 'SyntaxHighlightModels' );
		$model = $content->getModel();
		if ( !isset( $models[$model] ) ) {
			// We don't care about this model, carry on.
			return true;
		}
		$lexer = $models[$model];

		// Hope that $wgSyntaxHighlightModels does not contain silly types.
		$text = ContentHandler::getContentText( $content );
		if ( !$text ) {
			// Oops! Non-text content? Let MediaWiki handle this.
			return true;
		}

		// Parse using the standard parser to get links etc. into the database, HTML is replaced below.
		// We could do this using $content->fillParserOutput(), but alas it is 'protected'.
		if ( $content instanceof TextContent && in_array( $model, $wgTextModelsToParse ) ) {
			$output = $wgParser->parse( $text, $title, $options, true, true, $revId );
		}

		$status = self::highlight( $text, $lexer );
		if ( !$status->isOK() ) {
			return true;
		}
		$out = $status->getValue();

		$output->addModuleStyles( 'ext.pygments' );
		$output->setText( '<div dir="ltr">' . $out . '</div>' );

		// Inform MediaWiki that we have parsed this page and it shouldn't mess with it.
		return false;
	}

	/**
	 * Hook to provide syntax highlighting for API pretty-printed output
	 *
	 * @param IContextSource $context
	 * @param string $text
	 * @param string $mime
	 * @param string $format
	 * @since MW 1.24
	 * @return bool
	 */
	public static function onApiFormatHighlight( IContextSource $context, $text, $mime, $format ) {
		if ( !isset( self::$mimeLexers[$mime] ) ) {
			return true;
		}

		$lexer = self::$mimeLexers[$mime];
		$status = self::highlight( $text, $lexer );
		if ( !$status->isOK() ) {
			return true;
		}

		$out = $status->getValue();
		if ( preg_match( '/^<pre([^>]*)>/i', $out, $m ) ) {
			$attrs = Sanitizer::decodeTagAttributes( $m[1] );
			$attrs['class'] .= ' api-pretty-content';
			$encodedAttrs = Sanitizer::safeEncodeTagAttributes( $attrs );
			$out = '<pre' . $encodedAttrs. '>' .  substr( $out, strlen( $m[0] ) );
		}
		$output = $context->getOutput();
		$output->addModuleStyles( 'ext.pygments' );
		$output->addHTML( '<div dir="ltr">' . $out . '</div>' );

		// Inform MediaWiki that we have parsed this page and it shouldn't mess with it.
		return false;
	}

	/**
	 * Conditionally register resource loader modules that depends on the
	 * VisualEditor MediaWiki extension.
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( &$resourceLoader ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'VisualEditor' ) ) {
			return;
		}

		$resourceLoader->register( 'ext.geshi.visualEditor', [
			'class' => ResourceLoaderSyntaxHighlightVisualEditorModule::class,
			'localBasePath' => __DIR__ . '/../modules',
			'remoteExtPath' => 'SyntaxHighlight_GeSHi/modules',
			'scripts' => [
				've-syntaxhighlight/ve.dm.MWSyntaxHighlightNode.js',
				've-syntaxhighlight/ve.ce.MWSyntaxHighlightNode.js',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightWindow.js',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightDialog.js',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightDialogTool.js',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightInspector.js',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightInspectorTool.js',
			],
			'styles' => [
				've-syntaxhighlight/ve.ce.MWSyntaxHighlightNode.css',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightDialog.css',
				've-syntaxhighlight/ve.ui.MWSyntaxHighlightInspector.css',
			],
			'dependencies' => [
				'ext.visualEditor.mwcore',
				'oojs-ui.styles.icons-editing-advanced'
			],
			'messages' => [
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-code',
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-language',
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-none',
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-showlines',
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-startingline',
				'syntaxhighlight-visualeditor-mwsyntaxhighlightinspector-title',
			],
			'targets' => [ 'desktop', 'mobile' ],
		] );
	}

	/**
	 * Backward-compatibility shim for extensions.
	 * @deprecated since MW 1.25
	 */
	public static function prepare( $text, $lang ) {
		wfDeprecated( __METHOD__ );
		return new GeSHi( self::highlight( $text, $lang )->getValue() );
	}

	/**
	 * Backward-compatibility shim for extensions.
	 * @deprecated since MW 1.25
	 * @param GeSHi $geshi
	 * @return string
	 */
	public static function buildHeadItem( $geshi ) {
		wfDeprecated( __METHOD__ );
		$geshi->parse_code();
		return '';
	}
}
class_alias( SyntaxHighlight::class, 'SyntaxHighlight_GeSHi' );
