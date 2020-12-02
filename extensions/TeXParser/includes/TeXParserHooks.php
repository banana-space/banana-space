<?php
/**
 * Hooks for TeXParser
 *
 * @file
 * @ingroup Extensions
 */

class TeXParserHooks {
	static function http_post_json($url, $jsonStr){
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

	public static function onParserBeforeInternalParse( &$parser, &$text, &$stripState ){
		if(!$parser->getOptions()->getInterfaceMessage())
		{
			//Content detected, should send it to the parser js exec.

			$url="http://127.0.0.1:7200";
			$jsonStr=json_encode(array("code" => $text));
			list($returnCode, $returnContent) = self::http_post_json($url, $jsonStr);
			$text = json_decode($returnContent)->html;
			return false;
		}
		return true;
	}
}
