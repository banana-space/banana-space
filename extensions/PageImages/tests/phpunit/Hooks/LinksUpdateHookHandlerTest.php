<?php

namespace PageImages\Tests\Hooks;

use AbstractContent;
use File;
use LinksUpdate;
use MediaWiki\Revision\RevisionRecord;
use MediaWikiTestCase;
use PageImages\Hooks\LinksUpdateHookHandler;
use PageImages\PageImages;
use ParserOutput;
use RepoGroup;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \PageImages\Hooks\LinksUpdateHookHandler
 *
 * @group PageImages
 *
 * @license WTFPL
 * @author Thiemo Kreuz
 */
class LinksUpdateHookHandlerTest extends MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();

		// Force LinksUpdateHookHandler::getPageImageCanditates to look at all
		// sections.
		$this->setMwGlobals( 'wgPageImagesLeadSectionOnly', false );
	}

	/**
	 * @param array[] $images
	 * @param array[]|bool $leadImages
	 *
	 * @return LinksUpdate
	 */
	private function getLinksUpdate( array $images, $leadImages = false ) {
		$parserOutput = new ParserOutput();
		$parserOutput->setExtensionData( 'pageImages', $images );
		$parserOutputLead = new ParserOutput();
		$parserOutputLead->setExtensionData( 'pageImages', $leadImages ?: $images );

		$sectionContent = $this->getMockBuilder( AbstractContent::class )
			->disableOriginalConstructor()
			->getMock();

		$sectionContent->expects( $this->any() )
			->method( 'getParserOutput' )
			->will( $this->returnValue( $parserOutputLead ) );

		$content = $this->getMockBuilder( AbstractContent::class )
			->disableOriginalConstructor()
			->getMock();

		$content->expects( $this->any() )
			->method( 'getSection' )
			->will( $this->returnValue( $sectionContent ) );

		$revRecord = $this->getMockBuilder( RevisionRecord::class )
			->disableOriginalConstructor()
			->getMock();

		$revRecord->expects( $this->any() )
			->method( 'getContent' )
			->will( $this->returnValue( $content ) );

		$linksUpdate = $this->getMockBuilder( LinksUpdate::class )
			->disableOriginalConstructor()
			->getMock();

		$linksUpdate->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $this->createMock( Title::class ) ) );

		$linksUpdate->expects( $this->any() )
			->method( 'getParserOutput' )
			->will( $this->returnValue( $parserOutput ) );

		$linksUpdate->expects( $this->any() )
			->method( 'getRevisionRecord' )
			->will( $this->returnValue( $revRecord ) );

		return $linksUpdate;
	}

	/**
	 * Required to make RepoGroup::findFile in LinksUpdateHookHandler::getScore return something.
	 * @return RepoGroup
	 */
	private function getRepoGroup() {
		$file = $this->getMockBuilder( File::class )
			->disableOriginalConstructor()
			->getMock();
		// ugly hack to avoid all the unmockable crap in FormatMetadata
		$file->expects( $this->any() )
			->method( 'isDeleted' )
			->will( $this->returnValue( true ) );

		$repoGroup = $this->getMockBuilder( RepoGroup::class )
			->disableOriginalConstructor()
			->getMock();
		$repoGroup->expects( $this->any() )
			->method( 'findFile' )
			->will( $this->returnValue( $file ) );

		return $repoGroup;
	}

	/**
	 * @dataProvider provideDoLinksUpdate
	 * @covers \PageImages\Hooks\LinksUpdateHookHandler::doLinksUpdate
	 */
	public function testDoLinksUpdate(
		array $images,
		$expectedFreeFileName,
		$expectedNonFreeFileName
	) {
		$linksUpdate = $this->getLinksUpdate( $images );
		$mock = TestingAccessWrapper::newFromObject(
				$this->getMockBuilder( LinksUpdateHookHandler::class )
				->setMethods( [ 'getScore', 'isImageFree' ] )
				->getMock()
		);

		$scoreMap = [];
		$isFreeMap = [];
		$counter = 0;
		foreach ( $images as $image ) {
			array_push( $scoreMap, [ $image, $counter++, $image['score'] ] );
			array_push( $isFreeMap, [ $image['filename'], $image['isFree'] ] );
		}

		$mock->expects( $this->any() )
			->method( 'getScore' )
			->will( $this->returnValueMap( $scoreMap ) );

		$mock->expects( $this->any() )
			->method( 'isImageFree' )
			->will( $this->returnValueMap( $isFreeMap ) );

		$mock->doLinksUpdate( $linksUpdate );

		$this->assertTrue( property_exists( $linksUpdate, 'mProperties' ), 'precondition' );
		if ( $expectedFreeFileName === null ) {
			$this->assertArrayNotHasKey( PageImages::PROP_NAME_FREE, $linksUpdate->mProperties );
		} else {
			$this->assertSame( $expectedFreeFileName,
				$linksUpdate->mProperties[PageImages::PROP_NAME_FREE] );
		}
		if ( $expectedNonFreeFileName === null ) {
			$this->assertArrayNotHasKey( PageImages::PROP_NAME, $linksUpdate->mProperties );
		} else {
			$this->assertSame( $expectedNonFreeFileName, $linksUpdate->mProperties[PageImages::PROP_NAME] );
		}
	}

	public function provideDoLinksUpdate() {
		return [
			// both images are non-free
			[
				[
					[ 'filename' => 'A.jpg', 'score' => 100, 'isFree' => false ],
					[ 'filename' => 'B.jpg', 'score' => 90, 'isFree' => false ],
				],
				null,
				'A.jpg'
			],
			// both images are free
			[
				[
					[ 'filename' => 'A.jpg', 'score' => 100, 'isFree' => true ],
					[ 'filename' => 'B.jpg', 'score' => 90, 'isFree' => true ],
				],
				'A.jpg',
				null
			],
			// one free (with a higher score), one non-free image
			[
				[
					[ 'filename' => 'A.jpg', 'score' => 100, 'isFree' => true ],
					[ 'filename' => 'B.jpg', 'score' => 90, 'isFree' => false ],
				],
				'A.jpg',
				null
			],
			// one non-free (with a higher score), one free image
			[
				[
					[ 'filename' => 'A.jpg', 'score' => 100, 'isFree' => false ],
					[ 'filename' => 'B.jpg', 'score' => 90, 'isFree' => true ],
				],
				'B.jpg',
				'A.jpg'
			]
		];
	}

	/**
	 * @covers \PageImages\Hooks\LinksUpdateHookHandler::getPageImageCandidates
	 */
	public function testGetPageImageCandidates() {
		$candidates = [
				[ 'filename' => 'A.jpg', 'score' => 100, 'isFree' => false ],
				[ 'filename' => 'B.jpg', 'score' => 90, 'isFree' => false ],
		];
		$linksUpdate = $this->getLinksUpdate( $candidates, array_slice( $candidates, 0, 1 ) );

		// should get without lead.
		$handler = new LinksUpdateHookHandler();
		$this->setMwGlobals( 'wgPageImagesLeadSectionOnly', false );
		$images = $handler->getPageImageCandidates( $linksUpdate );
		$this->assertCount( 2, $images, 'All images are returned.' );

		$this->setMwGlobals( 'wgPageImagesLeadSectionOnly', true );
		$images = $handler->getPageImageCandidates( $linksUpdate );
		$this->assertCount( 1, $images, 'Only lead images are returned.' );
	}

	/**
	 * @dataProvider provideGetScore
	 */
	public function testGetScore( $image, $scoreFromTable, $position, $expected ) {
		$mock = TestingAccessWrapper::newFromObject(
			$this->getMockBuilder( LinksUpdateHookHandler::class )
				->setMethods( [ 'scoreFromTable', 'getMetadata', 'getRatio', 'getBlacklist' ] )
				->getMock()
		);
		$mock->expects( $this->any() )
			->method( 'scoreFromTable' )
			->will( $this->returnValue( $scoreFromTable ) );
		$mock->expects( $this->any() )
			->method( 'getRatio' )
			->will( $this->returnValue( 0 ) );
		$mock->expects( $this->any() )
			->method( 'getBlacklist' )
			->will( $this->returnValue( [ 'blacklisted.jpg' => 1 ] ) );

		$score = $mock->getScore( $image, $position );
		$this->assertEquals( $expected, $score );
	}

	public function provideGetScore() {
		return [
			[
				[ 'filename' => 'A.jpg', 'handler' => [ 'width' => 100 ] ],
				100,
				0,
				// width score + ratio score + position score
				100 + 100 + 8
			],
			[
				[ 'filename' => 'A.jpg', 'fullwidth' => 100 ],
				50,
				1,
				// width score + ratio score + position score
				106
			],
			[
				[ 'filename' => 'A.jpg', 'fullwidth' => 100 ],
				50,
				2,
				// width score + ratio score + position score
				104
			],
			[
				[ 'filename' => 'A.jpg', 'fullwidth' => 100 ],
				50,
				3,
				// width score + ratio score + position score
				103
			],
			[
				[ 'filename' => 'blacklisted.jpg', 'fullwidth' => 100 ],
				50,
				3,
				// blacklist score
				- 1000
			],
		];
	}

	/**
	 * @dataProvider provideScoreFromTable
	 * @covers \PageImages\Hooks\LinksUpdateHookHandler::scoreFromTable
	 */
	public function testScoreFromTable( array $scores, $value, $expected ) {
		$handlerWrapper = TestingAccessWrapper::newFromObject( new LinksUpdateHookHandler );

		$score = $handlerWrapper->scoreFromTable( $value, $scores );
		$this->assertEquals( $expected, $score );
	}

	public function provideScoreFromTable() {
		global $wgPageImagesScores;

		return [
			'no match' => [ [], 100, 0 ],
			'float' => [ [ 0.5 ], 0, 0.5 ],

			'always min when below range' => [ [ 200 => 2, 800 => 1 ], 0, 2 ],
			'always max when above range' => [ [ 200 => 2, 800 => 1 ], 1000, 1 ],

			'always min when below range (reversed)' => [ [ 800 => 1, 200 => 2 ], 0, 2 ],
			'always max when above range (reversed)' => [ [ 800 => 1, 200 => 2 ], 1000, 1 ],

			'min match' => [ [ 200 => 2, 400 => 3, 800 => 1 ], 200, 2 ],
			'above min' => [ [ 200 => 2, 400 => 3, 800 => 1 ], 201, 3 ],
			'second last match' => [ [ 200 => 2, 400 => 3, 800 => 1 ], 400, 3 ],
			'above second last' => [ [ 200 => 2, 400 => 3, 800 => 1 ], 401, 1 ],

			// These test cases use the default values from extension.json
			[ $wgPageImagesScores['width'], 100, -100 ],
			[ $wgPageImagesScores['width'], 119, -100 ],
			[ $wgPageImagesScores['width'], 300, 10 ],
			[ $wgPageImagesScores['width'], 400, 10 ],
			[ $wgPageImagesScores['width'], 500, 5 ],
			[ $wgPageImagesScores['width'], 600, 5 ],
			[ $wgPageImagesScores['width'], 601, 0 ],
			[ $wgPageImagesScores['width'], 999, 0 ],
			[ $wgPageImagesScores['galleryImageWidth'], 99, -100 ],
			[ $wgPageImagesScores['galleryImageWidth'], 100, 0 ],
			[ $wgPageImagesScores['galleryImageWidth'], 500, 0 ],
			[ $wgPageImagesScores['ratio'], 1, -100 ],
			[ $wgPageImagesScores['ratio'], 3, -100 ],
			[ $wgPageImagesScores['ratio'], 4, 0 ],
			[ $wgPageImagesScores['ratio'], 5, 0 ],
			[ $wgPageImagesScores['ratio'], 10, 5 ],
			[ $wgPageImagesScores['ratio'], 20, 5 ],
			[ $wgPageImagesScores['ratio'], 25, 0 ],
			[ $wgPageImagesScores['ratio'], 30, 0 ],
			[ $wgPageImagesScores['ratio'], 31, -100 ],
			[ $wgPageImagesScores['ratio'], 40, -100 ],

			'T212013' => [ $wgPageImagesScores['width'], 0, -100 ],
		];
	}

	/**
	 * @dataProvider provideIsFreeImage
	 * @covers \PageImages\Hooks\LinksUpdateHookHandler::isImageFree
	 */
	public function testIsFreeImage( $fileName, $metadata, $expected ) {
		$this->overrideMwServices( null, [
			'RepoGroup' => function () {
				return $this->getRepoGroup();
			}
		] );

		$mock = TestingAccessWrapper::newFromObject(
			$this->getMockBuilder( LinksUpdateHookHandler::class )
				->setMethods( [ 'fetchFileMetadata' ] )
				->getMock()
		);
		$mock->expects( $this->any() )
			->method( 'fetchFileMetadata' )
			->will( $this->returnValue( $metadata ) );
		$this->assertEquals( $expected, $mock->isImageFree( $fileName ) );
	}

	public function provideIsFreeImage() {
		return [
			[ 'A.jpg', [], true ],
			[ 'A.jpg', [ 'NonFree' => [ 'value' => '0' ] ], true ],
			[ 'A.jpg', [ 'NonFree' => [ 'value' => 0 ] ], true ],
			[ 'A.jpg', [ 'NonFree' => [ 'value' => false ] ], true ],
			[ 'A.jpg', [ 'NonFree' => [ 'value' => 'something' ] ], false ],
			[ 'A.jpg', [ 'something' => [ 'value' => 'something' ] ], true ],
		];
	}
}
