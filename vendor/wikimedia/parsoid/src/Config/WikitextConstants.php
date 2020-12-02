<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config;

use Wikimedia\Parsoid\Utils\PHPUtils;

class WikitextConstants {
	public static $Media;
	public static $Sanitizer;
	public static $WikitextTagsWithTrimmableWS;
	public static $HTMLTagsRequiringSOLContext;
	public static $WTQuoteTags;
	public static $WeakIndentPreSuppressingTags;
	public static $StrongIndentPreSuppressingTags;
	public static $SolSpaceSensitiveTags;
	public static $BlockScopeOpenTags;
	public static $BlockScopeCloseTags;
	public static $HTML;
	public static $WTTagsWithNoClosingTags;
	public static $Output;
	public static $WtTagWidths;
	public static $ZeroWidthWikitextTags;
	public static $LCFlagMap;
	public static $LCNameMap;

	public static function init() {
		/*
		 * Valid media options:
		 * - Prefix options are of the form "alt=foo"
		 * - Simple options are of the form "center"
		 *
		 * See http:#en.wikipedia.org/wiki/Wikipedia:Extended_image_syntax
		 * for more information about how they are used.
		 */
		self::$Media = [
			'PrefixOptions' => [
				'img_link' => 'link',
				'img_alt' => 'alt',
				'img_page' => 'page',
				'img_lang' => 'lang',  # see T34987
				'img_upright' => 'upright',
				'img_width' => 'width',
				'img_class' => 'class',
				'img_manualthumb' => 'manualthumb',

				'timedmedia_thumbtime' => 'thumbtime',
				'timedmedia_starttime' => 'start',
				'timedmedia_endtime' => 'end',
				'timedmedia_disablecontrols' => 'disablecontrols'  # See T135537
			],
			'SimpleOptions' => [
				# halign
				'img_left' => 'halign',
				'img_right' => 'halign',
				'img_center' => 'halign',
				'img_none' => 'halign',

				# valign
				'img_baseline' => 'valign',
				'img_sub' => 'valign',
				'img_super' => 'valign',
				'img_top' => 'valign',
				'img_text_top' => 'valign',
				'img_middle' => 'valign',
				'img_bottom' => 'valign',
				'img_text_bottom' => 'valign',

				# format
				# 'border' can be given in addition to *one of*
				# frameless, framed, or thumbnail
				'img_border' => 'border',
				'img_frameless' => 'format',
				'img_framed' => 'format',
				'img_thumbnail' => 'format',

				# Ha! Upright can be either one! Try parsing THAT!
				'img_upright' => 'upright',

				'timedmedia_noplayer' => 'noplayer',  # See T134880
				'timedmedia_noicon' => 'noicon'  # See T134880
			]
		];

		self::$Sanitizer = [
			# List of allowed tags that can be used as raw HTML in wikitext.
			# All other html/html-like tags will be spit out as text.
			'AllowedLiteralTags' => PHPUtils::makeSet( [
				# In case you were wondering, explicit <a .. > HTML is NOT allowed in wikitext.
				# That is why the <a> tag is missing from the allowed list.
				'abbr',
				'b', 'bdi', 'bdo', 'big', 'blockquote', 'br',
				'caption', 'center', 'cite', 'code',
				'data', 'dd', 'del', 'dfn', 'div', 'dl', 'dt',
				'em',
				'font',
				'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr',
				'i', 'ins',
				'kbd',
				'li',
				'mark',
				'ol',
				'p', 'pre',
				'q',
				'rb', 'rp', 'rt', 'rtc', 'ruby',
				's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup',
				'table', 'td', 'th', 'time', 'tr', 'tt',
				'u', 'ul',
				'var',
				'wbr',
			] ),
		];

		/**
		 * These HTML tags come from native wikitext markup and
		 * (as long as they are not literal HTML tags in the wikitext source)
		 * should have whitespace trimmed from their content.
		 */
		self::$WikitextTagsWithTrimmableWS = PHPUtils::makeSet( [
			"h1", "h2", "h3", "h4", "h5", "h6",
			"ol", "li", "ul", "dd", "dl", "dt",
			"td", "th", "caption"
		] );

		# These HTML tags will be generated only if
		# the corresponding wikitext occurs in a SOL context.
		self::$HTMLTagsRequiringSOLContext = PHPUtils::makeSet( [
			"pre",
			"h1", "h2", "h3", "h4", "h5", "h6",
			"ol", "li", "ul", "dd", "dl", "dt",
		] );

		# These wikitext tags are composed with quote-chars.
		self::$WTQuoteTags = PHPUtils::makeSet( [ 'i', 'b' ] );

		# Leading whitespace on new lines in these elements does not lead to indent-pre.
		# This only applies to immediate children (while skipping past zero-wikitext tags).
		# (Ex: content in table-cells induce indent pres)
		self::$WeakIndentPreSuppressingTags = PHPUtils::makeSet( [
			'table', 'tbody', 'tr',
		] );

		/*
		 * Leading whitespace on new lines in these elements does not lead to indent-pre
		 * This applies to all nested content in these tags.
		 * Ex: content in table-cells nested in blocktags do not induce indent pres
		 *
		 * These tags should match $openmatch regexp in doBlockLevels:
		 * $openmatch = preg_match(
		 *		'#(?:<table|<blockquote|<h1|<h2|<h3|<h4|<h5|<h6|<pre|<tr|<p|<ul|<ol|<dl|<li|</tr|</td|</th)/#S',
		 *		$t )
		 *
		 * PHP parser handling is line-based. Our handling is DOM-children based.
		 * So, there might be edge cases where behavior will be different.
		*/
		self::$StrongIndentPreSuppressingTags = PHPUtils::makeSet( [
			'blockquote', 'pre', 'p',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'ul', 'ol', 'dl', 'li',
		] );

		# Leading whitespace on new lines changes wikitext
		# parsing for these tags (*#;:=)
		self::$SolSpaceSensitiveTags = PHPUtils::makeSet( [
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'ul', 'ol', 'li', 'dl', 'dd', 'dt',
		] );

		# In the PHP parser, these block tags open block-tag scope
		# See doBlockLevels in the PHP parser (includes/parser/Parser.php)
		self::$BlockScopeOpenTags = PHPUtils::makeSet( [
			'blockquote', 'pre', 'p',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
			'ul', 'ol', 'li', 'dl',
			'table', 'tr',
		] );

		# In the PHP parser, these block tags close block-tag scope
		# See doBlockLevels in the PHP parser (includes/parser/Parser.php)
		self::$BlockScopeCloseTags = PHPUtils::makeSet( [
			'td', 'th',
		] );

		self::$HTML = [
			# The list of HTML5 tags, mainly used for the identification of *non*-html tags.
			# Non-html tags terminate otherwise tag-eating rules in the tokenizer
			# to support potential extension tags.
			'HTML5Tags' => PHPUtils::makeSet( [
				"a", "abbr", "address", "area", "article",
				"aside", "audio", "b", "base", "bdi", "bdo", "blockquote",
				"body", "br", "button", "canvas", "caption", "cite", "code",
				"col", "colgroup", "data", "datalist", "dd", "del",
				"details", "dfn", "div", "dl", "dt", "em", "embed", "fieldset",
				"figcaption", "figure", "footer", "form",
				"h1", "h2", "h3", "h4", "h5", "h6", "head", "header", "hgroup",
				"hr", "html", "i", "iframe", "img", "input", "ins", "kbd", "keygen",
				"label", "legend", "li", "link", "map", "mark", "menu", "meta",
				"meter", "nav", "noscript", "object", "ol", "optgroup", "option",
				"output", "p", "param", "pre", "progress", "q", "rb", "rp", "rt",
				"rtc", "ruby", "s", "samp", "script", "section", "select", "small",
				"source", "span", "strong", "style", "sub", "summary", "sup",
				"table", "tbody", "td", "textarea", "tfoot", "th", "thead", "time",
				"title", "tr", "track", "u", "ul", "var", "video", "wbr",
			] ),

			/**
			 * https://html.spec.whatwg.org/multipage/dom.html#metadata-content-2
			 * @type {Set}
			 */
			'MetaTags' => PHPUtils::makeSet( [
				"base", "link", "meta", "noscript", "script", "style", "template", "title"
			] ),

			# From http://www.w3.org/TR/html5-diff/#obsolete-elements
			# SSS FIXME: basefont is missing here, but looks like the PHP parser
			# does not support it anyway and treats it as plain text.  So, skipping
			# this one in Parsoid as well.
			'OlderHTMLTags' => PHPUtils::makeSet( [
				"strike", "big", "center", "font", "tt",
			] ),

			# From https://developer.mozilla.org/en-US/docs/HTML/Block-level_elements
			# However, you probably want to use `TokenUtils.isBlockTag()`, where some
			# exceptions are being made.
			'HTML4BlockTags' => PHPUtils::makeSet( [
				'div', 'p',
				# tables
				'table', 'tbody', 'thead', 'tfoot', 'caption', 'th', 'tr', 'td',
				# lists
				'ul', 'ol', 'li', 'dl', 'dt', 'dd',
				# HTML5 heading content
				'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup',
				# HTML5 sectioning content
				'article', 'aside', 'nav', 'section', 'footer', 'header',
				'figure', 'figcaption', 'fieldset', 'details', 'blockquote',
				# other
				'hr', 'button', 'canvas', 'center', 'col', 'colgroup', 'embed',
				'map', 'object', 'pre', 'progress', 'video',
			] ),

			# See http://www.w3.org/html/wg/drafts/html/master/syntax.html#formatting
			'FormattingTags' => PHPUtils::makeSet( [
				'a', 'b', 'big', 'code', 'em', 'font', 'i', 'nobr',
				's', 'small', 'strike', 'strong', 'tt', 'u',
			] ),

			/**
			 * From \\MediaWiki\Tidy\RemexCompatMunger::$onlyInlineElements
			 */
			'OnlyInlineElements' => PHPUtils::makeSet( [
				'a', 'abbr', 'acronym', 'applet', 'b', 'basefont', 'bdo',
				'big', 'br', 'button', 'cite', 'code', 'del', 'dfn', 'em',
				'font', 'i', 'iframe', 'img', 'input', 'ins', 'kbd', 'label',
				'legend', 'map', 'object', 'param', 'q', 'rb', 'rbc', 'rp',
				'rt', 'rtc', 'ruby', 's', 'samp', 'select', 'small', 'span',
				'strike', 'strong', 'sub', 'sup', 'textarea', 'tt', 'u', 'var',
				// Those defined in tidy.conf
				'video', 'audio', 'bdi', 'data', 'time', 'mark',
				// FIXME(T251641)
				'figure-inline'
			] ),

			'ListTags' => PHPUtils::makeSet( [ 'ul', 'ol', 'dl' ] ),

			'ListItemTags' => PHPUtils::makeSet( [ 'li', 'dd', 'dt' ] ),

			'FosterablePosition' => PHPUtils::makeSet( [ 'table', 'thead', 'tbody', 'tfoot', 'tr' ] ),

			'TableContentModels' => [
				'table' => [ 'caption', 'colgroup', 'thead', 'tbody', 'tr', 'tfoot' ],
				'thead' => [ 'tr' ],
				'tbody' => [ 'tr' ],
				'tfoot' => [ 'tr' ],
				'tr'    => [ 'td', 'th' ]
			],

			'TableTags' => PHPUtils::makeSet( [
				'table', 'tbody', 'thead', 'tfoot', 'caption', 'th', 'tr', 'td',
			] ),

			# Table tags that can be children
			'ChildTableTags' => PHPUtils::makeSet( [
				"tbody", "thead", "tfoot", "tr", "caption", "th", "td",
			] ),

			# See https://html.spec.whatwg.org/#void-elements
			'VoidTags' => PHPUtils::makeSet( [
				'area', 'base', 'br', 'col', 'embed', 'hr', 'img',
				'input', 'link', 'meta', 'param', 'source',
				'track', 'wbr',
			] ),
		];

		/**
		 * These HTML tags have native wikitext representations.
		 * The wikitext equivalents do not have closing tags.
		 * @type {Set}
		 */
		self::$WTTagsWithNoClosingTags = PHPUtils::makeSet( [
			"pre", "li", "dt", "dd", "hr", "tr", "td", "th"
		] );

		self::$Output = [
			'FlaggedEmptyElts' => PHPUtils::makeSet( [
				'li', 'tr', 'p',
			] ),
		];

		# Known wikitext tag widths -- these are known statically
		# but other widths are computed or updated based on actual wikitext usage
		self::$WtTagWidths = [
			"body" => [ 0, 0 ],
			"html" => [ 0, 0 ],
			"head" => [ 0, 0 ],
			"p" => [ 0, 0 ],
			"meta" => [ 0, 0 ],
			"pre" => [ 1, 0 ],
			"ol" => [ 0, 0 ],
			"ul" => [ 0, 0 ],
			"dl" => [ 0, 0 ],
			"li" => [ 1, 0 ],
			"dt" => [ 1, 0 ],
			"dd" => [ 1, 0 ],
			"h1" => [ 1, 1 ],
			"h2" => [ 2, 2 ],
			"h3" => [ 3, 3 ],
			"h4" => [ 4, 4 ],
			"h5" => [ 5, 5 ],
			"h6" => [ 6, 6 ],
			"hr" => [ 4, 0 ],
			"table" => [ 2, 2 ],
			"tbody" => [ 0, 0 ],
			"thead" => [ 0, 0 ],
			"tfoot" => [ 0, 0 ],
			"tr" => [ null, 0 ],
			"td" => [ null, 0 ],
			"th" => [ null, 0 ],
			"b" => [ 3, 3 ],
			"i" => [ 2, 2 ],
			"br" => [ 0, 0 ],
			"figure" => [ 2, 2 ],
			"figcaption" => [ 0, 0 ],
		];

		# HTML tags whose wikitext equivalents are zero-width.
		# This information is derived from WtTagWidths and set below.
		self::$ZeroWidthWikitextTags = PHPUtils::makeSet( [] );

		# Map LanguageConverter wikitext flags to readable JSON field names.
		self::$LCFlagMap = [
			# These first three flags are used internally during flag processing,
			# but should never appear in the output wikitext, so we prepend them
			# with '$'.

			# 'S': Show converted text
			'$S' => 'show',
			# '+': Add conversion rule
			'$+' => 'add',
			# 'E': Error in the given flags
			'$E' => 'error',

			# These rest of these are valid flags in wikitext.

			# 'A': add conversion rule *and show converted text* (implies S)
			'A' => 'add',
			# 'T': Convert and override page title
			'T' => 'title',
			# 'R': Disable language conversion (exclusive flag)
			'R' => 'disabled',
			# 'D': Describe conversion rule (without adding to table)
			'D' => 'describe',
			# '-': Remove existing conversion rule (exclusive flag)
			'-' => 'remove',
			# 'H': add rule for convert code (but no display in placed code )
			'H' => '', # this is handled implicitly as a lack of 'show'
			# 'N': Output current variant name (exclusive flag)
			'N' => 'name',
		];

		# Map JSON field names to LanguageConverter wikitext flags.
		# This information is derived from LCFlagMap and set below.
		self::$LCNameMap = [];

		# Derived information from 'WtTagWidths'
		foreach ( self::$WtTagWidths as $tag => $widths ) {
			# This special case can be fixed by maybe removing them WtTagWidths.
			# They may no longer be necessary -- to be investigated in another patch.
			if ( $tag !== 'html' && $tag !== 'head' && $tag !== 'body' ) {
				if ( $widths[0] === 0 && $widths[1] === 0 ) {
					self::$ZeroWidthWikitextTags[$tag] = true;
				}
			}
		}

		# Derived information from `LCFlagMap`
		foreach ( self::$LCFlagMap as $k => $v ) {
			if ( $v ) {
				self::$LCNameMap[$v] = $k;
			}
		}

		# Handle ambiguity in inverse mapping.
		self::$LCNameMap['add'] = 'A';
	}
}

WikitextConstants::init();
