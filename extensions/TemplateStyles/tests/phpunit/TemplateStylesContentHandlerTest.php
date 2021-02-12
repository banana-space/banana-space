<?php

/**
 * @group TemplateStyles
 * @covers TemplateStylesContentHandler
 */
class TemplateStylesContentHandlerTest extends MediaWikiLangTestCase {

	public function testBasics() {
		$handler = new TemplateStylesContentHandler();

		$this->assertSame( 'sanitized-css', $handler->getModelID() );
		$this->assertSame( [ 'text/css' ], $handler->getSupportedFormats() );
		$this->assertInstanceOf( TemplateStylesContent::class, $handler->makeEmptyContent() );

		$this->assertFalse( $handler->supportsRedirects() );

		$title = Title::newFromText( 'Template:Example/styles.css' );
		$this->assertNull( $handler->makeRedirectContent( $title ) );
	}

}
