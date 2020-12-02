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

namespace MWParsoid\Test;

use Html;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Parser;
use ParserOptions;
use ParserTestResult;
use ParserTestResultNormalizer;
use RequestContext;
use Title;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;
use WikitextContent;

// This file is not yet complete (and some methods in core need to be made
// protected rather than private):
// @phan-file-suppress PhanAccessPropertyPrivate
// @phan-file-suppress PhanAccessMethodPrivate
class IntegratedTestRunner extends \ParserTestRunner {

	/**
	 * Override the base class runTest() method to use Parsoid.
	 * @inheritDoc
	 */
	public function runTest( $test ) {
		wfDebug( __METHOD__ . ": running {$test['desc']}" );
		$opts = $this->parseOptions( $test['options'] ); // XXX FIXME PRIVATE
		// Skip tests targetting features Parsoid doesn't (yet?) support
		if ( isset( $opts['pst'] ) || isset( $opts['msg'] ) ||
			 isset( $opts['section'] ) || isset( $opts['replace'] ) ||
			 isset( $opts['comment'] ) || isset( $opts['preload'] ) ) {
			return false;
		}
		$teardownGuard = $this->perTestSetup( $test );

		$context = RequestContext::getMain();
		$user = $context->getUser();
		$options = ParserOptions::newFromContext( $context );
		$options->setTimestamp( $this->getFakeTimestamp() );
		$revId = 1337; // see Parser::getRevisionId()
		$title = isset( $opts['title'] )
			? Title::newFromText( $opts['title'] )
			: $this->defaultTitle;

		if ( isset( $opts['lastsavedrevision'] ) ) {
			$content = new WikitextContent( $test['input'] );
			$title = Title::newFromRow( (object)[
				'page_id' => 187,
				'page_len' => $content->getSize(),
				'page_latest' => 1337,
				'page_namespace' => $title->getNamespace(),
				'page_title' => $title->getDBkey(),
				'page_is_redirect' => 0
			] );

			$revRecord = new MutableRevisionRecord( $title );
			$revRecord->setContent( SlotRecord::MAIN, $content );
			$revRecord->setUser( $user );
			$revRecord->setTimestamp( strval( $this->getFakeTimestamp() ) );
			$revRecord->setPageId( $title->getArticleID() );
			$revRecord->setId( $title->getLatestRevID() );

			$oldCallback = $options->getCurrentRevisionRecordCallback();
			$options->setCurrentRevisionRecordCallback(
				function ( Title $t, $parser ) use ( $title, $revRecord, $oldCallback ) {
					if ( $t->equals( $title ) ) {
						return $revRecord;
					} else {
						return $oldCallback( $t, $parser );
					}
				}
			);
		}

		if ( isset( $opts['maxincludesize'] ) ) {
			$options->setMaxIncludeSize( $opts['maxincludesize'] );
		}
		if ( isset( $opts['maxtemplatedepth'] ) ) {
			$options->setMaxTemplateDepth( $opts['maxtemplatedepth'] );
		}

		$local = isset( $opts['local'] );
		$parser = $this->getParser();

		if ( isset( $opts['styletag'] ) ) {
			// For testing the behavior of <style> (including those deduplicated
			// into <link> tags), add tag hooks to allow them to be generated.
			$parser->setHook( 'style', function ( $content, $attributes, $parser ) {
				$marker = Parser::MARKER_PREFIX . '-style-' . md5( $content ) . Parser::MARKER_SUFFIX;
				// @phan-suppress-next-line SecurityCheck-XSS
				$parser->mStripState->addNoWiki( $marker, $content );
				return Html::inlineStyle( $marker, 'all', $attributes );
			} );
			$parser->setHook( 'link', function ( $content, $attributes, $parser ) {
				return Html::element( 'link', $attributes );
			} );
		}

		// Run the parser test!

		$output = $parser->parse( $test['input'], $title, $options, true, true, $revId );
		$out = $output->getText( [
			'allowTOC' => !isset( $opts['notoc'] ),
			'unwrap' => !isset( $opts['wrap'] ),
		] );
		if ( isset( $opts['tidy'] ) ) {
			$out = preg_replace( '/\s+$/', '', $out );
		}

		if ( isset( $opts['showtitle'] ) ) {
			if ( $output->getTitleText() ) {
				$title = $output->getTitleText();
			}

			$out = "$title\n$out";
		}

		if ( isset( $opts['showindicators'] ) ) {
			$indicators = '';
			foreach ( $output->getIndicators() as $id => $content ) {
				$indicators .= "$id=$content\n";
			}
			$out = $indicators . $out;
		}

		if ( isset( $opts['ill'] ) ) {
			$out = implode( ' ', $output->getLanguageLinks() );
		} elseif ( isset( $opts['cat'] ) ) {
			$out = '';
			foreach ( $output->getCategories() as $name => $sortkey ) {
				if ( $out !== '' ) {
					$out .= "\n";
				}
				$out .= "cat=$name sort=$sortkey";
			}
		}

		if ( isset( $output ) && isset( $opts['showflags'] ) ) {
			$actualFlags = array_keys( TestingAccessWrapper::newFromObject( $output )->mFlags );
			sort( $actualFlags );
			$out .= "\nflags=" . implode( ', ', $actualFlags );
		}

		ScopedCallback::consume( $teardownGuard );

		$expected = $test['result'];
		if ( count( $this->normalizationFunctions ) ) {
			$expected = ParserTestResultNormalizer::normalize(
				$test['expected'], $this->normalizationFunctions );
			$out = ParserTestResultNormalizer::normalize( $out, $this->normalizationFunctions );
		}

		$testResult = new ParserTestResult( $test, $expected, $out );
		return $testResult;
	}
}
