<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {
	static function http_post_json($url, $jsonStr) {
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
			// Read labels from database
			$title = $parser->getTitle();
			$labels = LabelHandler::getLabels($title);

			// Run compiler
			$url = "http://127.0.0.1:7200";
			$jsonStr = json_encode(
				array(
					"code" => $text,
					"labels" => $labels
				)
			);
			list($httpCode, $response) = self::http_post_json($url, $jsonStr);
			$json = json_decode($response);

			// Output
			$text = $json->html;

			// When not in preview mode,
			// write labels to database
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
}
