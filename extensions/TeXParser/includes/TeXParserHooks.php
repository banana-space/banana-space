<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {
	private const BTEX_URL = "http://127.0.0.1:7200";

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$dir = dirname(__DIR__);
		$updater->addExtensionTable('banana_subpage', "$dir/base.sql");
	}

	/**
	 * Initialise the extension.
	 */
    public static function initExtension() {
		global $wgNamespaceAliases;
		$wgNamespaceAliases['Notes'] = NS_NOTES;
		$wgNamespaceAliases['Notes_talk'] = NS_NOTES_TALK;
		$wgNamespaceAliases['Discussion'] = NS_DISCUSSION;

		// Change how section IDs should be encoded;
		// Default bahaviour: #.FF.FF.FF; changed behaviour: unicode string.
        global $wgFragmentMode;
		$wgFragmentMode = [ 'html5' ];
    }

	public static function onParserBeforeInternalParse( Parser &$parser, &$text ) {
		// If text is page content, use bTeX.
		if (!$parser->getOptions()->getInterfaceMessage()) {
			// Use wikitext parser for templates
			$title = $parser->getTitle();
			if (in_array( $title->getNamespace(), [ NS_TEMPLATE, NS_MODULE ] )) {
				return true;
			}

			$isPreview = $parser->getOptions()->getIsPreview();
			$subpageInfo = SubpageHandler::getSubpageInfo($title);

			// If is preamble, return content wrapped in <pre>
			if ($subpageInfo && preg_match('#/preamble$#', $title->getText())) {
				if ($text !== '') {
					$dom = new DOMDocument();
					$pre = $dom->createElement('pre');
					$pre->setAttribute('class', 'code-btex');
					$textNode = $dom->createTextNode($text);
					$pre->appendChild($textNode);
					$text = $dom->saveHTML($pre);

					// Run compiler to get error messages
					if ($isPreview) {
						$jsonStr = json_encode( [ "code" => $text ] );
						list($httpCode, $response) = self::http_post_json(self::BTEX_URL, $jsonStr);
						$json = json_decode($response);
						self::generateErrorMessages($json, $isPreview, $text);
					}
				}

				self::handleCompilerData($parser, $subpageInfo, '{}');
				return false;
			}

			$preamble = self::getPreambleFromSubpageInfo($subpageInfo);

			$isInline = $parser->getOptions()->getOption('isInline');

			// Try to get cached btex output
			$btexOutput = null;
			if (!$isPreview) {
				$btexOutput = BananaParsoid::getFromDatabase($title, $text, $preamble);
			}

			// If no cache is found, run compiler
			if (!isset($btexOutput)) {
				$jsonStr = json_encode(
					[
						"code" => $text,
						"preamble" => $preamble,
						"inverseSearch" => $isPreview,
						"inline" => $isInline
					]
				);
				[ $httpCode, $response ] = self::http_post_json(self::BTEX_URL, $jsonStr);
				$btexOutput = $response;
			}

			$json = json_decode($btexOutput);
			$text = $json->html ?? '';

			$output = $parser->getOutput();
			$output->setExtensionData('btex-data', $json->data ?? '{}');
			$output->setExtensionData('btex-output', $btexOutput);

			// Handle <btex-link> etc. in btex output
			self::handleWikitextAfterBtex($parser, $text);
			self::handleCompilerData($parser, $subpageInfo, $json->data ?? '{}');
			self::generateErrorMessages($json, $isPreview, $text);

			return false;
		}

		// Text is not page content; use wikitext parser.
		$text = self::addSpaces($text);
		return true;
	}

	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		foreach ([ 'btex-html-title' ] as $key) {
			$value = $parserOutput->getExtensionData($key);
			if (isset($value)) $out->setProperty($key, $value);
		}
	}

	public static function onBeforePageDisplay( OutputPage $output, Skin $skin ) {
		// Load css for btex output
		$output->addModules( "ext.TeXParser" );

		// Add meta tag
		$output->addMeta('viewport', 'width=500, initial-scale=1');
		
		// Add script for syntax highlighting lua and css
		$title = $output->getTitle();
		$contentModel = $title->getContentModel();
		$action = Action::getActionName($output->getContext());
		if (in_array($contentModel, [ 'Scribunto', 'sanitized-css', 'css' ]) && $action === 'view') {
			// load monaco-editor for syntax highlighting
			$output->addScript(
				'<script>var require = { paths: { vs: "/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs" }, };</script>' .
				'<script src="/static/scripts/btex-monaco/node_modules/monaco-editor/min/vs/loader.js"></script>'
			);
		}

		$htmlTitle = $output->getProperty('btex-html-title');
		if ($action === 'view' && isset($htmlTitle)) {
			global $wgSitename;
			$output->setHTMLTitle($htmlTitle . ' - ' . $wgSitename);
		}

		if ($action === 'edit' && $title->getNamespace() === NS_NOTES) {
			
		}

		// Change page title from 'Prefix:Title' to 'Prefix: Title'
		$ns = $title->getNamespace();
		if ($ns !== NS_MAIN) {
			$prefix = ($title->getNsText() ?? '') . ':';
			$displayTitle = $output->getPageTitle();
			if (substr($displayTitle, 0, strlen($prefix)) === $prefix) {
				// setPageTitle works for flow discussions; setDisplayTitle doesn't.
				$output->setPageTitle( $prefix . ' ' . substr( $displayTitle, strlen($prefix) ) );
			}
		}
	}

	public static function onPageSaveComplete( WikiPage $wikiPage ) { 
		$title = $wikiPage->getTitle();
		$options = $wikiPage->makeParserOptions('canonical');
		$output = $wikiPage->getParserOutput($options);
		SubpageHandler::updatePageData($title, $output);

		$preamble = '';

		if ($title->getNamespace() === NS_NOTES) {
			$info = SubpageHandler::getSubpageInfo($title);
			if ($info !== false) {
				self::purgeSubpages($info, $title);
			}

			$preamble = self::getPreambleFromSubpageInfo($info);
		}

		if ($output && $wikiPage->getContentModel() === CONTENT_MODEL_WIKITEXT) {
			$code = $wikiPage->getContent()->getText();
			$btexOutput = $output->getExtensionData('btex-output');
			BananaParsoid::writeToDatabase($title, $btexOutput, $code, $preamble);
		}
	}

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook(
			'math',
			[ self::class, 'mathParserFunction' ],
			Parser::SFH_NO_HASH
		);
	}

	/**
	 * For use in templates, since we don't use btex for templates.
	 */
	public static function mathParserFunction( Parser $parser, $param = '' ) {
		$jsonStr = json_encode(
			array(
				"code" => $param,
				"equationMode" => true
			)
		);
		list($httpCode, $response) = self::http_post_json(self::BTEX_URL, $jsonStr);
		$json = json_decode($response);
		$output = $json->html;
  
		return [ $output, 'nowiki' => true, 'isHTML' => true ];
	 }

	public static function onParserOptionsRegister( &$defaults, &$inCacheKey, &$lazyLoad ) {
		$defaults['isInline'] = false;
		$inCacheKey['isInline'] = true;
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

	// Replace $element by $html in $dom.
	private static function domReplace( DOMDocument $dom, $html, DOMNode $element ) {
		if ($html !== '') {
			$fragment = $dom->createDocumentFragment();
			$fragment->appendXML($html);
			if ($fragment !== false)
				$element->parentNode->insertBefore($fragment, $element);
		}
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
			$data = $parser->getOutput()->getExtensionData('btex-data');
			if (isset($data) && strpos($data, '"subpages":') !== false) {
				$checkLabels = true;
			}
		}

		$labels = [];
		if ($checkLabels) {
			$labels = SubpageHandler::getLabels($title);
		}

		// References
		$refs = $xpath->query('//btex-ref');
		$prefix = SubpageHandler::getPagePrefix($title);
		/** @var DOMElement $element */
		foreach ($refs as $element) {
			$result = '';
			if ($element->hasAttribute('data-key')) {
				$key = $element->getAttribute('data-key');

				if ($key === '--prefix--') {
					$result = $prefix;
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

	private static function handleCompilerData( Parser $parser, $subpageInfo, $compilerData ) {
		$output = $parser->getOutput();
		$info = $subpageInfo;
		$prefix = null;
		
		if ($info !== false) {
			// A hack to call private functions
			$mwHandleInternalLinks = function ($text) { return $this->handleInternalLinks($text); };

			// Set display title
			if (isset($info['display'])) {
				$output->setDisplayTitle( trim($info['prefix'] . ' ' . $info['display']) );
				$output->setExtensionData('btex-data', $compilerData);

				$prefix = $info['prefix'];
			}

			$parent = '<li>讲义: [[讲义:' .
				self::escapeBracketsAndPipes($info['parent_title']) . '|' .
				self::escapeBracketsAndPipes($info['parent_display'] ?? $info['parent_title']) . ']]</li>';

			$prev = isset($info['prev_title']) ?
				'<li>上一节: [[讲义:' .
				self::escapeBracketsAndPipes($info['prev_title']) . '|' .
				self::escapeBracketsAndPipes(trim($info['prev_prefix'] . ' ' . $info['prev_display'])) . ']]</li>' : '';

			$next = isset($info['next_title']) ?
				'<li>下一节: [[讲义:' .
				self::escapeBracketsAndPipes($info['next_title']) . '|' .
				self::escapeBracketsAndPipes(trim($info['next_prefix'] . ' ' . $info['next_display'])) . ']]</li>' : '';

			$preamble = '<li class="link-to-preamble">TeX 导言: [[讲义:' .
				self::escapeBracketsAndPipes($info['parent_title']) . '/preamble|' .
				self::escapeBracketsAndPipes($info['parent_display'] ?? $info['parent_title']) . '/preamble]]</li>';

			$nav = $mwHandleInternalLinks->call($parser, '<ul>' . $parent . $prev . $next . $preamble . '</ul>');
			$parser->replaceLinkHolders($nav);
			$output->setExtensionData('btex-before', $nav);
		}

		$json = json_decode($compilerData);

		if (isset($json->externalLinks)) {
			foreach ($json->externalLinks as $link) {
				$output->addExternalLink($link);
			}
		}

		if (isset($json->displayTitle)) {
			$displayTitle = $json->displayTitle;
			if ($prefix) $displayTitle = $prefix . ' ' . $displayTitle;
			$output->setDisplayTitle($displayTitle);
		}

		if (isset($json->htmlTitle)) {
			$htmlTitle = $json->htmlTitle;
			if ($prefix) $htmlTitle = html_entity_decode($prefix) . ' ' . $htmlTitle;
			$output->setExtensionData('btex-html-title', $htmlTitle);
		}

		if (isset($json->lang)) {
			$output->setExtensionData('btex-page-lang', $json->lang);
		}
	}

	private static function addSpaces($text) {
		// MW uses sequences like '"1 to replace parameters
		$text = preg_replace( '#((?<!\'")[\p{Ll}\p{Lu}\p{Nd}\p{Mn}’”])([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])#u', '$1 $2', $text );
		$text = preg_replace( '#([\p{Han}\p{Hangul}\p{Hiragana}\p{Katakana}])([\p{Ll}\p{Lu}\p{Nd}\p{Mn}‘“])#u', '$1 $2', $text );
		return $text;
	}

	private static function generateErrorMessages($json, bool $isPreview, string &$text) {
		$errors = $json->errors ?? [];
		$warnings = $json->warnings ?? [];

		if (!$isPreview) {
			if ($text === '' && isset($errors[0]))
				$text = '<span class="error">[编译错误]</span>';
			return;
		}

		$prepend = '';
		foreach ($errors as $error) {
			$message = self::getErrorMessage($error);
			if ($message)
				$prepend .= '<div class="error-message"><b>错误</b> - ' . $message . '</div>';
		}
		foreach ($warnings as $error) {
			$message = self::getErrorMessage($error);
			if ($message)
				$prepend .= '<div class="warning-message"><b>警告</b> - ' . $message . '</div>';
		}

		$text = $prepend . $text;
	}

	private static function getErrorMessage($error) {
		$match = [];
		if (!preg_match('#^([^:\s]*):(\d+):(\d+) (.+)#u', $error, $match)) return false;

		$positionString = '';
		if ($match[1] === 'code') $positionString = "第 $match[2] 行, 第 $match[3] 列: ";
		if ($match[1] === 'preamble') $positionString = "(preamble) 第 $match[2] 行, 第 $match[3] 列: ";

		return $positionString . $match[4];
	}

	private static function purgeSubpages($subpageInfo, Title $except = null) {
		$exceptID = -1;
		if (isset($except)) $exceptID = $except->getArticleID();

		if (isset($subpageInfo['rows'])) {
			foreach ($subpageInfo['rows'] as $row) {
				$title = Title::makeTitle(NS_NOTES, $row->page_title);
				if ($title->exists()) {
					$id = $title->getArticleID();
					if ($id !== $exceptID) {
						$page = WikiPage::newFromID($id);
						$page->doPurge();
					}
				}
			}
		}
	}

	private static function getPreambleFromSubpageInfo($subpageInfo) {
		$preamble = '';
		if ($subpageInfo !== false) {
			$preambleName = $subpageInfo['parent_title'] . '/preamble';
			$preambleTitle = Title::makeTitle(NS_NOTES, $preambleName);
			if ($preambleTitle->exists()) {
				$content = WikiPage::newFromID($preambleTitle->getArticleID())->getContent();
				if ($content instanceof TextContent) {
					$preamble = $content->getText();
				}
			}
		}

		return $preamble;
	}
}
