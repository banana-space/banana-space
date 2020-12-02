<?php

namespace PageImages\Tests;

use PageImages\ApiQueryPageImages;
use PageImages\PageImages;
use Title;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers ApiQueryPageImages
 *
 * @group PageImages
 *
 * @license WTFPL
 * @author Sam Smith
 * @author Thiemo Kreuz
 */
class ApiQueryPageImagesTest extends \PHPUnit\Framework\TestCase {

	private function newInstance() {
		$config = new \HashConfig( [
			'PageImagesAPIDefaultLicense' => 'free'
		] );

		$context = $this->getMockBuilder( 'IContextSource' )
			->disableOriginalConstructor()
			->getMock();

		$context->expects( $this->any() )
			->method( 'getConfig' )
			->willReturn( $config );

		$main = $this->getMockBuilder( 'ApiMain' )
			->disableOriginalConstructor()
			->getMock();
		$main->expects( $this->once() )
			->method( 'getContext' )
			->will( $this->returnValue( $context ) );

		$query = $this->getMockBuilder( 'ApiQuery' )
			->disableOriginalConstructor()
			->getMock();
		$query->expects( $this->once() )
			->method( 'getMain' )
			->will( $this->returnValue( $main ) );

		return new ApiQueryPageImages( $query, '' );
	}

	public function testConstructor() {
		$instance = $this->newInstance();
		$this->assertInstanceOf( ApiQueryPageImages::class, $instance );
	}

	public function testGetCacheMode() {
		$instance = $this->newInstance();
		$this->assertSame( 'public', $instance->getCacheMode( [] ) );
	}

	public function testGetAllowedParams() {
		$instance = $this->newInstance();
		$params = $instance->getAllowedParams();
		$this->assertIsArray( $params );
		$this->assertNotEmpty( $params );
		$this->assertContainsOnly( 'array', $params );
		$this->assertArrayHasKey( 'limit', $params );
		$this->assertEquals( $params['limit'][\ApiBase::PARAM_DFLT], 50 );
		$this->assertEquals( $params['limit'][\ApiBase::PARAM_TYPE], 'limit' );
		$this->assertEquals( $params['limit'][\ApiBase::PARAM_MIN], 1 );
		$this->assertEquals( $params['limit'][\ApiBase::PARAM_MAX], 50 );
		$this->assertEquals( $params['limit'][\ApiBase::PARAM_MAX2], 100 );
		$this->assertArrayHasKey( 'license', $params );
		$this->assertEquals( $params['license'][\ApiBase::PARAM_TYPE], [ 'free', 'any' ] );
		$this->assertEquals( $params['license'][\ApiBase::PARAM_DFLT], 'free' );
		$this->assertEquals( $params['license'][\ApiBase::PARAM_ISMULTI], false );
	}

	/**
	 * @dataProvider provideGetTitles
	 */
	public function testGetTitles( $titles, $missingTitlesByNamespace, $expected ) {
		$pageSet = $this->getMockBuilder( 'ApiPageSet' )
			->disableOriginalConstructor()
			->getMock();
		$pageSet->expects( $this->any() )
			->method( 'getGoodTitles' )
			->will( $this->returnValue( $titles ) );
		$pageSet->expects( $this->any() )
			->method( 'getMissingTitlesByNamespace' )
			->will( $this->returnValue( $missingTitlesByNamespace ) );
		$queryPageImages = new ApiQueryPageImagesProxyMock( $pageSet );

		$this->assertEquals( $expected, $queryPageImages->getTitles() );
	}

	public function provideGetTitles() {
		return [
			[
				[ Title::makeTitle( NS_MAIN, 'Foo' ) ],
				[],
				[ Title::makeTitle( NS_MAIN, 'Foo' ) ],
			],
			[
				[ Title::makeTitle( NS_MAIN, 'Foo' ) ],
				[
					NS_TALK => [
						'Bar' => -1,
					],
				],
				[ Title::makeTitle( NS_MAIN, 'Foo' ) ],
			],
			[
				[ Title::makeTitle( NS_MAIN, 'Foo' ) ],
				[
					NS_FILE => [
						'Bar' => -1,
					],
				],
				[
					0 => Title::makeTitle( NS_MAIN, 'Foo' ),
					-1 => Title::makeTitle( NS_FILE, 'Bar' ),
				],
			],
		];
	}

	/**
	 * @dataProvider provideExecute
	 * @param array $requestParams Request parameters to the API
	 * @param array $titles Page titles passed to the API
	 * @param array $queryPageIds Page IDs that will be used for querying the DB.
	 * @param array $queryResults Results of the DB select query
	 * @param int $setResultValueCount The number results the API returned
	 */
	public function testExecute( $requestParams, $titles, $queryPageIds,
		$queryResults, $setResultValueCount
	) {
		$mock = TestingAccessWrapper::newFromObject(
			$this->getMockBuilder( ApiQueryPageImages::class )
				->disableOriginalConstructor()
				->setMethods( [ 'extractRequestParams', 'getTitles', 'setContinueParameter', 'dieUsage',
					'addTables', 'addFields', 'addWhere', 'select', 'setResultValues' ] )
				->getMock()
		);
		$mock->expects( $this->any() )
			->method( 'extractRequestParams' )
			->willReturn( $requestParams );
		$mock->expects( $this->any() )
			->method( 'getTitles' )
			->willReturn( $titles );
		$mock->expects( $this->any() )
			->method( 'select' )
			->willReturn( new FakeResultWrapper( $queryResults ) );

		// continue page ID is not found
		if ( isset( $requestParams['continue'] )
			&& $requestParams['continue'] > count( $titles )
		) {
			$mock->expects( $this->exactly( 1 ) )
				->method( 'dieUsage' );
		}

		$originalRequested = in_array( 'original', $requestParams['prop'] );
		$this->assertTrue( $this->hasExpectedProperties( $queryResults, $originalRequested ) );

		$license = isset( $requestParams['license'] ) ? $requestParams['license'] : 'free';
		if ( $license == PageImages::LICENSE_ANY ) {
			$propName = [ PageImages::getPropName( true ), PageImages::getPropName( false ) ];
		} else {
			$propName = PageImages::getPropName( true );
		}
		$mock->expects( $this->exactly( count( $queryPageIds ) > 0 ? 1 : 0 ) )
			->method( 'addWhere' )
			->with( [ 'pp_page' => $queryPageIds, 'pp_propname' => $propName ] );

		$mock->expects( $this->exactly( $setResultValueCount ) )
			->method( 'setResultValues' );

		$mock->execute();
	}

	public function provideExecute() {
		return [
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 100, 'limit' => 10, 'license' => 'any' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 0, 'pp_value' => 'A.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				2
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 200, 'limit' => 10 ],
				[],
				[],
				[],
				0
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'continue' => 1, 'thumbsize' => 400,
					'limit' => 10, 'license' => 'any' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 500, 'limit' => 10, 'license' => 'any' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'continue' => 1, 'thumbsize' => 500,
					'limit' => 10, 'license' => 'any' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 1 ],
				[
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
				],
				1
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 510, 'limit' => 10, 'license' => 'free' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 0, 1 ],
				[],
				0
			],
			[
				[ 'prop' => [ 'thumbnail' ], 'thumbsize' => 510, 'limit' => 10, 'license' => 'free' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 0, 1 ],
				[
					(object)[ 'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
					(object)[ 'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_propname' => PageImages::PROP_NAME_FREE ],
				],
				2
			],
			[
				[ 'prop' => [ 'thumbnail', 'original' ], 'thumbsize' => 510,
					'limit' => 10, 'license' => 'free' ],
				[ Title::newFromText( 'Page 1' ), Title::newFromText( 'Page 2' ) ],
				[ 0, 1 ],
				[
					(object)[
						'pp_page' => 0, 'pp_value' => 'A_Free.jpg',
						'pp_value_original' => 'A_Free_original.jpg', 'pp_original_width' => 80,
						'pp_original_height' => 80, 'pp_propname' => PageImages::PROP_NAME_FREE
					],
					(object)[
						'pp_page' => 1, 'pp_value' => 'B_Free.jpg',
						'pp_value_original' => 'B_Free_original.jpg', 'pp_original_width' => 80,
						'pp_original_height' => 80, 'pp_propname' => PageImages::PROP_NAME_FREE
					],
				],
				2
			],
		];
	}

	private function hasExpectedProperties( $queryResults, $originalRequested ) {
		if ( $originalRequested ) {
			return $this->allResultsHaveProperty( $queryResults, 'pp_value_original' )
				&& $this->allResultsHaveProperty( $queryResults, 'pp_original_width' )
				&& $this->allResultsHaveProperty( $queryResults, 'pp_original_height' );
		} else {
			return $this->noResultsHaveProperty( $queryResults, 'pp_value_original' )
				&& $this->noResultsHaveProperty( $queryResults, 'pp_original_width' )
				&& $this->noResultsHaveProperty( $queryResults, 'pp_original_height' );
		}
	}

	private function noResultsHaveProperty( $queryResults, $propName ) {
		foreach ( $queryResults as $result ) {
			if ( property_exists( $result, $propName ) ) {
				return false;
			}
		}
		return true;
	}

	private function allResultsHaveProperty( $queryResults, $propName ) {
		foreach ( $queryResults as $result ) {
			if ( !property_exists( $result, $propName ) ) {
				return false;
			}
		}
		return true;
	}
}
