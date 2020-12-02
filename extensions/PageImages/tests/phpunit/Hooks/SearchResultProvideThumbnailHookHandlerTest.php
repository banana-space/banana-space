<?php

namespace PageImages\Tests\Hooks;

use MediaWiki\Rest\Entity\SearchResultPageIdentityValue;
use MediaWikiTestCase;
use PageImages\Hooks\SearchResultProvideThumbnailHookHandler;
use PageImages\PageImages;
use PageProps;
use RepoGroup;

/**
 * @covers \PageImages\Hooks\SearchResultProvideThumbnailHookHandler
 *
 * @group PageImages
 */
class SearchResultProvideThumbnailHookHandlerTest extends MediaWikiTestCase {
	/**
	 * Creates mock object for LocalFile
	 * @return ThumbnailImage
	 */
	private function getMockThumbnailImage(
		int $size,
		LocalFile $file,
		$thumbFilePath
	): ThumbnailImage {
		$thumbnail = $this->getMockBuilder( ThumbnailImage::class )
			->disableOriginalConstructor()
			->setMethods( [
				'getLocalCopyPath',
				'getWidth',
				'getHeight',
				'getFile',
				'getUrl'
			] )
			->getMock();

			$thumbnail->expects( $this->once() )
				->method( 'getLocalCopyPath' )
				->willReturn( $thumbFilePath );

			$thumbnail->expects( $this->once() )
				->method( 'getUrl' )
				->willReturn( 'https://example.org/test.url' );

			$thumbnail->expects( $this->once() )
				->method( 'getWidth' )
				->willReturn( $size );

			$thumbnail->expects( $this->once() )
				->method( 'getHeight' )
				->willReturn( $size );

			$thumbnail->expects( $this->once() )
				->method( 'getFile' )
				->willReturn( $file );

		return $thumbnail;
	}

	/**
	 * Creates mock object for LocalFile
	 * @return LocalFile
	 */
	private function getMockLocalFile( int $size, $thumbFilePath ): LocalFile {
		$file = $this->getMockBuilder( LocalFile::class )
			->disableOriginalConstructor()
			->setMethods( [
				'transform',
				'getMimeType'
			] )
			->getMock();

		$file->expects( $this->once() )
			->method( 'transform' )
			->with( [ 'width' => $size , 'height' => $size ] )
			->willReturn( $this->getMockThumbnailImage( $size, $file, $thumbFilePath ) );

		$file->expects( $this->once() )
			->method( 'getMimeType' )
			->willReturn( 'image/jpg' );

		return $file;
	}

	public function testProvideThumbnail() {
		$pageProps = $this->getMockBuilder( PageProps::class )
			->disableOriginalConstructor()
			->setMethods( [ 'getProperties' ] )
			->getMock();

		$pageIdentities = [
			1 => new SearchResultPageIdentityValue( 1, NS_MAIN, 'dbKey1' ),
			2 => new SearchResultPageIdentityValue( 2, NS_MAIN, 'dbKey2' ),
			3 => new SearchResultPageIdentityValue( 3, NS_FILE, 'dbKey3' ),
			4 => new SearchResultPageIdentityValue( 4, NS_FILE, 'dbKey4' )
		];

		$pageProps->expects( $this->once() )
			->method( 'getProperties' )
			->with(
				$this->anything(),
				PageImages::getPropNames( PageImages::LICENSE_ANY )
			)->willReturn( [
				1 => [
					PageImages::getPropName( true ) => 'File1.jpg'
				],
				2 => [
					PageImages::getPropName( true ) => 'File2_free.jpg',
					PageImages::getPropName( false ) => 'File2_any.jpg'
				] ] );

		$repoGroup = $this->getMockBuilder( RepoGroup::class )
			->disableOriginalConstructor()
			->setMethods( [ 'findFile' ] )
			->getMock();

		$repoGroup->expects( $this->exactly( 4 ) )
					->method( 'findFile' )
					->withConsecutive( [ 'File1.jpg' ], [ 'File2_any.jpg' ], [ 'dbKey3' ], [ 'dbKey4' ] )
					->willReturnOnConsecutiveCalls(
						$this->getMockLocalFile(
							SearchResultProvideThumbnailHookHandler::THUMBNAIL_SIZE,
							__FILE__
						),
						null,
						$this->getMockLocalFile(
							SearchResultProvideThumbnailHookHandler::THUMBNAIL_SIZE,
							false
						),
						null
					);

		$handler = new SearchResultProvideThumbnailHookHandler( $pageProps, $repoGroup );

		$results = [ 1 => null, 2 => null, 3 => null, 4 => null ];
		$handler->doSearchResultProvideThumbnail( $pageIdentities, $results );

		$this->assertNull( $results[ 2 ] );
		$this->assertNull( $results[ 4 ] );

		$this->assertNotNull( $results[ 1 ] );
		$this->assertSame( 'File1.jpg', $results[ 1 ]->getName() );
		$this->assertSame(
			SearchResultProvideThumbnailHookHandler::THUMBNAIL_SIZE,
			$results[ 1 ]->getWidth()
		);
		$this->assertSame(
			SearchResultProvideThumbnailHookHandler::THUMBNAIL_SIZE,
			$results[ 1 ]->getHeight()
		);
		$this->assertGreaterThan( 0, $results[ 1 ]->getSize() );
		$this->assertSame( 'https://example.org/test.url', $results[ 1 ]->getUrl() );

		$this->assertNotNull( $results[ 3 ] );
		$this->assertNull( $results[ 3 ]->getSize() );
	}
}
