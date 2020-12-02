<?php

namespace RemexHtml;

use RemexHtml\Tokenizer\Tokenizer;

/**
 * Generate HTMLData.php. This can be executed e.g. with
 *
 * echo 'RemexHtml\GenerateDataFiles::run()' | php bin/test.php
 *
 * or, using the psysh shell from the project root directory:
 *
 * >>> require('vendor/autoload.php');
 * >>> RemexHtml\GenerateDataFiles::run()
 */
class GenerateDataFiles {
	private const NS_HTML = 'http://www.w3.org/1999/xhtml';
	private const NS_MATHML = 'http://www.w3.org/1998/Math/MathML';
	private const NS_SVG = 'http://www.w3.org/2000/svg';
	private const NS_XLINK = 'http://www.w3.org/1999/xlink';
	private const NS_XML = 'http://www.w3.org/XML/1998/namespace';
	private const NS_XMLNS = 'http://www.w3.org/2000/xmlns/';

	/**
	 * The only public entry point
	 */
	public static function run() {
		$instance = new self;
		$instance->execute();
	}

	/**
	 * This is the character entity mapping table copied from
	 * https://www.w3.org/TR/2014/REC-html5-20141028/syntax.html#tokenizing-character-references
	 */
	private static $legacyNumericEntityData = <<<EOT
0x00 	U+FFFD 	REPLACEMENT CHARACTER
0x80 	U+20AC 	EURO SIGN (€)
0x82 	U+201A 	SINGLE LOW-9 QUOTATION MARK (‚)
0x83 	U+0192 	LATIN SMALL LETTER F WITH HOOK (ƒ)
0x84 	U+201E 	DOUBLE LOW-9 QUOTATION MARK („)
0x85 	U+2026 	HORIZONTAL ELLIPSIS (…)
0x86 	U+2020 	DAGGER (†)
0x87 	U+2021 	DOUBLE DAGGER (‡)
0x88 	U+02C6 	MODIFIER LETTER CIRCUMFLEX ACCENT (ˆ)
0x89 	U+2030 	PER MILLE SIGN (‰)
0x8A 	U+0160 	LATIN CAPITAL LETTER S WITH CARON (Š)
0x8B 	U+2039 	SINGLE LEFT-POINTING ANGLE QUOTATION MARK (‹)
0x8C 	U+0152 	LATIN CAPITAL LIGATURE OE (Œ)
0x8E 	U+017D 	LATIN CAPITAL LETTER Z WITH CARON (Ž)
0x91 	U+2018 	LEFT SINGLE QUOTATION MARK (‘)
0x92 	U+2019 	RIGHT SINGLE QUOTATION MARK (’)
0x93 	U+201C 	LEFT DOUBLE QUOTATION MARK (“)
0x94 	U+201D 	RIGHT DOUBLE QUOTATION MARK (”)
0x95 	U+2022 	BULLET (•)
0x96 	U+2013 	EN DASH (–)
0x97 	U+2014 	EM DASH (—)
0x98 	U+02DC 	SMALL TILDE (˜)
0x99 	U+2122 	TRADE MARK SIGN (™)
0x9A 	U+0161 	LATIN SMALL LETTER S WITH CARON (š)
0x9B 	U+203A 	SINGLE RIGHT-POINTING ANGLE QUOTATION MARK (›)
0x9C 	U+0153 	LATIN SMALL LIGATURE OE (œ)
0x9E 	U+017E 	LATIN SMALL LETTER Z WITH CARON (ž)
0x9F 	U+0178 	LATIN CAPITAL LETTER Y WITH DIAERESIS (Ÿ)
EOT;

	/**
	 * This is the list of public identifier prefixes that cause quirks mode
	 * to be set, from § 8.2.5.4.1
	 */
	private static $quirkyPublicPrefixes = [
		"+//Silmaril//dtd html Pro v0r11 19970101//",
		"-//AS//DTD HTML 3.0 asWedit + extensions//",
		"-//AdvaSoft Ltd//DTD HTML 3.0 asWedit + extensions//",
		"-//IETF//DTD HTML 2.0 Level 1//",
		"-//IETF//DTD HTML 2.0 Level 2//",
		"-//IETF//DTD HTML 2.0 Strict Level 1//",
		"-//IETF//DTD HTML 2.0 Strict Level 2//",
		"-//IETF//DTD HTML 2.0 Strict//",
		"-//IETF//DTD HTML 2.0//",
		"-//IETF//DTD HTML 2.1E//",
		"-//IETF//DTD HTML 3.0//",
		"-//IETF//DTD HTML 3.2 Final//",
		"-//IETF//DTD HTML 3.2//",
		"-//IETF//DTD HTML 3//",
		"-//IETF//DTD HTML Level 0//",
		"-//IETF//DTD HTML Level 1//",
		"-//IETF//DTD HTML Level 2//",
		"-//IETF//DTD HTML Level 3//",
		"-//IETF//DTD HTML Strict Level 0//",
		"-//IETF//DTD HTML Strict Level 1//",
		"-//IETF//DTD HTML Strict Level 2//",
		"-//IETF//DTD HTML Strict Level 3//",
		"-//IETF//DTD HTML Strict//",
		"-//IETF//DTD HTML//",
		"-//Metrius//DTD Metrius Presentational//",
		"-//Microsoft//DTD Internet Explorer 2.0 HTML Strict//",
		"-//Microsoft//DTD Internet Explorer 2.0 HTML//",
		"-//Microsoft//DTD Internet Explorer 2.0 Tables//",
		"-//Microsoft//DTD Internet Explorer 3.0 HTML Strict//",
		"-//Microsoft//DTD Internet Explorer 3.0 HTML//",
		"-//Microsoft//DTD Internet Explorer 3.0 Tables//",
		"-//Netscape Comm. Corp.//DTD HTML//",
		"-//Netscape Comm. Corp.//DTD Strict HTML//",
		"-//O'Reilly and Associates//DTD HTML 2.0//",
		"-//O'Reilly and Associates//DTD HTML Extended 1.0//",
		"-//O'Reilly and Associates//DTD HTML Extended Relaxed 1.0//",
		"-//SoftQuad Software//DTD HoTMetaL PRO 6.0::19990601::extensions to HTML 4.0//",
		"-//SoftQuad//DTD HoTMetaL PRO 4.0::19971010::extensions to HTML 4.0//",
		"-//Spyglass//DTD HTML 2.0 Extended//",
		"-//SQ//DTD HTML 2.0 HoTMetaL + extensions//",
		"-//Sun Microsystems Corp.//DTD HotJava HTML//",
		"-//Sun Microsystems Corp.//DTD HotJava Strict HTML//",
		"-//W3C//DTD HTML 3 1995-03-24//",
		"-//W3C//DTD HTML 3.2 Draft//",
		"-//W3C//DTD HTML 3.2 Final//",
		"-//W3C//DTD HTML 3.2//",
		"-//W3C//DTD HTML 3.2S Draft//",
		"-//W3C//DTD HTML 4.0 Frameset//",
		"-//W3C//DTD HTML 4.0 Transitional//",
		"-//W3C//DTD HTML Experimental 19960712//",
		"-//W3C//DTD HTML Experimental 970421//",
		"-//W3C//DTD W3 HTML//",
		"-//W3O//DTD W3 HTML 3.0//",
		"-//WebTechs//DTD Mozilla HTML 2.0//",
		"-//WebTechs//DTD Mozilla HTML//",
	];

	private static $special = [
		self::NS_HTML => 'address, applet, area, article, aside, base,
			basefont, bgsound, blockquote, body, br, button, caption, center,
			col, colgroup, dd, details, dir, div, dl, dt, embed, fieldset,
			figcaption, figure, footer, form, frame, frameset, h1, h2, h3, h4,
			h5, h6, head, header, hr, html, iframe, img, input, li, link,
			listing, main, marquee, menu, menuitem, meta, nav, noembed,
			noframes, noscript, object, ol, p, param, plaintext, pre, script,
			section, select, source, style, summary, table, tbody, td, template,
			textarea, tfoot, th, thead, title, tr, track, ul, wbr, xmp',
		self::NS_MATHML => 'mi, mo, mn, ms, mtext, annotation-xml',
		self::NS_SVG => 'foreignObject, desc, title',
	];

	// @codingStandardsIgnoreStart
	/**
	 * The NameStartChar production from XML 1.0, but with colon excluded since
	 * there's a lot of ways to break namespace validation, and we actually need
	 * this for local names
	 */
	private static $nameStartChar = '[A-Z] | "_" | [a-z] | [#xC0-#xD6] | [#xD8-#xF6] | [#xF8-#x2FF] | [#x370-#x37D] | [#x37F-#x1FFF] | [#x200C-#x200D] | [#x2070-#x218F] | [#x2C00-#x2FEF] | [#x3001-#xD7FF] | [#xF900-#xFDCF] | [#xFDF0-#xFFFD] | [#x10000-#xEFFFF]';

	/** The NameChar production from XML 1.0 */
	private static $nameChar = 'NameStartChar | "-" | "." | [0-9] | #xB7 | [#x0300-#x036F] | [#x203F-#x2040]';
	// @codingStandardsIgnoreEnd

	/**
	 * Build a regex alternation from an array of ampersand-prefixed entity
	 * names.
	 * @param string[] $array
	 * @return string
	 */
	private function makeRegexAlternation( $array ) {
		$regex = '';
		foreach ( $array as $value ) {
			if ( $regex !== '' ) {
				$regex .= '|';
			}
			$regex .= "\n\t\t" . preg_quote( substr( $value, 1 ), '~' );
		}
		return $regex;
	}

	private function getCharRanges( $input, $nonterminals = [] ) {
		$ranges = [];

		foreach ( preg_split( '/\s*\|\s*/', $input ) as $case ) {
			if ( preg_match( '/^"(.)"$/', $case, $m ) ) {
				// Single ASCII character
				$ranges[] = [ ord( $m[1] ), ord( $m[1] ) ];
			} elseif ( preg_match( '/^\[(.)-(.)\]$/', $case, $m ) ) {
				// ASCII range
				$ranges[] = [ ord( $m[1] ), ord( $m[2] ) ];
			} elseif ( preg_match( '/^#x([0-9A-F]+)$/', $case, $m ) ) {
				// Single encoded character
				$codepoint = intval( $m[1], 16 );
				$ranges[] = [ $codepoint, $codepoint ];
			} elseif ( preg_match( '/^\[#x([0-9A-F]+)-#x([0-9A-F]+)\]$/', $case, $m ) ) {
				// Encoded range
				$ranges[] = [ intval( $m[1], 16 ), intval( $m[2], 16 ) ];
			} elseif ( isset( $nonterminals[$case] ) ) {
				$ranges = array_merge( $ranges, $this->getCharRanges( $nonterminals[$case] ) );
			} else {
				throw new \Exception( "Invalid XML char case \"$case\"" );
			}
		}
		usort( $ranges, function ( $a, $b ) {
			return $a[0] - $b[0];
		} );
		return $ranges;
	}

	private function makeConvTable( $input, $nonterminals = [] ) {
		$ranges = $this->getCharRanges( $input, $nonterminals );

		// Invert the ranges, produce a set complement
		$lastEndPlusOne = 0;
		$table = [];
		for ( $i = 0; $i < count( $ranges ); $i++ ) {
			$start = $ranges[$i][0];
			$end = $ranges[$i][1];
			// Merge consecutive ranges
			for ( $j = $i + 1; $j < count( $ranges ); $j++ ) {
				if ( $ranges[$j][0] === $end + 1 ) {
					$end = $ranges[$j][1];
					$i = $j;
				} else {
					break;
				}
			}

			$table[] = $lastEndPlusOne;
			$table[] = $start - 1;
			$table[] = 0;
			$table[] = 0xffffff;

			$lastEndPlusOne = $end + 1;
		}

		// Last range
		$table[] = $lastEndPlusOne;
		$table[] = 0x10ffff;
		$table[] = 0;
		$table[] = 0xffffff;

		return $table;
	}

	private function encodeConvTable( $table ) {
		return "[\n\t\t" . implode( ",\n\t\t", array_map(
			function ( $a ) {
				return implode( ', ', $a );
			},
			array_chunk( $table, 4 ) ) ) . ' ]';
	}

	private function execute() {
		$filename = __DIR__ . '/entities.json';
		$entitiesJson = file_exists( $filename ) ?
			file_get_contents( $filename ) : false;

		if ( $entitiesJson === false ) {
			throw new \Exception( "Please download entities.json from " .
				"https://www.w3.org/TR/2016/REC-html51-20161101/entities.json" );
		}

		$entities = (array)json_decode( $entitiesJson );

		$entityTranslations = [];
		foreach ( $entities as $entity => $info ) {
			$entityTranslations[substr( $entity, 1 )] = $info->characters;
		}

		// Sort descending by length
		uksort( $entities, function ( $a, $b ) {
			if ( strlen( $a ) > strlen( $b ) ) {
				return -1;
			} elseif ( strlen( $a ) < strlen( $b ) ) {
				return 1;
			} else {
				return strcmp( $a, $b );
			}
		} );

		$entityRegex = $this->makeRegexAlternation( array_keys( $entities ) );
		$charRefRegex = str_replace(
			'{{NAMED_ENTITY_REGEX}}', $entityRegex, Tokenizer::CHARREF_REGEX
		);

		$matches = [];
		preg_match_all( '/^0x([0-9A-F]+)\s+U\+([0-9A-F]+)/m',
			self::$legacyNumericEntityData, $matches, PREG_SET_ORDER );

		$legacyNumericEntities = [];
		foreach ( $matches as $match ) {
			$legacyNumericEntities[ intval( $match[1], 16 ) ] =
				\UtfNormal\Utils::codepointToUtf8( intval( $match[2], 16 ) );
		}

		$quirkyRegex =
			'~' .
			$this->makeRegexAlternation( self::$quirkyPublicPrefixes ) .
			'~xAi';

		$nameStartCharConvTable = $this->makeConvTable( self::$nameStartChar );
		$nameCharConvTable = $this->makeConvTable( self::$nameChar,
			[ 'NameStartChar' => self::$nameStartChar ] );

		$encEntityRegex = var_export( $entityRegex, true );
		$encCharRefRegex = var_export( $charRefRegex, true );
		$encTranslations = var_export( $entityTranslations, true );
		$encLegacy = var_export( $legacyNumericEntities, true );
		$encQuirkyRegex = var_export( $quirkyRegex, true );
		$encNameStartCharConvTable = $this->encodeConvTable( $nameStartCharConvTable );
		$encNameCharConvTable = $this->encodeConvTable( $nameCharConvTable );

		$special = [];
		foreach ( self::$special as $ns => $str ) {
			foreach ( explode( ',', $str ) as $name ) {
				$special[$ns][trim( $name )] = true;
			}
		}
		$encSpecial = var_export( $special, true );

		$nsHtml = var_export( self::NS_HTML, true );
		$nsMathML = var_export( self::NS_MATHML, true );
		$nsSvg = var_export( self::NS_SVG, true );
		$nsXlink = var_export( self::NS_XLINK, true );
		$nsXml = var_export( self::NS_XML, true );
		$nsXmlNs = var_export( self::NS_XMLNS, true );

		$fileContents = '<' . <<<PHP
?php

/**
 * This data file is machine generated, see GenerateDataFiles.php
 */

namespace RemexHtml;

class HTMLData {
	public const NS_HTML = $nsHtml;
	public const NS_MATHML = $nsMathML;
	public const NS_SVG = $nsSvg;
	public const NS_XLINK = $nsXlink;
	public const NS_XML = $nsXml;
	public const NS_XMLNS = $nsXmlNs;

	static public \$special = $encSpecial;
	static public \$namedEntityRegex = $encEntityRegex;
	static public \$charRefRegex = $encCharRefRegex;
	static public \$namedEntityTranslations = $encTranslations;
	static public \$legacyNumericEntities = $encLegacy;
	static public \$quirkyPrefixRegex = $encQuirkyRegex;
	static public \$nameStartCharConvTable = $encNameStartCharConvTable;
	static public \$nameCharConvTable = $encNameCharConvTable;
}
PHP;

		file_put_contents( __DIR__ . '/HTMLData.php', $fileContents );
	}
}
