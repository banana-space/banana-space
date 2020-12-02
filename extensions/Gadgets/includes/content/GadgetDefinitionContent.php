<?php
/**
 * Copyright 2014
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

class GadgetDefinitionContent extends JsonContent {

	public function __construct( $text ) {
		parent::__construct( $text, 'GadgetDefinition' );
	}

	public function isValid() {
		// parent::isValid() is called in validate()
		return $this->validate()->isOK();
	}

	/**
	 * Pretty-print JSON.
	 *
	 * If called before validation, it may return JSON "null".
	 *
	 * @return string
	 */
	public function beautifyJSON() {
		// @todo we should normalize entries in module.scripts and module.styles
		return FormatJson::encode( $this->getAssocArray(), "\t", FormatJson::UTF8_OK );
	}

	/**
	 * Register some links
	 *
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput &$output
	 */
	protected function fillParserOutput( Title $title, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		parent::fillParserOutput( $title, $revId, $options, $generateHtml, $output );
		$assoc = $this->getAssocArray();
		foreach ( [ 'scripts', 'styles' ] as $type ) {
			foreach ( $assoc['module'][$type] as $page ) {
				$title = Title::makeTitleSafe( NS_GADGET, $page );
				if ( $title ) {
					$output->addLink( $title );
				}
			}
		}
	}

	/**
	 * @return Status
	 */
	public function validate() {
		if ( !parent::isValid() ) {
			return $this->getData();
		}

		$validator = new GadgetDefinitionValidator();
		return $validator->validate( $this->getAssocArray() );
	}

	/**
	 * Get the JSON content as an associative array with
	 * all fields filled out, populating defaults as necessary.
	 *
	 * @return array
	 * @suppress PhanUndeclaredMethod
	 */
	public function getAssocArray() {
		$info = wfObjectToArray( $this->getData()->getValue() );
		/** @var GadgetDefinitionContentHandler $handler */
		$handler = $this->getContentHandler();
		$info = wfArrayPlus2d( $info, $handler->getDefaultMetadata() );

		return $info;
	}

	/**
	 * @param WikiPage $page
	 * @param ParserOutput|null $parserOutput
	 * @return DeferrableUpdate[]
	 */
	public function getDeletionUpdates( WikiPage $page, ParserOutput $parserOutput = null ) {
		return array_merge(
			parent::getDeletionUpdates( $page, $parserOutput ),
			[ new GadgetDefinitionDeletionUpdate( $page->getTitle() ) ]
		);
	}

	/**
	 * @param Title $title
	 * @param Content|null $old
	 * @param bool $recursive
	 * @param ParserOutput|null $parserOutput
	 * @return DataUpdate[]
	 */
	public function getSecondaryDataUpdates( Title $title, Content $old = null,
		$recursive = true, ParserOutput $parserOutput = null
	) {
		return array_merge(
			parent::getSecondaryDataUpdates( $title, $old, $recursive, $parserOutput ),
			[ new GadgetDefinitionSecondaryDataUpdate( $title ) ]
		);
	}
}
