<?php
/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
declare( strict_types = 1 );

namespace MWParsoid\Rest\Handler;

use MediaWiki\Rest\Response;
use MWParsoid\Rest\FormatHelper;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Handler for transforming content given in the request.
 * - /{domain}/v3/transform/{from}/to/{format}
 * - /{domain}/v3/transform/{from}/to/{format}/{title}
 * - /{domain}/v3/transform/{from}/to/{format}/{title}/{revision}
 * @see https://www.mediawiki.org/wiki/Parsoid/API#POST
 */
class TransformHandler extends ParsoidHandler {

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'domain' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'from' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'format' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'revision' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	/**
	 * Transform content given in the request from or to wikitext.
	 * @return Response
	 */
	public function execute() {
		$request = $this->getRequest();
		$from = $request->getPathParam( 'from' );
		$format = $request->getPathParam( 'format' );

		if (
			!isset( FormatHelper::VALID_TRANSFORM[$from] ) ||
			!in_array( $format, FormatHelper::VALID_TRANSFORM[$from], true )
		) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => "Invalid transform: ${from}/to/${format}",
			] );
		}

		$attribs = &$this->getRequestAttributes();

		if ( !$this->acceptable( $attribs ) ) {
			return $this->getResponseFactory()->createHttpError( 406, [
				'message' => 'Not acceptable',
			] );
		}

		if ( $from === FormatHelper::FORMAT_WIKITEXT ) {
			// Accept wikitext as a string or object{body,headers}
			$wikitext = $attribs['opts']['wikitext'] ?? null;
			if ( is_array( $wikitext ) ) {
				$wikitext = $wikitext['body'];
				// We've been given a pagelanguage for this page.
				if ( isset( $attribs['opts']['wikitext']['headers']['content-language'] ) ) {
					$attribs['pagelanguage'] = $attribs['opts']['wikitext']['headers']['content-language'];
				}
			}
			// We've been given source for this page
			if ( $wikitext === null && isset( $attribs['opts']['original']['wikitext'] ) ) {
				$wikitext = $attribs['opts']['original']['wikitext']['body'];
				// We've been given a pagelanguage for this page.
				if ( isset( $attribs['opts']['original']['wikitext']['headers']['content-language'] ) ) {
					$attribs['pagelanguage']
						= $attribs['opts']['original']['wikitext']['headers']['content-language'];
				}
			}
			// Abort if no wikitext or title.
			if ( $wikitext === null && $attribs['titleMissing'] ) {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => 'No title or wikitext was provided.',
				] );
			}
			$pageConfig = $this->createPageConfig(
				$attribs['pageName'], (int)$attribs['oldid'], $wikitext,
				$attribs['pagelanguage']
			);
			return $this->wt2html( $pageConfig, $attribs, $wikitext );
		} elseif ( $format === FormatHelper::FORMAT_WIKITEXT ) {
			$html = $attribs['opts']['html'] ?? null;
			if ( $html === null ) {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => 'No html was supplied.',
				] );
			}
			// Accept html as a string or object{body,headers}
			if ( is_array( $html ) ) {
				$html = $html['body'];
			}
			$wikitext = $attribs['opts']['original']['wikitext']['body'] ?? null;
			$pageConfig = $this->createPageConfig(
				$attribs['pageName'], (int)$attribs['oldid'], $wikitext
			);
			$hasOldId = (bool)$attribs['oldid'];
			if ( $hasOldId && $pageConfig->getRevisionContent() === null ) {
				return $this->getResponseFactory()->createHttpError( 404, [
					'message' => 'The specified revision does not exist.',
				] );
			}
			return $this->html2wt( $pageConfig, $attribs, $html );
		} else {
			$pageConfig = $this->createPageConfig(
				$attribs['pageName'], (int)$attribs['oldid'], null,
				$attribs['pagelanguage']
			);
			return $this->pb2pb( $pageConfig, $attribs );
		}
	}

}
