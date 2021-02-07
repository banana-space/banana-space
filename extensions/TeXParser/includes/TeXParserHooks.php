<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = dirname(__DIR__);
		$updater->addExtensionTable('banana_subpage', "$dir/base.sql");
	}

    public static function initExtension() {
		// Change how section IDs should be encoded;
		// Default bahaviour: #.FF.FF.FF; changed behaviour: unicode string.
        global $wgFragmentMode;
        $wgFragmentMode = [ 'html5' ];
    }

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

	public static function onParserBeforeInternalParse( Parser &$parser, &$text ) {
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

	public static function onPageSaveComplete( WikiPage $wikiPage ) { 
		$title = $wikiPage->getTitle();
		$options = $wikiPage->makeParserOptions('canonical');
		$output = $wikiPage->getParserOutput($options);

		// Update subpages and labels in database
		$text = $output->getText();
		$dom = new DOMDocument();
		if (!@$dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8')))
			return;
		$xpath = new DOMXpath($dom);
		$data = '';

		$elements = $xpath->query('//div[@class="compiler-data"]');
		if ($elements->length === 1) {
			/** @var DOMElement $element */
			$element = $elements->item(0);
			$data = $element->hasAttribute('data-json') ? $element->getAttribute('data-json') : '';
		}
		SubpageHandler::updatePageData($title, $data);
	}

	// Replace $element by $html in $dom.
	private static function domReplace( DOMDocument $dom, $html, DOMNode $element ) {
		$fragment = $dom->createDocumentFragment();
		$fragment->appendXML($html);
		if ($fragment) $element->parentNode->insertBefore($fragment, $element);
		$element->parentNode->removeChild($element);
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
		
		// Check if page has subpages or is a subpage; if so, get labels.
		$title = $parser->getTitle();
		$checkLabels = strpos($title->getText(), '/') !== false;
		if (!$checkLabels) {
			$elements = $xpath->query('//div[@class="compiler-data"]');
			if ($elements->length === 1) {
				/** @var DOMElement $element */
				$element = $elements->item(0);
				if (
					$element->hasAttribute('data-json') &&
					strpos($element->getAttribute('data-json'), '"subpages":') !== false
				)
					$checkLabels = true;
			}
		}

		$labels = [];
		if ($checkLabels) {
			$labels = SubpageHandler::getLabels($title);
		}

		// References
		$refs = $xpath->query('//btex-ref');
		$prefix = null;
		/** @var DOMElement $element */
		foreach ($refs as $element) {
			$result = '';
			if ($element->hasAttribute('data-key')) {
				$key = $element->getAttribute('data-key');

				if ($key === '--prefix--') {
					$result = $prefix ?? ($prefix = SubpageHandler::getPagePrefix($title));
				} else {
					$label = $labels[$key];
					if (isset($label)) {
						$result = $label['text'];
					} else {
						$result = '<span class="undefined-reference">??</span>';
					}
				}
			}
			self::domReplace($dom, $result, $element);
		}

		// Links, [[...]]
		$links = $xpath->query('//btex-link');
		/** @var DOMElement $element */
		foreach ($links as $element) {
			// use mw parser to generate internal link
			$pageName = '';
			if ($element->hasAttribute('data-page')) {
				$pageName = self::escapeBracketsAndPipes($element->getAttribute('data-page'));
				if (substr($pageName, 0, 2) === './')
					$pageName = $parser->getTitle()->getPrefixedText() . substr($pageName, 1);
			} else if ($element->hasAttribute('data-key')) {
				$label = $labels[$element->getAttribute('data-key')];
				if (isset($label)) {
					$targetTitle = Title::newFromID($label['page_id']);
					$pageName = self::escapeBracketsAndPipes($targetTitle->getPrefixedText() . '#' . urlencode($label['target']));
				}
			}

			$content = '';
			foreach ($element->childNodes as $child)
				$content .= $dom->saveHTML($child);
			$content = self::escapeBracketsAndPipes($content);

			if ($pageName !== '') {
				$result = $mwHandleInternalLinks->call($parser, "[[$pageName|$content]]");
				self::domReplace($dom, $result, $element);
			} else {
				self::domReplace($dom, $content, $element);
			}
		}

		// Functions, {{...}}
		$funs = $xpath->query('//btex-fun');
		/** @var DOMNode $element */
		foreach ($funs as $element) {
			$funName = self::escapeBracketsAndPipes($element->attributes['data-name']->nodeValue);

			$text = '{{' . $funName;
			foreach ($element->childNodes as $child) {
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

			self::domReplace($dom, $text, $element);
		}

		$text = $dom->saveHTML();
	}

	private static function addSpaces($text) {
		// MW uses sequences like '"1 to replace parameters
		$text = preg_replace( '#((?<!\'")[\p{Ll}\p{Lu}\p{Nd}\p{Mn}’”])([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])#u', '$1 $2', $text );
		$text = preg_replace( '#([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])([\p{Ll}\p{Lu}\p{Nd}\p{Mn}‘“])#u', '$1 $2', $text );
		return $text;
	}
}
