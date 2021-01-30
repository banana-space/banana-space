<?php

namespace Flow\Parsoid\Extractor;

use DOMElement;
use Flow\Model\WikiReference;
use Flow\Parsoid\Extractor;
use Flow\Parsoid\ReferenceFactory;
use FormatJson;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use Title;

/*
 * phpcs:disable Generic.Files.LineLength
 * Parsoid currently returns images that don't exist like:
 * <meta typeof="mw:Placeholder" data-parsoid='{"src":"[[File:Image.png|25px]]","optList":[{"ck":"width","ak":"25px"}],"dsr":[0,23,null,null]}'>
 * phpcs:enable Generic.Files.LineLength
 *
 * Links to those should also be registered, but since they're
 * different nodes than what we expect above, we'll have to deal
 * with them ourselves. This may change some day, as Parsoids
 * codebase has a FIXME "Handle missing images properly!!"
 */
class PlaceholderExtractor implements Extractor {
	/**
	 * @inheritDoc
	 */
	public function getXPath() {
		return '//*[contains(concat(" ", @typeof, " "), " mw:Placeholder" )]';
	}

	/**
	 * @inheritDoc
	 */
	public function perform( ReferenceFactory $factory, DOMElement $element ) {
		$data = FormatJson::decode( $element->getAttribute( 'data-parsoid' ), true );
		if ( !isset( $data['src'] ) ) {
			return null;
		}

		/*
		 * Parsoid only gives us the raw source to play with. Run it
		 * through Parser to make sure we're dealing with an image
		 * and get the image name.
		 */
		$output = MediaWikiServices::getInstance()->getParser()->parse(
			$data['src'],
			Title::newFromText( 'Main Page' ),
			ParserOptions::newFromAnon()
		);

		$file = $output->getImages();
		if ( !$file ) {
			return null;
		}
		// $file looks like array( 'Foo.jpg' => 1 )
		$image = Title::newFromText( key( $file ), NS_FILE );

		return $factory->createWikiReference( WikiReference::TYPE_FILE, $image->getPrefixedDBkey() );
	}
}
