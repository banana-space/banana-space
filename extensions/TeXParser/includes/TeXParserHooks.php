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
			// Run compiler
			$url = "http://127.0.0.1:7200";
			$jsonStr = json_encode(
				array(
					"code" => $text
				)
			);
			list($httpCode, $response) = self::http_post_json($url, $jsonStr);
			$json = json_decode($response);
			$text = $json->html;

			// Handle <btex-link> in btex output
			self::handleLinks($parser, $text);

			// TODO: set display title
			// $parser->getOutput()->setTitleText( ... )

			// When not in preview mode,
			// write labels to database
			$title = $parser->getTitle();
			if ($parser->getRevisionId() !== null) {
				LabelHandler::setLabels($title, $json->labels);
			}

			return false;
		}

		// Text is not page content; use wikitext parser.
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
		$node->parentNode->insertBefore($fragment, $node);
		$node->parentNode->removeChild($node);
	}

	private static function escapeBracketsAndPipes( $str ) {
		$str = str_replace( '[', '&#91;', $str );
		$str = str_replace( ']', '&#93;', $str );
		$str = str_replace( '|', '&#124;', $str );
		return $str;
	}

	private static function handleLinks( Parser &$parser, &$text ) {
		// A hack to call private functions
		$mwHandleInternalLinks = function ( $text ) {
			return $this->handleInternalLinks( $text );
		};

		$dom = new DOMDocument();
		// Suppress warnings on custom tags <btex-link> etc.
		if (!@$dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8')))
			return;
		$xpath = new DOMXpath($dom);
		
		$links = $xpath->query('//btex-link');
		foreach ($links as $node) {
			// If data-page is set, use mw parser to generate internal link
			if (isset($node->attributes['data-page'])) {
				$pageName = self::escapeBracketsAndPipes($node->attributes['data-page']->nodeValue);

				$content = '';
				foreach ($node->childNodes as $child) {
					$content .= $dom->saveHTML($child);
				}
				$content = self::escapeBracketsAndPipes($content);

				$result = $mwHandleInternalLinks->call($parser, "[[$pageName|$content]]");
				self::domReplace($dom, $result, $node);
			}
		}

		$text = $dom->saveHTML();
	}
}
