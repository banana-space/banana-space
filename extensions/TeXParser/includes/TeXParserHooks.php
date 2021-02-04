<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {
	private static function http_post_json($url, $jsonStr) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	            'Content-Type: application/json; charset=utf-8',
	            'Content-Length: ' . strlen($jsonStr)
	        )
	    );
	    $response = curl_exec($ch);
	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close($ch);

	    return array($httpCode, $response);
	}

	public static function onParserBeforeInternalParse( Parser &$parser, &$text, &$stripState ) {
		// If text is page content, use bTeX.
		if (!$parser->getOptions()->getInterfaceMessage()) {
			// Use wikitext parser for templates
			$title = $parser->getTitle();
			if ($title->mNamespace === NS_TEMPLATE) {
				return true;
			}

			// Run compiler
			// TODO: result should be stored in database
			$url = "http://127.0.0.1:7200";
			$jsonStr = json_encode(
				array(
					"code" => $text
				)
			);
			list($httpCode, $response) = self::http_post_json($url, $jsonStr);
			$json = json_decode($response);
			$text = $json->html;

			// Handle <btex-link> etc. in btex output
			self::handleWikitextAfterBtex($parser, $text);

			// TODO: set display title
			// $parser->getOutput()->setTitleText( ... )

			return false;
		}

		// Text is not page content; use wikitext parser.
		$text = self::addSpaces($text);
		return true;
	}

	public static function onBeforePageDisplay( OutputPage $output, Skin $skin ) {
		// Load css for btex output
		$output->addModules( "ext.TeXParser" );
	}

	// Replace $node by $html in $dom.
	private static function domReplace( DOMDocument $dom, $html, DOMNode $node ) {
		$fragment = $dom->createDocumentFragment();
		$fragment->appendXML($html);
		if ($fragment) $node->parentNode->insertBefore($fragment, $node);
		$node->parentNode->removeChild($node);
	}

	private static function escapeBracketsAndPipes( $str ) {
		$str = str_replace( '[', '&#91;', $str );
		$str = str_replace( ']', '&#93;', $str );
		$str = str_replace( '{', '&#123;', $str );
		$str = str_replace( '|', '&#124;', $str );
		$str = str_replace( '}', '&#125;', $str );
		return $str;
	}

	private static function handleWikitextAfterBtex( Parser &$parser, &$text ) {
		// A hack to call private functions
		$mwHandleTables           = function ($text) { return $this->handleTables           ($text); };
		$mwHandleDoubleUnderscore = function ($text) { return $this->handleDoubleUnderscore ($text); };
		$mwHandleHeadings         = function ($text) { return $this->handleHeadings         ($text); };
		$mwHandleInternalLinks    = function ($text) { return $this->handleInternalLinks    ($text); };
		$mwHandleAllQuotes        = function ($text) { return $this->handleAllQuotes        ($text); };
		$mwHandleExternalLinks    = function ($text) { return $this->handleExternalLinks    ($text); };
		$mwHandleMagicLinks       = function ($text) { return $this->handleMagicLinks       ($text); };

		$dom = new DOMDocument();
		// Suppress warnings on custom tags <btex-link> etc.
		if (!@$dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8')))
			return;
		$xpath = new DOMXpath($dom);
		
		// Links, [[...]]
		$links = $xpath->query('//btex-link');
		foreach ($links as $node) {
			// If data-page is set, use mw parser to generate internal link
			if (isset($node->attributes['data-page'])) {
				$pageName = self::escapeBracketsAndPipes($node->attributes['data-page']->nodeValue);

				$content = '';
				foreach ($node->childNodes as $child)
					$content .= $dom->saveHTML($child);
				$content = self::escapeBracketsAndPipes($content);

				$result = $mwHandleInternalLinks->call($parser, "[[$pageName|$content]]");
				self::domReplace($dom, $result, $node);
			}
		}

		// Functions, {{...}}
		$funs = $xpath->query('//btex-fun');
		/** @var DOMNode $node */
		foreach ($funs as $node) {
			$funName = self::escapeBracketsAndPipes($node->attributes['data-name']->nodeValue);

			$text = '{{' . $funName;
			foreach ($node->childNodes as $child) {
				if ($child->nodeName !== 'btex-arg') continue;

				$content = '';
				foreach ($child->childNodes as $grandchild)
					$content .= $dom->saveHTML($grandchild);
				$content = self::escapeBracketsAndPipes($content);

				$text .= "|$content";
			}

			$text .= '}}';

			// Run $text through MediaWiki parser.
			// We are skipping the sanitising process, as $text
			// comes out of btex and is safe.
			$text = $parser->replaceVariables($text);
			$text = $parser->getStripState()->unstripBoth($text);
			$text = $mwHandleTables->call($parser, $text);
			$text = preg_replace('/(^|\n)-----*/', '\\1<hr />', $text);
			$text = $mwHandleDoubleUnderscore->call($parser, $text);
			$text = $mwHandleHeadings->call($parser, $text);
			$text = $mwHandleInternalLinks->call($parser, $text);
			$text = $mwHandleAllQuotes->call($parser, $text);
			$text = $mwHandleExternalLinks->call($parser, $text);
			$text = str_replace(Parser::MARKER_PREFIX . 'NOPARSE', '', $text);
			$text = $mwHandleMagicLinks->call($parser, $text);

			self::domReplace($dom, $text, $node);
		}

		$text = $dom->saveHTML();
	}

	private static function addSpaces($text) {
		$text = preg_replace( '#([\p{Ll}\p{Lu}\p{Nd}\p{Mn}:’”])([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])#u', '$1 $2', $text );
		$text = preg_replace( '#([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])([\p{Ll}\p{Lu}\p{Nd}\p{Mn}‘“])#u', '$1 $2', $text );
		return $text;
	}
}
