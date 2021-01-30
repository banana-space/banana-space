<?php

namespace Flow\Tests\Handlebars;

use Flow\Container;
use LightnCandy\LightnCandy;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @group Flow
 */
class FlowPostMetaActionsTest extends \MediaWikiTestCase {

	/**
	 * The specific timestamps used inside are not anything
	 * in particular, they just match the post and last edit
	 * uuid's we use in the test.
	 */
	public function timestampEditedProvider() {
		return array(
			array(
				'never been edited',
				// expected
				'02:52, 1 October 2014',
				// args
				array(
					'isOriginalContent' => true,
					'author' => 'creator',
					'creator' => 'creator',
					'lastEditUser' => null,
				),
			),

			array(
				'last edited by post creator',
				// expected
				'Edited 04:21, 9 October 2014',
				// args
				array(
					'isOriginalContent' => false,
					'author' => 'creator',
					'creator' => 'creator',
					'lastEditUser' => 'creator',
				),
			),

			array(
				'last edited by other than post creator',
				// expected
				'Edited by author 04:21, 9 October 2014',
				// args
				array(
					'isOriginalContent' => false,
					'author' => 'author',
					'creator' => 'creator',
					'lastEditUser' => 'author',
				),
			),

			array(
				'most recent revision not a content edit',
				// expected
				'Edited 04:21, 9 October 2014',
				// args
				array(
					'isOriginalContent' => false,
					'author' => 'author',
					'creator' => 'creator',
					'lastEditUser' => 'creator',
				),
			),
		);
	}

	/**
	 * @dataProvider timestampEditedProvider
	 */
	public function testTimestampEdited( $message, $expect, array $args ) {
		if ( !class_exists( Crawler::class ) ) {
			$this->markTestSkipped( 'DomCrawler component is not available.' );
			return;
		}

		$crawler = $this->renderTemplate(
			'flow_post_meta_actions.partial',
			array(
				'actions' => array(),
				'postId' => 's3chebds95i0atkw',
				'lastEditId' => 's3ufwcms95i0atkw',
				'isOriginalContent' => $args['isOriginalContent'],
				'author' => array(
					'name' => $args['author'],
				),
				'creator' => array(
					'name' => $args['creator'],
				),
				'lastEditUser' => array(
					'name' => $args['lastEditUser'],
				),
			)
		);

		$text = $crawler->filter( '.flow-post-timestamp' )->text();
		// normalize whitespace
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$this->assertStringStartsWith( $expect, $text, $message );
	}

	protected function renderTemplate( $templateName, array $args = array() ) {
		/** @var \Flow\TemplateHelper $lc */
		$lc = Container::get( 'lightncandy' );
		$filenames = $lc->getTemplateFilenames( $templateName );
		$phpCode = $lc::compile(
			file_get_contents( $filenames['template'] ),
			__DIR__ . '/../../../../handlebars'
		);
		$renderer = LightnCandy::prepare( $phpCode );

		return new Crawler( $renderer( $args ) );
	}
}
